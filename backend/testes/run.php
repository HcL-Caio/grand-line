<?php
declare(strict_types=1);

/**
 * Suíte de testes do backend (repositórios, autenticação, filtros e PDF).
 *
 *   php backend/tests/run.php            # SQLite com o catálogo real (sem servidor de banco)
 *   php backend/tests/run.php --mysql    # MySQL 8.4 real, via DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD;
 *                                        # tudo roda dentro de uma transação e termina em ROLLBACK
 */

use Atlas\App;
use Atlas\Auth;
use Atlas\Cards;
use Atlas\Decks;
use Atlas\Pdf;

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/Fixture.php';

ob_start(); // sessões em CLI emitem cabeçalhos
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '0');
session_save_path(sys_get_temp_dir());
session_start();

$mysql = in_array('--mysql', $argv, true);
$pdo = $mysql ? App::pdo() : Fixture::sqlite();
App::usePdo($pdo);
if ($mysql) {
    $pdo->beginTransaction(); // nada do que o teste fizer permanece no banco
}

$passed = 0;
$failed = [];
function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed[] = $name . ($detail !== '' ? " — {$detail}" : '');
    fwrite(STDERR, "  FALHOU: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n");
}
function section(string $title): void
{
    echo "\n== {$title}\n";
}
function scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}
function column(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}
function deck(PDO $pdo, int $userId, string $publicId): array
{
    return Decks::find($pdo, $userId, $publicId) ?? throw new RuntimeException('deck não encontrado no teste');
}
/** Executa $fn e informa se lançou exceção (sem derrubar a suíte). */
function throws(callable $fn): bool
{
    try {
        $fn();
    } catch (Throwable) {
        return true;
    }
    return false;
}

$tag = bin2hex(random_bytes(3));

// ------------------------------------------------------------------ cartas de apoio
$leaderRed = (string)scalar($pdo, "SELECT c.card_id FROM cards c JOIN card_colors cc ON cc.card_id=c.card_id AND cc.color_code='Red'
    WHERE c.type_code='LEADER' AND (SELECT COUNT(*) FROM card_colors x WHERE x.card_id=c.card_id)=1 ORDER BY c.card_id LIMIT 1");
$leaderBlue = (string)scalar($pdo, "SELECT c.card_id FROM cards c JOIN card_colors cc ON cc.card_id=c.card_id AND cc.color_code='Blue'
    WHERE c.type_code='LEADER' AND (SELECT COUNT(*) FROM card_colors x WHERE x.card_id=c.card_id)=1 ORDER BY c.card_id LIMIT 1");
$redPool = column($pdo, "SELECT c.card_id FROM cards c WHERE c.type_code IN ('CHARACTER','EVENT','STAGE') AND c.deck_copy_limit=4
    AND c.archived_at IS NULL AND EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id=c.card_id)
    AND NOT EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id=c.card_id AND cc.color_code<>'Red') ORDER BY c.card_id LIMIT 20");
$blueCard = (string)scalar($pdo, "SELECT c.card_id FROM cards c WHERE c.type_code='CHARACTER' AND EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id=c.card_id)
    AND NOT EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id=c.card_id AND cc.color_code<>'Blue') ORDER BY c.card_id LIMIT 1");
$donCard = (string)scalar($pdo, "SELECT card_id FROM cards WHERE type_code='DON' ORDER BY card_id LIMIT 1");
$anyNumber = scalar($pdo, "SELECT card_id FROM cards WHERE type_code IN ('CHARACTER','EVENT','STAGE') AND deck_copy_limit>4 ORDER BY card_id LIMIT 1");
check('dados de apoio encontrados', $leaderRed !== '' && $leaderBlue !== '' && count($redPool) >= 13 && $blueCard !== '' && $donCard !== '');

// ====================================================================== AUTENTICAÇÃO
section('Autenticação');
$bad = Auth::register($pdo, ['name' => 'A', 'email' => 'nao-e-email', 'password' => 'curta', 'password_confirm' => 'x']);
check('cadastro inválido é recusado', !$bad['ok']);
check('erros por campo (nome, e-mail, senha)', isset($bad['errors']['name'], $bad['errors']['email'], $bad['errors']['password']));
$mismatch = Auth::register($pdo, ['name' => 'Ana Lima', 'email' => "ana{$tag}@example.com", 'password' => 'senhaSegura1', 'password_confirm' => 'outraSenha1']);
check('confirmação diferente é recusada', !$mismatch['ok'] && isset($mismatch['errors']['password_confirm']));

$ana = Auth::register($pdo, ['name' => 'Ana Lima', 'email' => "Ana{$tag}@Example.com", 'password' => 'senhaSegura1', 'password_confirm' => 'senhaSegura1']);
check('cadastro válido', $ana['ok'] && is_int($ana['user_id']));
$anaId = (int)$ana['user_id'];
$row = $pdo->query("SELECT * FROM users WHERE user_id = {$anaId}")->fetch();
check('e-mail normalizado em minúsculas', $row['email'] === "ana{$tag}@example.com");
check('senha guardada como hash, nunca em texto puro', $row['password_hash'] !== 'senhaSegura1' && password_verify('senhaSegura1', $row['password_hash']));
check('conta nasce ativa com perfil member', $row['status'] === 'active' && $row['role_code'] === 'member');
check('nome de usuário interno único e sem espaços', preg_match('/^[a-z0-9-]+$/', $row['username']) === 1);
$dup = Auth::register($pdo, ['name' => 'Outra Ana', 'email' => "ANA{$tag}@example.com", 'password' => 'senhaSegura1', 'password_confirm' => 'senhaSegura1']);
check('e-mail duplicado (ignorando caixa) é recusado', !$dup['ok'] && isset($dup['errors']['email']));
$bob = Auth::register($pdo, ['name' => 'Bob Souza', 'email' => "bob{$tag}@example.com", 'password' => 'outraSenha22', 'password_confirm' => 'outraSenha22']);
$bobId = (int)$bob['user_id'];
check('segundo usuário criado', $bob['ok']);

$wrong = Auth::login($pdo, "ana{$tag}@example.com", 'errada123');
check('senha errada recusada com mensagem genérica', !$wrong['ok'] && $wrong['error'] === 'E-mail ou senha incorretos.');
$ghost = Auth::login($pdo, "ninguem{$tag}@example.com", 'qualquer123');
check('e-mail inexistente recebe a mesma mensagem', !$ghost['ok'] && $ghost['error'] === $wrong['error']);
$ok = Auth::login($pdo, "ANA{$tag}@example.com", 'senhaSegura1');
check('login correto (e-mail sem diferenciar caixa)', $ok['ok']);
check('sessão guarda apenas o id do usuário', ($_SESSION['uid'] ?? null) === $anaId);
check('Auth::user carrega o usuário ativo', (Auth::user($pdo)['user_id'] ?? null) === $anaId);
Auth::logout();
check('logout limpa a sessão', !isset($_SESSION['uid']) && Auth::user($pdo) === null);

// Bloqueio por tentativas: 5 falhas (as 2 acima já contam) travam até com a senha certa.
for ($i = 0; $i < 4; $i++) {
    Auth::login($pdo, "ana{$tag}@example.com", 'errada' . $i);
}
$locked = Auth::login($pdo, "ana{$tag}@example.com", 'senhaSegura1');
check('limite de tentativas bloqueia login, mesmo com a senha correta', !$locked['ok'] && str_contains((string)$locked['error'], 'Muitas tentativas'));
check('login de outro e-mail não é afetado pelo bloqueio', Auth::login($pdo, "bob{$tag}@example.com", 'outraSenha22')['ok']);
Auth::logout();
check('falhas registradas sem guardar o e-mail em claro', (int)scalar($pdo, "SELECT COUNT(*) FROM audit_events WHERE action='auth.login_failed' AND entity_key = ?", [hash('sha256', "ana{$tag}@example.com")]) >= 5);

$pdo->exec("UPDATE users SET status='suspended' WHERE user_id = {$bobId}");
check('conta suspensa não entra', !Auth::login($pdo, "bob{$tag}@example.com", 'outraSenha22')['ok']);
$pdo->exec("UPDATE users SET status='active' WHERE user_id = {$bobId}");
Auth::reset();

// ====================================================================== CRUD DE DECKS
section('Decks — criar');
$none = Decks::create($pdo, $anaId, ['name' => '   ', 'format_code' => 'study', 'visibility' => 'private']);
check('nome vazio recusado', !$none['ok']);
check('formato inexistente recusado', !Decks::create($pdo, $anaId, ['name' => 'X', 'format_code' => 'inventado'])['ok']);
check('visibilidade inválida recusada', !Decks::create($pdo, $anaId, ['name' => 'X', 'visibility' => 'todos'])['ok']);
check('DON!! fora de 0–10 recusado', !Decks::create($pdo, $anaId, ['name' => 'X', 'don_quantity' => '11'])['ok']);
check('Líder que não é Líder recusado', !Decks::create($pdo, $anaId, ['name' => 'X', 'leader_card_id' => $redPool[0]])['ok']);
check('Líder inexistente recusado', !Decks::create($pdo, $anaId, ['name' => 'X', 'leader_card_id' => 'ZZ99-999'])['ok']);
check('deck novo não pode nascer como pronto', !Decks::create($pdo, $anaId, ['name' => 'X', 'status' => 'ready'])['ok']);
check('nada foi gravado pelas tentativas inválidas', (int)scalar($pdo, 'SELECT COUNT(*) FROM decks WHERE user_id = ?', [$anaId]) === 0);

$created = Decks::create($pdo, $anaId, ['name' => 'Alpha Vermelho', 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft', 'leader_card_id' => $leaderRed, 'description' => "Linha 1\nLinha 2", 'don_quantity' => '10']);
check('deck válido criado', $created['ok'] && preg_match('/^[0-9a-f-]{36}$/', (string)$created['public_id']) === 1);
$alpha = (string)$created['public_id'];
$d = deck($pdo, $anaId, $alpha);
check('campos gravados corretamente', $d['name'] === 'Alpha Vermelho' && $d['leader_card_id'] === $leaderRed && $d['revision'] === 1 && $d['status'] === 'draft' && $d['don_quantity'] === 10);
check('descrição preservada com quebra de linha', $d['description'] === "Linha 1\nLinha 2");
check('Líder tem cor e imagem resolvidas', $d['leader_colors'] === ['Red'] && $d['leader_image'] !== null);
check('criação auditada', (int)scalar($pdo, "SELECT COUNT(*) FROM audit_events WHERE action='deck.created' AND entity_key = ?", [$alpha]) === 1);

section('Decks — autorização entre usuários');
check('outro usuário não enxerga o deck', Decks::find($pdo, $bobId, $alpha) === null);
check('outro usuário não o vê na listagem', Decks::list($pdo, $bobId, [])['total'] === 0);
$stolen = Decks::update($pdo, $bobId, $alpha, ['name' => 'Roubado', 'revision' => 1]);
check('outro usuário não consegue editar (404 lógico)', !$stolen['ok'] && $stolen['reason'] === 'not_found');
check('outro usuário não consegue adicionar cartas', Decks::addItem($pdo, $bobId, $alpha, ['card_id' => $redPool[0], 'quantity' => 1, 'revision' => 1])['reason'] === 'not_found');
check('outro usuário não consegue trocar o Líder', Decks::setLeader($pdo, $bobId, $alpha, ['card_id' => $leaderBlue, 'revision' => 1])['reason'] === 'not_found');
check('outro usuário não consegue excluir', Decks::delete($pdo, $bobId, $alpha) === false);
check('o deck permanece intacto', deck($pdo, $anaId, $alpha)['name'] === 'Alpha Vermelho' && deck($pdo, $anaId, $alpha)['revision'] === 1);
check('formato de ID inválido não consulta o banco', Decks::find($pdo, $anaId, "x' OR '1'='1") === null && Decks::delete($pdo, $anaId, '../../etc') === false);

section('Decks — editar dados e concorrência (revision)');
$u1 = Decks::update($pdo, $anaId, $alpha, ['name' => 'Alpha Vermelho v2', 'format_code' => 'standard', 'visibility' => 'unlisted', 'status' => 'draft', 'description' => '', 'don_quantity' => '8', 'revision' => 1]);
check('atualização com a revisão correta', $u1['ok']);
$d = deck($pdo, $anaId, $alpha);
check('dados alterados e revisão incrementada', $d['name'] === 'Alpha Vermelho v2' && $d['format_code'] === 'standard' && $d['visibility'] === 'unlisted' && $d['don_quantity'] === 8 && $d['revision'] === 2);
check('descrição vazia vira NULL', $d['description'] === null);
$stale = Decks::update($pdo, $anaId, $alpha, ['name' => 'Edição antiga', 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft', 'don_quantity' => '10', 'revision' => 1]);
check('revisão antiga é recusada como conflito', !$stale['ok'] && $stale['reason'] === 'conflict');
check('conflito não altera nada', deck($pdo, $anaId, $alpha)['name'] === 'Alpha Vermelho v2' && deck($pdo, $anaId, $alpha)['revision'] === 2);
check('revisão ausente também é recusada', Decks::update($pdo, $anaId, $alpha, ['name' => 'Sem revisão', 'don_quantity' => '10'])['reason'] === 'conflict');
check('edição inválida não incrementa a revisão', !Decks::update($pdo, $anaId, $alpha, ['name' => '', 'revision' => 2])['ok'] && deck($pdo, $anaId, $alpha)['revision'] === 2);
$rev = static fn (string $id): int => deck($GLOBALS['pdo'], $GLOBALS['anaId'], $id)['revision'];
Decks::update($pdo, $anaId, $alpha, ['name' => 'Alpha Vermelho', 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft', 'don_quantity' => '10', 'revision' => 2]);

section('Decks — cartas (adicionar, limites, lote)');
$r = Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[0], 'quantity' => '2', 'revision' => $rev($alpha)]);
check('adicionar carta compatível', $r['ok']);
$items = Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id']);
check('item gravado com quantidade', count($items) === 1 && $items[0]['quantity'] === 2 && $items[0]['card_id'] === $redPool[0]);
check('item traz nome, cor e imagem', $items[0]['name'] !== '' && $items[0]['colors'] === ['Red'] && $items[0]['image_path'] !== null);
Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[0], 'quantity' => '1', 'revision' => $rev($alpha)]);
check('adicionar a mesma carta soma à quantidade', Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id'])[0]['quantity'] === 3);
$over = Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[0], 'quantity' => '2', 'revision' => $rev($alpha)]);
check('passar do limite de 4 por ID é recusado com mensagem', !$over['ok'] && str_contains(implode(' ', $over['errors']), 'limite é 4'));
check('recusa não altera a quantidade nem a revisão', Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id'])[0]['quantity'] === 3 && $rev($alpha) === 5);
check('DON!! não entra no deck principal', !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $donCard, 'quantity' => '1', 'revision' => $rev($alpha)])['ok']);
check('Líder não entra no deck principal', !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $leaderRed, 'quantity' => '1', 'revision' => $rev($alpha)])['ok']);
check('carta inexistente é recusada', !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => 'ZZ99-999', 'quantity' => '1', 'revision' => $rev($alpha)])['ok']);
check('ID com caracteres perigosos é recusado', !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => "OP01-001'; DROP TABLE decks;--", 'quantity' => '1', 'revision' => $rev($alpha)])['ok']);
check('quantidade zero/negativa/texto recusada', !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[1], 'quantity' => '0', 'revision' => $rev($alpha)])['ok']
    && !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[1], 'quantity' => '-3', 'revision' => $rev($alpha)])['ok']
    && !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[1], 'quantity' => 'abc', 'revision' => $rev($alpha)])['ok']);
check('tabela decks continua existindo após a tentativa de injeção', (int)scalar($pdo, 'SELECT COUNT(*) FROM decks') >= 1);
if ($anyNumber !== false && $anyNumber !== null) {
    check('carta com exceção "qualquer quantidade" aceita mais de 4', Decks::addItem($pdo, $anaId, $alpha, ['card_id' => (string)$anyNumber, 'quantity' => '6', 'revision' => $rev($alpha)])['ok']);
    Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [(string)$anyNumber => '0'], 'revision' => $rev($alpha)]);
}

$before = Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id']);
$batchBad = Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[0] => '1', $redPool[1] => '9'], 'revision' => $rev($alpha)]);
check('lote com um item inválido é recusado', !$batchBad['ok']);
check('lote é atômico: nenhuma alteração parcial', Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id']) === $before);
check('lote não adiciona carta que não está no deck', !Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[5] => '2'], 'revision' => $rev($alpha)])['ok']);
check('lote com texto no lugar do número é recusado', !Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[0] => 'muitas'], 'revision' => $rev($alpha)])['ok']);
check('lote vazio é recusado', !Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [], 'revision' => $rev($alpha)])['ok']);
check('lote atualiza a quantidade', Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[0] => '4'], 'revision' => $rev($alpha)])['ok'] && Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id'])[0]['quantity'] === 4);
check('quantidade 0 remove a carta', Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[0] => '0'], 'revision' => $rev($alpha)])['ok'] && Decks::items($pdo, deck($pdo, $anaId, $alpha)['deck_id']) === []);
check('item com revisão antiga é recusado', Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[0], 'quantity' => '1', 'revision' => 1])['reason'] === 'conflict');

section('Decks — Líder e pendências (v_deck_issues)');
$codes = static fn (string $id): array => array_values(array_unique(array_column(Decks::issues($GLOBALS['pdo'], deck($GLOBALS['pdo'], $GLOBALS['anaId'], $id)['deck_id']), 'issue_code')));
check('deck vazio acusa 50 cartas (e não acusa o Líder)', in_array('main_count', $codes($alpha), true) && !in_array('leader_required', $codes($alpha), true));
check('trocar para Líder válido', Decks::setLeader($pdo, $anaId, $alpha, ['card_id' => $leaderBlue, 'revision' => $rev($alpha)])['ok'] && deck($pdo, $anaId, $alpha)['leader_card_id'] === $leaderBlue);
check('carta que não é Líder não vira Líder', !Decks::setLeader($pdo, $anaId, $alpha, ['card_id' => $redPool[0], 'revision' => $rev($alpha)])['ok']);
Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[0], 'quantity' => '1', 'revision' => $rev($alpha)]);
check('carta vermelha com Líder azul gera color_mismatch', in_array('color_mismatch', $codes($alpha), true));
Decks::setLeader($pdo, $anaId, $alpha, ['card_id' => $leaderRed, 'revision' => $rev($alpha)]);
check('voltando ao Líder vermelho a incompatibilidade some', !in_array('color_mismatch', $codes($alpha), true));
Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[0] => '0'], 'revision' => $rev($alpha)]);

section('Decks — "pronto" só com estrutura completa; rebaixa ao quebrar');
$meta = static fn (string $id, string $status): array => ['name' => 'Alpha Vermelho', 'format_code' => 'study', 'visibility' => 'private', 'status' => $status, 'don_quantity' => '10', 'revision' => $GLOBALS['rev']($id)];
$notReady = Decks::update($pdo, $anaId, $alpha, $meta($alpha, 'ready'));
check('incompleto não pode ser marcado como pronto', !$notReady['ok'] && str_contains(implode(' ', $notReady['errors']), '50 cartas'));
check('recusa mantém o rascunho e a revisão', deck($pdo, $anaId, $alpha)['status'] === 'draft');
$remaining = 50;
foreach (array_slice($redPool, 0, 13) as $cardId) {
    $q = min(4, $remaining);
    if ($q > 0) {
        check("adicionar {$cardId} ×{$q}", Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $cardId, 'quantity' => (string)$q, 'revision' => $rev($alpha)])['ok']);
        $remaining -= $q;
    }
}
check('deck chega a exatamente 50 cartas', Decks::totals($pdo, deck($pdo, $anaId, $alpha)['deck_id'])['main_quantity'] === 50);
check('51ª carta é recusada', !Decks::addItem($pdo, $anaId, $alpha, ['card_id' => $redPool[14], 'quantity' => '1', 'revision' => $rev($alpha)])['ok']);
check('estrutura completa não tem pendências', Decks::issues($pdo, deck($pdo, $anaId, $alpha)['deck_id']) === [], json_encode($codes($alpha)));
$ready = Decks::update($pdo, $anaId, $alpha, $meta($alpha, 'ready'));
check('deck completo pode ser marcado como pronto', $ready['ok'] && deck($pdo, $anaId, $alpha)['status'] === 'ready');
$down = Decks::updateQuantities($pdo, $anaId, $alpha, ['quantities' => [$redPool[0] => '3'], 'revision' => $rev($alpha)]);
check('alterar deck pronto que fica incompleto volta a rascunho com aviso', $down['ok'] && $down['notice'] !== null && deck($pdo, $anaId, $alpha)['status'] === 'draft');
$curve = Decks::costCurve($pdo, deck($pdo, $anaId, $alpha)['deck_id']);
check('curva de custo cobre 0–6+ e soma ≤ total', count($curve) === 7 && array_sum($curve) <= 49);
$totals = Decks::totals($pdo, deck($pdo, $anaId, $alpha)['deck_id']);
check('totais por tipo coerentes', $totals['main_quantity'] === 49 && $totals['character_quantity'] + $totals['event_quantity'] + $totals['stage_quantity'] === 49);

section('Decks — listagem, filtros, ordenação e paginação');
$mk = static function (string $name, array $extra = []) use ($pdo, $anaId): string {
    $r = Decks::create($pdo, $anaId, $extra + ['name' => $name, 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft']);
    if (!$r['ok']) {
        throw new RuntimeException('mk falhou: ' . implode(',', $r['errors']));
    }
    return (string)$r['public_id'];
};
$beta = $mk('Beta Azul', ['format_code' => 'standard', 'visibility' => 'public', 'leader_card_id' => $leaderBlue]);
$gamma = $mk('Gamma 100%_teste', ['visibility' => 'unlisted']);
Decks::update($pdo, $anaId, $gamma, ['name' => 'Gamma 100%_teste', 'format_code' => 'study', 'visibility' => 'unlisted', 'status' => 'archived', 'don_quantity' => '10', 'revision' => 1]);
$acao = $mk('Ação ñandú');
$names = static fn (array $f): array => array_column(Decks::list($GLOBALS['pdo'], $GLOBALS['anaId'], $f)['rows'], 'name');
check('sem filtro lista os 4 decks da Ana', Decks::list($pdo, $anaId, [])['total'] === 4);
check('filtro por nome (sem diferenciar caixa)', $names(['q' => 'beta']) === ['Beta Azul']);
check('filtro por nome com acento', $names(['q' => 'Ação']) === ['Ação ñandú']);
check('% no nome é literal, não curinga', $names(['q' => '%']) === ['Gamma 100%_teste']);
check('_ no nome é literal, não curinga', $names(['q' => '_']) === ['Gamma 100%_teste']);
check('filtro por formato', $names(['format' => 'standard']) === ['Beta Azul']);
check('filtro por situação', $names(['status' => 'archived']) === ['Gamma 100%_teste']);
check('filtro por visibilidade', $names(['visibility' => 'public']) === ['Beta Azul']);
check('filtro por cor do Líder (vermelho)', $names(['color' => 'Red']) === ['Alpha Vermelho']);
check('filtro por cor do Líder (azul)', $names(['color' => 'Blue']) === ['Beta Azul']);
check('filtros combinados (AND)', $names(['format' => 'study', 'status' => 'draft', 'color' => 'Red']) === ['Alpha Vermelho'] && $names(['format' => 'standard', 'color' => 'Red']) === []);
check('valores inválidos de filtro são ignorados', Decks::list($pdo, $anaId, ['status' => 'xyz', 'color' => 'Pink', 'visibility' => 'all'])['total'] === 4);
check('tentativa de injeção no filtro não quebra nem vaza', Decks::list($pdo, $anaId, ['q' => "' OR '1'='1"])['total'] === 0 && Decks::list($pdo, $anaId, ['format' => "x' OR 1=1 --"])['total'] === 4);
$byName = $names(['sort' => 'name']);
$pos = static fn (string $n): int|false => array_search($n, $GLOBALS['byName'], true);
// A posição de 'Ação' depende da collation (MySQL ai_ci a trata como 'Acao'); compara só as ASCII.
check('ordenação por nome (A–Z)', $pos('Alpha Vermelho') < $pos('Beta Azul') && $pos('Beta Azul') < $pos('Gamma 100%_teste'));
check('ordenação por mais cartas', $names(['sort' => 'cards'])[0] === 'Alpha Vermelho');
$betaId = deck($pdo, $anaId, $beta)['deck_id'];
$pdo->exec("UPDATE decks SET updated_at = '2099-01-01 00:00:00.000000' WHERE deck_id = {$betaId}");
check('ordenação padrão: mais recentemente atualizado primeiro', $names([])[0] === 'Beta Azul' && $names(['sort' => 'updated'])[0] === 'Beta Azul');
$pdo->exec("UPDATE decks SET updated_at = '2000-01-01 00:00:00.000000' WHERE deck_id = {$betaId}");
check('ordenação padrão reflete a nova data', $names([])[count($names([])) - 1] === 'Beta Azul');
$row = Decks::list($pdo, $anaId, ['q' => 'Alpha'])['rows'][0];
check('linha da listagem traz Líder, cores, imagem e total', $row['leader_card_id'] === $leaderRed && $row['leader_colors'] === ['Red'] && $row['leader_image'] !== null && (int)$row['main_quantity'] === 49);
for ($i = 1; $i <= 14; $i++) {
    $mk(sprintf('Paginado %02d', $i));
}
$p1 = Decks::list($pdo, $anaId, ['q' => 'Paginado', 'sort' => 'name'], 1, 5);
$p3 = Decks::list($pdo, $anaId, ['q' => 'Paginado', 'sort' => 'name'], 3, 5);
$p9 = Decks::list($pdo, $anaId, ['q' => 'Paginado', 'sort' => 'name'], 9, 5);
check('paginação: total e número de páginas', $p1['total'] === 14 && $p1['pages'] === 3 && count($p1['rows']) === 5);
check('paginação: última página tem o resto', count($p3['rows']) === 4 && $p3['rows'][0]['name'] === 'Paginado 11');
check('paginação: página além do fim é limitada', $p9['page'] === 3);
check('listAll percorre todas as páginas', count(Decks::listAll($pdo, $anaId, ['q' => 'Paginado'])) === 14);
check('decks de outro usuário não aparecem nos filtros', Decks::list($pdo, $bobId, ['q' => 'Paginado'])['total'] === 0);

section('Decks — excluir (lógico)');
check('excluir o próprio deck', Decks::delete($pdo, $anaId, $acao) === true);
check('some da busca e da listagem', Decks::find($pdo, $anaId, $acao) === null && !in_array('Ação ñandú', $names([]), true));
check('registro permanece com deleted_at (histórico)', scalar($pdo, 'SELECT deleted_at FROM decks WHERE public_id = ?', [$acao]) !== null);
check('excluir de novo falha', Decks::delete($pdo, $anaId, $acao) === false);
check('não é possível editar deck excluído', Decks::update($pdo, $anaId, $acao, ['name' => 'Zumbi', 'revision' => 1])['reason'] === 'not_found');
check('exclusão auditada', (int)scalar($pdo, "SELECT COUNT(*) FROM audit_events WHERE action='deck.deleted' AND entity_key = ?", [$acao]) === 1);
check('itens do deck excluído não aparecem em totais da listagem', Decks::list($pdo, $anaId, [])['total'] === 17);

// ====================================================================== BUSCA DE CARTAS
section('Busca de cartas');
$allNonDon = (int)scalar($pdo, "SELECT COUNT(*) FROM cards WHERE type_code <> 'DON'");
$s = Cards::search($pdo, []);
check('sem filtros: todas as cartas exceto DON!!', $s['total'] === $allNonDon && count($s['rows']) === 24);
check('resultado traz imagem e cores como lista', $s['rows'][0]['colors'] !== null && is_array($s['rows'][0]['colors']));
check('filtro por tipo', Cards::search($pdo, ['type' => 'LEADER'])['total'] === (int)scalar($pdo, "SELECT COUNT(*) FROM cards WHERE type_code='LEADER'"));
check('DON!! nunca aparece, nem pedindo', Cards::search($pdo, ['type' => 'DON'])['total'] === $allNonDon);
$z = Cards::search($pdo, ['q' => 'zoro'], 1, 96);
check('busca por nome (sem diferenciar caixa)', $z['total'] > 0 && count(array_filter($z['rows'], static fn ($r) => stripos($r['name'], 'zoro') === false)) === 0);
$ids = Cards::search($pdo, ['q' => 'OP01-02'], 1, 96);
check('busca por ID parcial', $ids['total'] > 5 && count(array_filter($ids['rows'], static fn ($r) => !str_contains($r['card_id'], 'OP01-02'))) === 0);
$red = Cards::search($pdo, ['color' => 'Red'], 1, 96);
check('filtro por cor', $red['total'] > 100 && count(array_filter($red['rows'], static fn ($r) => !in_array('Red', $r['colors'], true))) === 0);
$cheap = Cards::search($pdo, ['max_cost' => '2'], 1, 96);
check('filtro por custo máximo', $cheap['total'] > 50 && count(array_filter($cheap['rows'], static fn ($r) => $r['cost'] === null || (int)$r['cost'] > 2)) === 0);
$compat = Cards::search($pdo, ['compatible_with' => $leaderRed], 1, 96);
check('compatíveis com o Líder: só cores contidas nas dele', $compat['total'] > 50 && count(array_filter($compat['rows'], static fn ($r) => array_diff($r['colors'], ['Red']) !== [])) === 0);
check('filtros combinados (tipo + cor + custo)', Cards::search($pdo, ['type' => 'CHARACTER', 'color' => 'Blue', 'max_cost' => '3'])['total'] > 0);
check('cartas multicoloridas aparecem em cada uma das cores', Cards::search($pdo, ['color' => 'Green'])['total'] > 0);
$a = Cards::search($pdo, [], 1, 10);
$b = Cards::search($pdo, [], 2, 10);
check('paginação: páginas distintas', $a['rows'][0]['card_id'] !== $b['rows'][0]['card_id'] && $a['pages'] === (int)ceil($allNonDon / 10));
check('paginação: página além do fim é limitada', Cards::search($pdo, [], 99999, 10)['page'] === $a['pages']);
check('valores inválidos são ignorados', Cards::search($pdo, ['type' => 'X', 'color' => 'Pink', 'max_cost' => 'abc'])['total'] === $allNonDon);
check('injeção em q não vaza nem quebra', Cards::search($pdo, ['q' => "' OR 1=1 --"])['total'] === 0);
check('% e _ em q são literais', Cards::search($pdo, ['q' => '%'])['total'] < $allNonDon && Cards::search($pdo, ['q' => '_'])['total'] < $allNonDon);
check('cardImage aceita assets/cards/*.webp e as artes de exemplo em assets/*.png', cardImage('assets/cards/OP01-025.webp') === '/assets/cards/OP01-025.webp' && cardImage('assets/OP01-001.png') === '/assets/OP01-001.png');
check('cardImage recusa caminhos suspeitos', cardImage('../etc/passwd') === null && cardImage('assets/../router.php') === null && cardImage('assets/cards/../../x.png') === null
    && cardImage('https://evil.example/x.png') === null && cardImage('assets/x.svg') === null && cardImage('//evil/x.png') === null && cardImage(null) === null && cardImage('assets/.hidden.png') === null);
$withSample = Decks::create($pdo, $anaId, ['name' => 'Imagem de exemplo', 'leader_card_id' => 'OP01-001']);
check('Líder com arte de exemplo (OP01-001) resolve a imagem na listagem', $withSample['ok'] && cardImage(Decks::list($pdo, $anaId, ['q' => 'Imagem de exemplo'])['rows'][0]['leader_image']) === '/assets/OP01-001.png');
check('todo Líder do catálogo tem imagem aceita pelo validador', (int)scalar($pdo, "SELECT COUNT(*) FROM cards WHERE type_code='LEADER'") === count(array_filter(array_merge(Cards::search($pdo, ['type' => 'LEADER'], 1, 96)['rows'], Cards::search($pdo, ['type' => 'LEADER'], 2, 96)['rows']), static fn ($r) => cardImage($r['image_path']) !== null)));
check('leaders() lista Líderes com cores', count(Cards::leaders($pdo)) === (int)scalar($pdo, "SELECT COUNT(*) FROM cards WHERE type_code='LEADER'"));

// ====================================================================== PDF
section('PDF');
$formats = ['study' => 'Estudo / casual', 'standard' => 'Standard'];
$rowsPdf = [];
foreach (Decks::listAll($pdo, $anaId, []) as $r) {
    $rowsPdf[] = [(string)$r['name'], $formats[$r['format_code']] ?? '', Decks::STATUSES[$r['status']], Decks::VISIBILITIES[$r['visibility']], (string)$r['leader_card_id'], implode('/', $r['leader_colors']), $r['main_quantity'] . '/50', formatDate((string)$r['updated_at'])];
}
$pdf = new Pdf('Meus decks · teste', true);
$pdf->heading('Meus decks');
$pdf->note('Relatório de teste com acentuação: ação, coração, não, Líder, ñ.');
$pdf->table(['Nome', 'Formato', 'Situação', 'Visibilidade', 'Líder', 'Cor', 'Cartas', 'Atualizado em'], $rowsPdf, [3.2, 1.5, 1.1, 1.2, 3.4, 1.3, 0.9, 1.6], [6 => true]);
$bytes = $pdf->output();
$dir = getenv('ATLAS_TEST_OUT') ?: sys_get_temp_dir();
file_put_contents($dir . '/atlas-test-lista.pdf', $bytes);
check('PDF começa e termina corretamente', str_starts_with($bytes, '%PDF-1.4') && str_ends_with(trim($bytes), '%%EOF'));
check('xref aponta para os objetos', (function () use ($bytes): bool {
    if (preg_match('/startxref\s+(\d+)\s+%%EOF/', $bytes, $m) !== 1) {
        return false;
    }
    if (substr($bytes, (int)$m[1], 4) !== 'xref') {
        return false;
    }
    preg_match_all('/^(\d{10}) 00000 n $/m', $bytes, $offsets);
    foreach ($offsets[1] as $i => $offset) {
        if (!str_starts_with(substr($bytes, (int)$offset, 12), ($i + 1) . ' 0 obj')) {
            return false;
        }
    }
    return $offsets[1] !== [];
})());
$big = new Pdf('Grande', true);
$bigRows = [];
for ($i = 1; $i <= 150; $i++) {
    $bigRows[] = ["Deck número {$i} com um nome bastante comprido para forçar o truncamento da célula na tabela", 'Estudo', 'Rascunho', 'Privado', 'OP01-001 Roronoa Zoro', 'Vermelho', "{$i}/50", '01/11/2026 10:00'];
}
$big->heading('Meus decks');
$big->table(['Nome', 'Formato', 'Situação', 'Visibilidade', 'Líder', 'Cor', 'Cartas', 'Atualizado em'], $bigRows, [3.2, 1.5, 1.1, 1.2, 3.4, 1.3, 0.9, 1.6], [6 => true]);
$bigBytes = $big->output();
file_put_contents($dir . '/atlas-test-grande.pdf', $bigBytes);
preg_match('/\/Count (\d+)/', $bigBytes, $pc);
check('muitas linhas geram várias páginas', (int)($pc[1] ?? 0) >= 5, 'páginas: ' . ($pc[1] ?? '?'));
if (is_executable('/usr/bin/pdftotext')) {
    $text = (string)shell_exec('pdftotext -layout ' . escapeshellarg($dir . '/atlas-test-lista.pdf') . ' - 2>&1');
    check('pdftotext lê o PDF: título, acentos e rodapé', str_contains($text, 'Meus decks') && str_contains($text, 'ação, coração, não, Líder, ñ') && str_contains($text, 'Página 1 de 1'));
    check('PDF lista os decks da conta', str_contains($text, 'Alpha Vermelho') && str_contains($text, 'Beta Azul') && str_contains($text, 'Gamma 100%_teste'));
    $bigText = (string)shell_exec('pdftotext -layout ' . escapeshellarg($dir . '/atlas-test-grande.pdf') . ' - 2>&1');
    check('cabeçalho da tabela se repete em cada página', substr_count($bigText, 'Visibilidade') === (int)$pc[1]);
    check('numeração "Página X de N" correta', str_contains($bigText, 'Página ' . $pc[1] . ' de ' . $pc[1]) && str_contains($bigText, 'Página 1 de ' . $pc[1]));
    check('texto longo é truncado com reticências', str_contains($bigText, '...') && !str_contains($bigText, 'forçar o truncamento da célula na tabela'));
    check('todas as 150 linhas presentes', substr_count($bigText, 'OP01-001 Roronoa Zoro') === 150);
} else {
    echo "  (pdftotext indisponível: verificação de texto do PDF ignorada)\n";
}
$empty = new Pdf('Vazio');
$empty->heading('Sem dados');
$empty->table(['A', 'B'], [], [1, 1]);
check('tabela vazia gera PDF válido com mensagem', str_starts_with($empty->output(), '%PDF-1.4'));
$evil = new Pdf('Teste) (injeção \\ )');
$evil->heading('Nome com (parênteses) e \\barra e ")" fechando');
$evilBytes = $evil->output();
check('parênteses e barras no texto não corrompem o PDF', str_ends_with(trim($evilBytes), '%%EOF'));
check('caracteres fora do CP1252 (emoji/CJK) não quebram', str_ends_with(trim((new Pdf('x'))->output()), '%%EOF'));

// ====================================================================== resultado
if ($mysql && $pdo->inTransaction()) {
    $pdo->rollBack();
    echo "\n(MySQL: transação revertida; nada foi gravado.)\n";
}
$total = $passed + count($failed);
echo "\n" . str_repeat('=', 60) . "\n";
echo $failed === [] ? "OK — {$passed}/{$total} verificações passaram" . ($mysql ? ' (MySQL)' : ' (SQLite)') . ".\n" : count($failed) . " de {$total} verificações FALHARAM:\n - " . implode("\n - ", $failed) . "\n";
exit($failed === [] ? 0 : 1);
