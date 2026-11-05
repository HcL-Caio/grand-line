<?php
declare(strict_types=1);

/**
 * Testes ponta a ponta: sobe o servidor embutido do PHP com backend/router.php sobre um SQLite
 * com o catálogo real e exercita o CRUD pelo HTTP, com cookies de sessão de verdade.
 *
 *   php backend/tests/http.php
 */

require_once __DIR__ . '/Fixture.php';

$out = getenv('ATLAS_TEST_OUT') ?: sys_get_temp_dir();
$dbFile = $out . '/atlas-http-test.sqlite';
foreach ([$dbFile, $dbFile . '-wal', $dbFile . '-shm'] as $f) {
    @unlink($f);
}
Fixture::sqlite($dbFile);

$root = realpath(__DIR__ . '/../..');
$servers = [];
function startServer(int $port, string $root, array $env, array &$servers): void
{
    $cmd = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1',
        '-S', "127.0.0.1:{$port}", '-t', $root . '/dist', $root . '/backend/tests/test_router.php'];
    $proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', sys_get_temp_dir() . "/atlas-server-{$port}.log", 'w']], $pipes, $root, $env + getenv());
    $servers[] = $proc;
    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($socket) {
            fclose($socket);
            return;
        }
        usleep(100000);
    }
    throw new RuntimeException("servidor {$port} não subiu");
}
register_shutdown_function(static function () use (&$servers): void {
    foreach ($servers as $proc) {
        proc_terminate($proc);
    }
});
$port = 8171;
startServer($port, $root, ['ATLAS_TEST_DB' => $dbFile], $servers);
startServer($port + 1, $root, ['ATLAS_TEST_DB' => '', 'DB_USER' => '', 'DB_PASSWORD' => ''], $servers); // sem banco configurado

final class Client
{
    /** @var array<string,string> */
    public array $cookies = [];

    public function __construct(private readonly int $port) {}

    /** @return array{status:int,headers:array<string,list<string>>,body:string,location:?string} */
    public function request(string $method, string $path, array $form = [], array $headers = []): array
    {
        $curl = curl_init("http://127.0.0.1:{$this->port}{$path}");
        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $send = $headers;
        if ($this->cookies !== []) {
            $send[] = 'Cookie: ' . implode('; ', array_map(static fn ($k, $v) => "{$k}={$v}", array_keys($this->cookies), $this->cookies));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $send);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($form));
        }
        $body = (string)curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        foreach ($responseHeaders['set-cookie'] ?? [] as $cookie) {
            [$pair] = explode(';', $cookie, 2);
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $this->cookies[trim($name)] = $value;
        }
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body, 'location' => $responseHeaders['location'][0] ?? null];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** POST com o token CSRF extraído de uma página da própria sessão. */
    public function post(string $path, array $form, string $tokenPage): array
    {
        $page = $this->get($tokenPage);
        $form['_csrf'] = self::token($page['body']);
        return $this->request('POST', $path, $form);
    }

    public static function token(string $html): string
    {
        return preg_match('/name="_csrf" value="([0-9a-f]{64})"/', $html, $m) === 1 ? $m[1] : '';
    }
}

$passed = 0;
$failed = [];
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        return;
    }
    $failed[] = $name . ($detail !== '' ? " — {$detail}" : '');
    fwrite(STDERR, "  FALHOU: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n");
}
function header1(array $response, string $name): string
{
    return $response['headers'][strtolower($name)][0] ?? '';
}

$tag = bin2hex(random_bytes(3));
$ana = new Client($port);

echo "== Arquivos estáticos e roteamento\n";
$r = $ana->get('/index.html');
check('dist/ continua sendo servido (index.html)', $r['status'] === 200 && str_contains($r['body'], 'Grand Line'));
check('CSS do frontend servido', $ana->get('/styles.css')['status'] === 200);
$css = $ana->get('/app/assets/crud.css');
check('CSS do CRUD servido com tipo correto', $css['status'] === 200 && str_starts_with(header1($css, 'content-type'), 'text/css'));
check('não serve código PHP via /app/assets', $ana->get('/app/assets/../router.php')['status'] === 404 && $ana->get('/app/assets/..%2Frouter.php')['status'] === 404 && $ana->get('/app/assets/%2e%2e/src/App.php')['status'] === 404);
check('extensão não permitida em /app/assets é recusada', $ana->get('/app/assets/crud.php')['status'] === 404);
check('rota desconhecida em /app dá 404', $ana->get('/app/nada')['status'] === 404);
check('ID que não é UUID dá 404', $ana->get('/app/decks/nao-e-uuid')['status'] === 404);
$r = $ana->get('/app');
check('/app redireciona para a lista de decks', $r['status'] === 303 && str_ends_with((string)$r['location'], '/app/decks'));

echo "== Acesso sem login\n";
$r = $ana->get('/app/decks');
check('lista exige login (redireciona com next)', $r['status'] === 303 && str_starts_with((string)$r['location'], '/app/login?next='));
check('PDF exige login', $ana->get('/app/decks.pdf')['status'] === 303);
check('formulário de novo deck exige login', $ana->get('/app/decks/novo')['status'] === 303);
$r = (new Client($port))->get('/app/login'); // cliente novo: ainda sem cookie, então o servidor envia Set-Cookie
check('login abre', $r['status'] === 200 && str_contains($r['body'], 'Vamos embarcar'));
check('cabeçalhos de segurança presentes', header1($r, 'x-frame-options') === 'DENY' && header1($r, 'x-content-type-options') === 'nosniff' && str_contains(header1($r, 'content-security-policy'), "script-src 'self'") && str_contains(header1($r, 'content-security-policy'), "frame-ancestors 'none'"));
$setCookie = implode(' | ', $r['headers']['set-cookie'] ?? []);
check('cookie de sessão é HttpOnly e SameSite=Lax', str_contains($setCookie, 'atlas_sid=') && str_contains($setCookie, 'HttpOnly') && str_contains($setCookie, 'SameSite=Lax'));
check('páginas autenticadas não são cacheáveis', str_contains(header1($r, 'cache-control'), 'no-store'));
$r = $ana->request('POST', '/app/login', ['email' => 'x@y.com', 'password' => 'abcdefgh']);
check('POST sem CSRF é recusado (419)', $r['status'] === 419);
$r = $ana->request('POST', '/app/login', ['email' => 'x@y.com', 'password' => 'abcdefgh', '_csrf' => str_repeat('a', 64)]);
check('POST com CSRF falso é recusado (419)', $r['status'] === 419);
check('GET em rota só-POST dá 405', $ana->get('/app/logout')['status'] === 405);

echo "== Cadastro\n";
$r = $ana->post('/app/cadastro', ['name' => 'A', 'email' => 'ruim', 'password' => '123', 'password_confirm' => '321'], '/app/cadastro');
check('cadastro inválido: 422 com erros', $r['status'] === 422 && str_contains($r['body'], 'cx-field-error'));
check('campos digitados voltam preenchidos; senha nunca é devolvida', str_contains($r['body'], 'value="ruim"') && !str_contains($r['body'], 'value="123"'));
$r = $ana->post('/app/cadastro', ['name' => '"><script>alert(1)</script>', 'email' => "ana{$tag}@example.com", 'password' => 'curta', 'password_confirm' => 'curta'], '/app/cadastro');
check('XSS no nome é escapado no formulário', str_contains($r['body'], '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($r['body'], '<script>alert(1)'));

$fixed = new Client($port);
$fixed->cookies['atlas_sid'] = 'fixadoPeloAtacante1234567890abcd';
$fixed->get('/app/cadastro');
check('modo estrito: ID de sessão inventado pelo cliente não é aceito', ($fixed->cookies['atlas_sid'] ?? '') !== 'fixadoPeloAtacante1234567890abcd');

$before = $ana->cookies['atlas_sid'] ?? '';
$r = $ana->post('/app/cadastro', ['name' => 'Ana Lima', 'email' => "ana{$tag}@example.com", 'password' => 'senhaSegura1', 'password_confirm' => 'senhaSegura1'], '/app/cadastro');
check('cadastro válido redireciona para a lista', $r['status'] === 303 && str_ends_with((string)$r['location'], '/app/decks'));
check('ID de sessão é renovado ao autenticar', ($ana->cookies['atlas_sid'] ?? '') !== $before && $before !== '');
$r = $ana->get('/app/decks');
check('lista abre logada, com boas-vindas e estado vazio', $r['status'] === 200 && str_contains($r['body'], 'Conta criada') && str_contains($r['body'], 'ainda não montou nenhum deck'));
check('nome do usuário aparece no topo', str_contains($r['body'], 'Ana Lima'));
$dup = (new Client($port));
$r = $dup->post('/app/cadastro', ['name' => 'Outra', 'email' => strtoupper("ana{$tag}@example.com"), 'password' => 'senhaSegura1', 'password_confirm' => 'senhaSegura1'], '/app/cadastro');
check('e-mail repetido é recusado', $r['status'] === 422 && str_contains($r['body'], 'já está cadastrado'));
check('quem já está logado é levado para a lista ao abrir /login', $ana->get('/app/login')['status'] === 303);

echo "== Login, redirecionamentos e logout\n";
$login = new Client($port);
$r = $login->post('/app/login', ['email' => "ana{$tag}@example.com", 'password' => 'errada', 'next' => '/app/decks'], '/app/login');
check('senha errada: 401 e mensagem genérica', $r['status'] === 401 && str_contains($r['body'], 'E-mail ou senha incorretos.'));
$r = $login->post('/app/login', ['email' => '"><script>x</script>', 'password' => 'errada', 'next' => ''], '/app/login');
check('XSS no e-mail é escapado ao reexibir', !str_contains($r['body'], '"><script>x</script>') && str_contains($r['body'], '&lt;script&gt;'));
foreach (['//evil.example/x', 'https://evil.example', '/app/../etc', "/app/decks\r\nSet-Cookie: pwn=1", '\\\\evil.example', 'javascript:alert(1)'] as $next) {
    $c = new Client($port);
    $r = $c->post('/app/login', ['email' => "ana{$tag}@example.com", 'password' => 'senhaSegura1', 'next' => $next], '/app/login');
    $label = substr(addcslashes($next, "\r\n"), 0, 24);
    check("next malicioso cai na lista de decks: {$label}", $r['status'] === 303 && $r['location'] === '/app/decks', (string)$r['location']);
    check("sem injeção de cabeçalho: {$label}", !isset($c->cookies['pwn']) && !isset($r['headers']['pwn']));
}
$c = new Client($port);
$r = $c->get('/app/decks/novo');
parse_str((string)parse_url((string)$r['location'], PHP_URL_QUERY), $q);
check('visitante é mandado ao login com o destino pedido', ($q['next'] ?? '') === '/app/decks/novo');
$r = $c->post('/app/login', ['email' => "ana{$tag}@example.com", 'password' => 'senhaSegura1', 'next' => $q['next'] ?? ''], '/app/login');
check('login volta para a página pedida (next válido)', $r['status'] === 303 && $r['location'] === '/app/decks/novo', (string)$r['location']);

echo "== CRUD de decks pelo navegador\n";
$leader = 'OP01-001';
$r = $ana->post('/app/decks', ['name' => '', 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft', 'don_quantity' => '10'], '/app/decks/novo');
check('criar sem nome: 422 com mensagem e formulário mantido', $r['status'] === 422 && str_contains($r['body'], 'Informe um nome'));
$xssName = '<img src=x onerror=alert(1)> Zoro Red';
$r = $ana->post('/app/decks', ['name' => $xssName, 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft', 'leader_card_id' => $leader, 'don_quantity' => '10', 'description' => '<b>negrito?</b>'], '/app/decks/novo');
check('criar deck: 303 para o editor', $r['status'] === 303 && preg_match('#^/app/decks/([0-9a-f-]{36})/editar$#', (string)$r['location'], $m) === 1);
$deckId = $m[1] ?? '';
$edit = $ana->get("/app/decks/{$deckId}/editar");
check('editor abre com aviso de criação', $edit['status'] === 200 && str_contains($edit['body'], 'Deck criado'));
check('nome com HTML é escapado no editor', !str_contains($edit['body'], '<img src=x') && str_contains($edit['body'], '&lt;img src=x onerror=alert(1)&gt;'));
check('Líder aparece no editor', str_contains($edit['body'], 'OP01-001'));

$s = $ana->get("/app/decks/{$deckId}/editar?search=1&q=" . urlencode('zoro') . '&compat=1');
check('busca de cartas devolve resultados com botão Adicionar', $s['status'] === 200 && str_contains($s['body'], 'cx-card') && str_contains($s['body'], 'Adicionar'));
preg_match_all('#name="card_id" value="([A-Za-z0-9._-]+)"#', $s['body'], $ids);
$candidate = '';
foreach ($ids['1'] as $id) {
    if ($id !== $leader) {
        $candidate = $id;
        break;
    }
}
check('há carta candidata nos resultados', $candidate !== '');
preg_match('/name="revision" value="(\d+)"/', $s['body'], $rv);
$back = '?search=1&q=zoro&compat=1';
$r = $ana->post("/app/decks/{$deckId}/itens", ['card_id' => $candidate, 'quantity' => '2', 'revision' => $rv[1], 'back' => $back], "/app/decks/{$deckId}/editar");
check('adicionar carta: volta à mesma busca', $r['status'] === 303 && str_contains((string)$r['location'], 'search=1') && str_ends_with((string)$r['location'], '#busca'), (string)$r['location']);
$edit = $ana->get("/app/decks/{$deckId}/editar");
check('carta aparece na lista do deck com quantidade 2', str_contains($edit['body'], 'value="2"') && str_contains($edit['body'], $candidate));
$r = $ana->post("/app/decks/{$deckId}/itens", ['card_id' => $candidate, 'quantity' => '9', 'revision' => $rv[1], 'back' => ''], "/app/decks/{$deckId}/editar");
$follow = $ana->get((string)$r['location']);
check('revisão desatualizada: aviso de conflito na tela', str_contains($follow['body'], 'alterado em outra aba'));
preg_match('/name="revision" value="(\d+)"/', $follow['body'], $rv2);
$r = $ana->post("/app/decks/{$deckId}/itens", ['card_id' => $candidate, 'quantity' => '9', 'revision' => $rv2[1], 'back' => ''], "/app/decks/{$deckId}/editar");
$follow = $ana->get((string)$r['location']);
check('limite por ID: erro claro na tela', str_contains($follow['body'], 'o limite é'));
$r = $ana->post("/app/decks/{$deckId}/editar", ['name' => 'Zoro Vermelho', 'format_code' => 'standard', 'visibility' => 'unlisted', 'status' => 'ready', 'don_quantity' => '10', 'description' => 'nota', 'revision' => $rv2[1]], "/app/decks/{$deckId}/editar");
check('marcar pronto sem estrutura: 422 listando pendências, dados digitados mantidos', $r['status'] === 422 && str_contains($r['body'], 'ainda não pode ser marcado como pronto') && str_contains($r['body'], 'value="Zoro Vermelho"'));
$r = $ana->post("/app/decks/{$deckId}/editar", ['name' => 'Zoro Vermelho', 'format_code' => 'standard', 'visibility' => 'unlisted', 'status' => 'draft', 'don_quantity' => '10', 'description' => 'nota', 'revision' => $rv2[1]], "/app/decks/{$deckId}/editar");
check('salvar dados: 303 e mensagem', $r['status'] === 303 && str_contains($ana->get((string)$r['location'])['body'], 'Dados do deck salvos'));
$show = $ana->get("/app/decks/{$deckId}");
check('página do deck: nome, Líder, curva e cartas', $show['status'] === 200 && str_contains($show['body'], 'Zoro Vermelho') && str_contains($show['body'], 'Roronoa Zoro') && str_contains($show['body'], 'cx-curve') && str_contains($show['body'], $candidate));
check('página do deck mostra pendências de estrutura', str_contains($show['body'], 'exatamente 50 cartas'));
$edit = $ana->get("/app/decks/{$deckId}/editar");
preg_match('/name="revision" value="(\d+)"/', $edit['body'], $rv3);
$r = $ana->post("/app/decks/{$deckId}/itens/atualizar", ['quantities' => [$candidate => '3'], 'revision' => $rv3[1]], "/app/decks/{$deckId}/editar");
check('atualizar quantidades em lote', $r['status'] === 303 && str_contains($ana->get("/app/decks/{$deckId}/editar")['body'], 'value="3"'));
$r = $ana->post("/app/decks/{$deckId}/lider", ['card_id' => 'OP01-002', 'revision' => (string)((int)$rv3[1] + 1)], "/app/decks/{$deckId}/editar");
check('trocar o Líder', $r['status'] === 303 && str_contains($ana->get("/app/decks/{$deckId}")['body'], 'OP01-002'));

echo "== Segundo deck, filtros e PDF\n";
for ($i = 1; $i <= 3; $i++) {
    $ana->post('/app/decks', ['name' => "Filtro {$i}", 'format_code' => $i === 1 ? 'standard' : 'study', 'visibility' => $i === 3 ? 'public' : 'private', 'status' => 'draft', 'don_quantity' => '10'], '/app/decks/novo');
}
$r = $ana->get('/app/decks?q=Filtro');
check('filtro por nome na tela', str_contains($r['body'], '3 decks encontrados'));
$r = $ana->get('/app/decks?q=Filtro&format=standard');
check('filtros combinados na tela', str_contains($r['body'], '1 deck encontrado') && str_contains($r['body'], 'Filtro 1') && !str_contains($r['body'], 'Filtro 2'));
$r = $ana->get('/app/decks?visibility=public');
check('filtro por visibilidade na tela', str_contains($r['body'], '1 deck encontrado') && str_contains($r['body'], 'Filtro 3'));
$r = $ana->get('/app/decks?color=Red');
check('filtro por cor do Líder na tela', str_contains($r['body'], '1 deck encontrado') && str_contains($r['body'], 'Zoro Vermelho'));
$r = $ana->get('/app/decks?q=nenhum-deck-com-este-nome');
check('sem resultados: mensagem e botão para limpar filtros', str_contains($r['body'], 'Nenhum deck com esses filtros') && str_contains($r['body'], 'Limpar filtros'));
$r = $ana->get('/app/decks?q=' . urlencode('<script>alert(1)</script>') . '&status=<x>');
check('XSS em parâmetro de filtro é escapado', $r['status'] === 200 && !str_contains($r['body'], '<script>alert(1)</script>'));

$pdf = $ana->get('/app/decks.pdf');
check('PDF da lista: 200 application/pdf', $pdf['status'] === 200 && header1($pdf, 'content-type') === 'application/pdf' && str_starts_with($pdf['body'], '%PDF-1.4'));
check('PDF tem Content-Length correto', (int)header1($pdf, 'content-length') === strlen($pdf['body']));
file_put_contents($out . '/atlas-http-lista.pdf', $pdf['body']);
$pdfFiltered = $ana->get('/app/decks.pdf?q=Filtro&format=standard');
file_put_contents($out . '/atlas-http-filtrada.pdf', $pdfFiltered['body']);
if (is_executable('/usr/bin/pdftotext')) {
    $text = (string)shell_exec('pdftotext -layout ' . escapeshellarg($out . '/atlas-http-lista.pdf') . ' - 2>&1');
    check('PDF lista os 4 decks e o usuário', str_contains($text, 'Zoro Vermelho') && str_contains($text, 'Filtro 1') && str_contains($text, 'Filtro 3') && str_contains($text, 'Ana Lima') && str_contains($text, '4 deck(s)'));
    $textF = (string)shell_exec('pdftotext -layout ' . escapeshellarg($out . '/atlas-http-filtrada.pdf') . ' - 2>&1');
    check('PDF respeita os filtros e os descreve', str_contains($textF, 'Filtro 1') && !str_contains($textF, 'Filtro 2') && str_contains($textF, 'nome contém "Filtro"') && str_contains($textF, '1 deck(s)'));
}
$deckPdf = $ana->get("/app/decks/{$deckId}.pdf");
check('PDF de um deck', $deckPdf['status'] === 200 && header1($deckPdf, 'content-type') === 'application/pdf');
file_put_contents($out . '/atlas-http-deck.pdf', $deckPdf['body']);
if (is_executable('/usr/bin/pdftotext')) {
    $text = (string)shell_exec('pdftotext -layout ' . escapeshellarg($out . '/atlas-http-deck.pdf') . ' - 2>&1');
    check('PDF do deck traz Líder, cartas e quantidade', str_contains($text, 'OP01-002') && str_contains($text, $candidate) && str_contains($text, '3x'));
}

echo "== Isolamento entre usuários\n";
$bob = new Client($port);
$bob->post('/app/cadastro', ['name' => 'Bob Souza', 'email' => "bob{$tag}@example.com", 'password' => 'outraSenha22', 'password_confirm' => 'outraSenha22'], '/app/cadastro');
check('Bob logado vê lista vazia', str_contains($bob->get('/app/decks')['body'], 'ainda não montou nenhum deck'));
check('Bob não abre o deck da Ana (404)', $bob->get("/app/decks/{$deckId}")['status'] === 404);
check('Bob não abre o editor da Ana (404)', $bob->get("/app/decks/{$deckId}/editar")['status'] === 404);
check('Bob não baixa o PDF do deck da Ana (404)', $bob->get("/app/decks/{$deckId}.pdf")['status'] === 404);
$r = $bob->post("/app/decks/{$deckId}/excluir", [], '/app/decks');
check('Bob não consegue excluir o deck da Ana (404)', $r['status'] === 404);
$r = $bob->post("/app/decks/{$deckId}/editar", ['name' => 'Roubado', 'format_code' => 'study', 'visibility' => 'public', 'status' => 'draft', 'don_quantity' => '10', 'revision' => '1'], '/app/decks');
check('Bob não consegue editar o deck da Ana (404)', $r['status'] === 404);
check('o deck da Ana continua intacto', str_contains($ana->get("/app/decks/{$deckId}")['body'], 'Zoro Vermelho'));
$r = $bob->request('POST', "/app/decks/{$deckId}/excluir", ['_csrf' => Client::token($ana->get('/app/decks/novo')['body'])]);
check('token CSRF da Ana não vale na sessão do Bob (419)', $r['status'] === 419);

echo "== Excluir e sair\n";
$r = $ana->post("/app/decks/{$deckId}/excluir", [], "/app/decks/{$deckId}");
check('excluir: 303 para a lista com mensagem', $r['status'] === 303 && str_contains($ana->get((string)$r['location'])['body'], 'Deck excluído'));
check('deck excluído some (404)', $ana->get("/app/decks/{$deckId}")['status'] === 404);
check('excluir de novo: 404', $ana->post("/app/decks/{$deckId}/excluir", [], '/app/decks')['status'] === 404);
$oldSession = $ana->cookies['atlas_sid'];
$r = $ana->post('/app/logout', [], '/app/decks');
check('logout: 303 para o login', $r['status'] === 303 && str_ends_with((string)$r['location'], '/app/login'));
check('depois do logout a lista exige login de novo', $ana->get('/app/decks')['status'] === 303);
$replay = new Client($port);
$replay->cookies['atlas_sid'] = $oldSession;
check('ID de sessão antigo não volta a funcionar', $replay->get('/app/decks')['status'] === 303);

echo "== Banco indisponível\n";
$down = new Client($port + 1);
$r = $down->get('/app/login');
check('sem banco: 503 amigável', $r['status'] === 503 && str_contains($r['body'], 'banco de dados não está disponível'));
check('sem banco: nada de SQL, caminhos ou usuário no HTML', !preg_match('#/home/|\.php|PDOException|SQLSTATE|stack trace#i', $r['body']));
check('sem banco: arquivos estáticos continuam funcionando', $down->get('/index.html')['status'] === 200);

$total = $passed + count($failed);
echo "\n" . str_repeat('=', 60) . "\n";
echo $failed === [] ? "OK — {$passed}/{$total} verificações HTTP passaram.\n" : count($failed) . " de {$total} FALHARAM:\n - " . implode("\n - ", $failed) . "\n";
exit($failed === [] ? 0 : 1);
