<?php
declare(strict_types=1);

namespace Atlas;

use PDO;

/** Rotas HTTP. Cada ação valida entrada, chama Auth/Decks e renderiza ou redireciona. */
final class Controller
{
    private ?array $user = null;

    public function __construct(private readonly PDO $pdo)
    {
        $this->user = Auth::user($pdo);
    }

    // --------------------------------------------------------------- conta

    public function loginForm(): void
    {
        if ($this->user) {
            redirect(url('/decks'));
        }
        render('login', ['title' => 'Entrar', 'next' => $this->safeNext((string)($_GET['next'] ?? '')), 'error' => null, 'email' => ''], 'auth_layout');
    }

    public function login(): void
    {
        $email = (string)($_POST['email'] ?? '');
        $next = $this->safeNext((string)($_POST['next'] ?? ''));
        $result = Auth::login($this->pdo, $email, (string)($_POST['password'] ?? ''));
        if (!$result['ok']) {
            http_response_code(401);
            render('login', ['title' => 'Entrar', 'next' => $next, 'error' => $result['error'], 'email' => $email], 'auth_layout');
            return;
        }
        redirect($next);
    }

    public function registerForm(): void
    {
        if ($this->user) {
            redirect(url('/decks'));
        }
        render('register', ['title' => 'Criar conta', 'errors' => [], 'old' => ['name' => '', 'email' => '']], 'auth_layout');
    }

    public function register(): void
    {
        $result = Auth::register($this->pdo, $_POST);
        if (!$result['ok']) {
            http_response_code(422);
            render('register', [
                'title' => 'Criar conta',
                'errors' => $result['errors'],
                'old' => ['name' => (string)($_POST['name'] ?? ''), 'email' => (string)($_POST['email'] ?? '')],
            ], 'auth_layout');
            return;
        }
        Auth::start((int)$result['user_id']);
        App::flash('ok', 'Conta criada. Bem-vindo a bordo!');
        redirect(url('/decks'));
    }

    public function logout(): void
    {
        Auth::logout();
        App::startSession();
        App::flash('ok', 'Você saiu da sua conta.');
        redirect(url('/login'));
    }

    // ------------------------------------------------------------- listagem

    public function decksIndex(): void
    {
        $user = $this->requireUser();
        $filters = $this->deckFilters();
        $result = Decks::list($this->pdo, $user['user_id'], $filters, max(1, (int)($_GET['page'] ?? 1)));
        render('decks_index', [
            'title' => 'Meus decks',
            'nav' => 'decks',
            'user' => $user,
            'filters' => $filters,
            'result' => $result,
            'formats' => Decks::formats($this->pdo),
        ]);
    }

    public function decksPdf(): void
    {
        $user = $this->requireUser();
        $filters = $this->deckFilters();
        $rows = Decks::listAll($this->pdo, $user['user_id'], $filters);
        $formats = array_column(Decks::formats($this->pdo), 'name_pt', 'format_code');

        $pdf = new Pdf('Meus decks · Grand Line Atlas', true, 'Grand Line Atlas · ' . $user['display_name']);
        $pdf->heading('Meus decks');
        $pdf->note('Emitido em ' . formatDate(App::now()) . ' (horário de Brasília) para ' . $user['display_name'] . '. ' . count($rows) . ' deck(s).');
        $applied = $this->describeFilters($filters, $formats);
        $pdf->note($applied === '' ? 'Filtros: nenhum (todos os decks).' : 'Filtros aplicados: ' . $applied . '.');
        $pdf->space(4);
        $table = [];
        foreach ($rows as $row) {
            $table[] = [
                (string)$row['name'],
                $formats[$row['format_code']] ?? (string)$row['format_code'],
                Decks::STATUSES[$row['status']] ?? (string)$row['status'],
                Decks::VISIBILITIES[$row['visibility']] ?? (string)$row['visibility'],
                $row['leader_card_id'] === null ? '—' : $row['leader_card_id'] . ' ' . ($row['leader_name'] ?? ''),
                implode('/', array_map('colorLabel', $row['leader_colors'])) ?: '—',
                $row['main_quantity'] . '/' . Decks::MAIN_SIZE,
                formatDate((string)$row['updated_at']),
            ];
        }
        $pdf->table(
            ['Nome', 'Formato', 'Situação', 'Visibilidade', 'Líder', 'Cor', 'Cartas', 'Atualizado em'],
            $table,
            [3.2, 1.5, 1.1, 1.2, 3.4, 1.3, 0.9, 1.6],
            [6 => true]
        );
        $pdf->note('A situação "Pronto" indica estrutura básica válida (50 cartas, 10 DON!!, limite por ID e cores do Líder). Não certifica legalidade competitiva.', 8);
        $this->sendPdf($pdf, 'meus-decks.pdf');
    }

    public function deckPdf(string $publicId): void
    {
        $user = $this->requireUser();
        $deck = $this->requireDeck($user, $publicId);
        $items = Decks::items($this->pdo, $deck['deck_id']);
        $totals = Decks::totals($this->pdo, $deck['deck_id']);
        $issues = Decks::issues($this->pdo, $deck['deck_id']);

        $pdf = new Pdf($deck['name'] . ' · Grand Line Atlas', false, 'Grand Line Atlas · ' . $user['display_name']);
        $pdf->heading((string)$deck['name']);
        $pdf->note(($deck['format_name'] ?? '') . ' · ' . (Decks::STATUSES[$deck['status']] ?? '') . ' · Atualizado em ' . formatDate((string)$deck['updated_at']));
        $pdf->note('Líder: ' . ($deck['leader_card_id'] === null ? 'não escolhido' : $deck['leader_card_id'] . ' ' . ($deck['leader_name'] ?? '')) . ' · DON!!: ' . $deck['don_quantity']);
        $pdf->note("Deck principal: {$totals['main_quantity']}/" . Decks::MAIN_SIZE . " · Personagens {$totals['character_quantity']} · Eventos {$totals['event_quantity']} · Cenários {$totals['stage_quantity']} · Com Counter {$totals['counter_quantity']}");
        $pdf->space(6);
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [(string)$item['quantity'] . 'x', (string)$item['card_id'], (string)$item['name'], typeLabel((string)$item['type_code']), $item['cost'] === null ? '—' : (string)$item['cost'], implode('/', array_map('colorLabel', $item['colors'])) ?: '—'];
        }
        $pdf->table(['Qtd.', 'ID', 'Carta', 'Tipo', 'Custo', 'Cor'], $rows, [0.7, 1.4, 4.2, 1.5, 0.8, 1.5], [0 => true, 4 => true]);
        if ($issues !== []) {
            $pdf->note('Pendências: ' . implode(' ', array_values(array_unique(array_column($issues, 'message')))), 8);
        }
        $this->sendPdf($pdf, 'deck-' . substr($publicId, 0, 8) . '.pdf');
    }

    // ---------------------------------------------------------------- CRUD

    public function deckNew(): void
    {
        $user = $this->requireUser();
        render('deck_form', [
            'title' => 'Novo deck', 'nav' => 'decks', 'user' => $user, 'mode' => 'create', 'deck' => null,
            'errors' => [], 'old' => $this->metaDefaults(), 'formats' => Decks::formats($this->pdo), 'leaders' => Cards::leaders($this->pdo),
        ]);
    }

    public function deckCreate(): void
    {
        $user = $this->requireUser();
        $result = Decks::create($this->pdo, $user['user_id'], $_POST);
        if (!$result['ok']) {
            http_response_code(422);
            render('deck_form', [
                'title' => 'Novo deck', 'nav' => 'decks', 'user' => $user, 'mode' => 'create', 'deck' => null,
                'errors' => $result['errors'], 'old' => $this->oldMeta($_POST), 'formats' => Decks::formats($this->pdo), 'leaders' => Cards::leaders($this->pdo),
            ]);
            return;
        }
        App::flash('ok', 'Deck criado. Agora escolha as cartas.');
        redirect(url('/decks/' . $result['public_id'] . '/editar'));
    }

    public function deckShow(string $publicId): void
    {
        $user = $this->requireUser();
        $deck = $this->requireDeck($user, $publicId);
        render('deck_show', [
            'title' => (string)$deck['name'], 'nav' => 'decks', 'user' => $user, 'deck' => $deck,
            'items' => Decks::items($this->pdo, $deck['deck_id']),
            'totals' => Decks::totals($this->pdo, $deck['deck_id']),
            'curve' => Decks::costCurve($this->pdo, $deck['deck_id']),
            'issues' => Decks::issues($this->pdo, $deck['deck_id']),
        ]);
    }

    public function deckEdit(string $publicId, array $errors = [], ?array $old = null): void
    {
        $user = $this->requireUser();
        $deck = $this->requireDeck($user, $publicId);
        $search = [
            'q' => (string)($_GET['q'] ?? ''),
            'type' => (string)($_GET['type'] ?? ''),
            'color' => (string)($_GET['color'] ?? ''),
            'max_cost' => (string)($_GET['max_cost'] ?? ''),
            // Marcada por padrão quando há Líder; ao enviar a busca, vale o que o usuário escolheu.
            'compat' => isset($_GET['search'])
                ? (string)($_GET['compat'] ?? '')
                : ($deck['leader_card_id'] !== null ? '1' : ''),
        ];
        $searchRun = isset($_GET['search']) || $search['q'] !== '' || $search['type'] !== '' || $search['color'] !== '' || $search['max_cost'] !== '';
        $found = null;
        if ($searchRun) {
            $filters = $search;
            $filters['compatible_with'] = ($search['compat'] === '1' && $deck['leader_card_id'] !== null && ($search['type'] !== 'LEADER')) ? $deck['leader_card_id'] : '';
            $found = Cards::search($this->pdo, $filters, max(1, (int)($_GET['page'] ?? 1)), 12);
        }
        render('deck_form', [
            'title' => 'Editar ' . $deck['name'], 'nav' => 'decks', 'user' => $user, 'mode' => 'edit', 'deck' => $deck,
            'errors' => $errors, 'old' => $old ?? $this->oldMeta($deck), 'formats' => Decks::formats($this->pdo),
            'leaders' => Cards::leaders($this->pdo), 'items' => Decks::items($this->pdo, $deck['deck_id']),
            'totals' => Decks::totals($this->pdo, $deck['deck_id']), 'issues' => Decks::issues($this->pdo, $deck['deck_id']),
            'search' => $search, 'found' => $found,
        ]);
    }

    public function deckUpdate(string $publicId): void
    {
        $user = $this->requireUser();
        $this->afterMutation($publicId, Decks::update($this->pdo, $user['user_id'], $publicId, $_POST), 'Dados do deck salvos.', $_POST);
    }

    public function deckAddItem(string $publicId): void
    {
        $user = $this->requireUser();
        $result = Decks::addItem($this->pdo, $user['user_id'], $publicId, $_POST);
        $this->afterMutation($publicId, $result, 'Carta adicionada.', null, (string)($_POST['back'] ?? ''));
    }

    public function deckUpdateItems(string $publicId): void
    {
        $user = $this->requireUser();
        $this->afterMutation($publicId, Decks::updateQuantities($this->pdo, $user['user_id'], $publicId, $_POST), 'Quantidades atualizadas.');
    }

    public function deckSetLeader(string $publicId): void
    {
        $user = $this->requireUser();
        $this->afterMutation($publicId, Decks::setLeader($this->pdo, $user['user_id'], $publicId, $_POST), 'Líder definido.', null, (string)($_POST['back'] ?? ''));
    }

    public function deckDelete(string $publicId): void
    {
        $user = $this->requireUser();
        if (Decks::delete($this->pdo, $user['user_id'], $publicId)) {
            App::flash('ok', 'Deck excluído.');
            redirect(url('/decks'));
        }
        $this->notFound();
    }

    // ------------------------------------------------------------- erros

    public function notFound(): never
    {
        http_response_code(404);
        render('error', ['title' => 'Não encontrado', 'user' => $this->user, 'code' => 404, 'message' => 'Não encontramos esta página ou ela não pertence à sua conta.']);
        exit;
    }

    // ----------------------------------------------------------- internos

    /** @return array{user_id:int,public_id:string,display_name:string,email:string,role_code:string} */
    private function requireUser(): array
    {
        if ($this->user === null) {
            $next = $_SERVER['REQUEST_METHOD'] === 'GET' ? (string)($_SERVER['REQUEST_URI'] ?? '') : '';
            redirect(url('/login', ['next' => $next]));
        }
        return $this->user;
    }

    /** 404 tanto para inexistente quanto para deck de outro usuário (não revela existência). */
    private function requireDeck(array $user, string $publicId): array
    {
        $deck = Decks::find($this->pdo, $user['user_id'], $publicId);
        if ($deck === null) {
            $this->notFound();
        }
        return $deck;
    }

    /** Pós-escrita: sucesso => mensagem e volta ao editor; falha => mensagens e volta ao editor. */
    private function afterMutation(string $publicId, array $result, string $success, ?array $old = null, string $back = ''): void
    {
        if (!$result['ok'] && $result['reason'] === 'not_found') {
            $this->notFound();
        }
        $target = url('/decks/' . $publicId . '/editar');
        if (str_starts_with($back, '?') && preg_match('/^\?[A-Za-z0-9_=&%.+-]*$/D', $back) === 1) {
            $target .= $back . '#busca'; // volta à mesma busca, na mesma posição da página
        }
        if ($result['ok']) {
            App::flash('ok', $success);
            if ($result['notice'] !== null) {
                App::flash('warn', $result['notice']);
            }
            redirect($target);
        }
        if ($result['reason'] === 'invalid' && $old !== null) {
            // Reexibe o formulário com o que o usuário digitou.
            http_response_code(422);
            $_GET = [];
            $this->deckEdit($publicId, $result['errors'], $this->oldMeta($old + ['revision' => $old['revision'] ?? 0]));
            return;
        }
        foreach ($result['errors'] as $message) {
            App::flash('error', $message);
        }
        redirect($target);
    }

    private function sendPdf(Pdf $pdf, string $filename): never
    {
        $body = $pdf->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: private, no-store');
        echo $body;
        exit;
    }

    /** @return array<string,string> */
    private function deckFilters(): array
    {
        $filters = [];
        foreach (['q', 'format', 'status', 'visibility', 'color', 'sort'] as $key) {
            $value = $_GET[$key] ?? '';
            $filters[$key] = is_string($value) ? trim($value) : '';
        }
        return $filters;
    }

    /** @param array<string,string> $formats */
    private function describeFilters(array $filters, array $formats): string
    {
        $parts = [];
        if ($filters['q'] !== '') {
            $parts[] = 'nome contém "' . $filters['q'] . '"';
        }
        if ($filters['format'] !== '') {
            $parts[] = 'formato ' . ($formats[$filters['format']] ?? $filters['format']);
        }
        if (isset(Decks::STATUSES[$filters['status']])) {
            $parts[] = 'situação ' . Decks::STATUSES[$filters['status']];
        }
        if (isset(Decks::VISIBILITIES[$filters['visibility']])) {
            $parts[] = 'visibilidade ' . Decks::VISIBILITIES[$filters['visibility']];
        }
        if (in_array($filters['color'], Cards::COLORS, true)) {
            $parts[] = 'Líder ' . colorLabel($filters['color']);
        }
        if (isset(Decks::SORTS[$filters['sort']])) {
            $parts[] = 'ordem: ' . Decks::SORTS[$filters['sort']];
        }
        return implode('; ', $parts);
    }

    /** @return array<string,mixed> */
    private function metaDefaults(): array
    {
        return ['name' => '', 'format_code' => 'study', 'visibility' => 'private', 'status' => 'draft', 'description' => '', 'don_quantity' => 10, 'leader_card_id' => '', 'revision' => 0];
    }

    /** @return array<string,mixed> */
    private function oldMeta(array $source): array
    {
        $defaults = $this->metaDefaults();
        $out = [];
        foreach ($defaults as $key => $default) {
            $out[$key] = $source[$key] ?? $default;
        }
        $out['description'] = (string)($out['description'] ?? '');
        return $out;
    }

    /** Só caminhos da própria aplicação; o resto volta para a lista de decks. */
    private function safeNext(string $next): string
    {
        if (
            str_starts_with($next, App::BASE . '/')
            && !str_starts_with($next, App::BASE . '//')
            && preg_match('/[\x00-\x1f\x7f\\\\]/', $next) !== 1
            && !str_contains($next, '..')
        ) {
            return $next;
        }
        return url('/decks');
    }
}
