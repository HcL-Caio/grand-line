<?php
declare(strict_types=1);

namespace Atlas;

use PDO;

/**
 * CRUD de decks. Toda consulta filtra user_id (vindo da sessão, nunca do navegador)
 * e deleted_at IS NULL. Regras do README do banco:
 *  - rascunhos incompletos são permitidos;
 *  - 'ready' só passa com v_deck_issues vazio, na mesma transação;
 *  - cada alteração (inclusive de itens) incrementa revision, evitando sobrescrita.
 */
final class Decks
{
    public const STATUSES = ['draft' => 'Rascunho', 'ready' => 'Pronto', 'archived' => 'Arquivado'];
    public const VISIBILITIES = ['private' => 'Privado', 'unlisted' => 'Não listado', 'public' => 'Público'];
    public const SORTS = ['updated' => 'Mais recentes', 'name' => 'Nome (A–Z)', 'cards' => 'Mais cartas'];
    public const MAIN_SIZE = 50;

    private const SORT_SQL = [
        'updated' => 'd.updated_at DESC, d.deck_id DESC',
        'name' => 's.name ASC, d.deck_id ASC',
        'cards' => 's.main_quantity DESC, d.deck_id ASC',
    ];

    /** @return list<array{format_code:string,name_pt:string}> */
    public static function formats(PDO $pdo): array
    {
        return $pdo->query('SELECT format_code, name_pt FROM play_formats ORDER BY format_code')->fetchAll();
    }

    // ------------------------------------------------------------ listagem

    /**
     * Filtros: q, format, status, visibility, color (cor do Líder), sort.
     *
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public static function list(PDO $pdo, int $userId, array $filters, int $page = 1, int $perPage = 12): array
    {
        [$clause, $params] = self::filterClause($userId, $filters);
        $perPage = max(1, min(100, $perPage));

        $count = $pdo->prepare('SELECT COUNT(*) FROM v_deck_summary s JOIN decks d ON d.deck_id = s.deck_id' . $clause);
        self::bindAll($count, $params);
        $count->execute();
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $sort = self::SORT_SQL[(string)($filters['sort'] ?? '')] ?? self::SORT_SQL['updated'];
        $sql = "SELECT s.public_id, s.name, s.format_code, s.status, s.revision, s.leader_card_id,
                       s.main_quantity, s.don_quantity, d.visibility, d.updated_at, d.created_at,
                       lt.name AS leader_name,
                       (SELECT GROUP_CONCAT(cc.color_code) FROM card_colors cc WHERE cc.card_id = s.leader_card_id) AS leader_colors,
                       " . Cards::imageSql('s.leader_card_id') . " AS leader_image
                FROM v_deck_summary s
                JOIN decks d ON d.deck_id = s.deck_id
                LEFT JOIN card_texts lt ON lt.card_id = s.leader_card_id AND lt.language_code = 'en'"
            . $clause . " ORDER BY {$sort} LIMIT :page_limit OFFSET :page_offset";
        $statement = $pdo->prepare($sql);
        self::bindAll($statement, $params);
        $statement->bindValue(':page_limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':page_offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['leader_colors'] = $row['leader_colors'] === null ? [] : explode(',', (string)$row['leader_colors']);
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Linhas para o PDF: mesmos filtros e ordenação, todas as páginas (teto de segurança: 1000 decks).
     *
     * @return list<array<string,mixed>>
     */
    public static function listAll(PDO $pdo, int $userId, array $filters): array
    {
        $rows = [];
        $page = 1;
        do {
            $result = self::list($pdo, $userId, $filters, $page, 100);
            array_push($rows, ...$result['rows']);
            $page++;
        } while ($page <= $result['pages'] && $page <= 10);
        return $rows;
    }

    /** Vincula todos os parâmetros com o tipo correto (nunca depender de execute($array) com inteiros). */
    private static function bindAll(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /** @return array{0:string,1:array<string,int|string>} cláusula WHERE e parâmetros (inclui user_id) */
    private static function filterClause(int $userId, array $filters): array
    {
        $where = ['s.user_id = :user_id'];
        $params = ['user_id' => $userId];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $term = str_replace(['=', '%', '_'], ['==', '=%', '=_'], mb_substr($q, 0, 100));
            $where[] = "s.name LIKE :q ESCAPE '='";
            $params['q'] = '%' . $term . '%';
        }
        $format = (string)($filters['format'] ?? '');
        if ($format !== '' && preg_match('/^[a-z0-9_-]{1,32}$/D', $format) === 1) {
            $where[] = 's.format_code = :format';
            $params['format'] = $format;
        }
        $status = (string)($filters['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $where[] = 's.status = :status';
            $params['status'] = $status;
        }
        $visibility = (string)($filters['visibility'] ?? '');
        if (isset(self::VISIBILITIES[$visibility])) {
            $where[] = 'd.visibility = :visibility';
            $params['visibility'] = $visibility;
        }
        $color = (string)($filters['color'] ?? '');
        if (in_array($color, Cards::COLORS, true)) {
            $where[] = 'EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id = s.leader_card_id AND cc.color_code = :color)';
            $params['color'] = $color;
        }
        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    // -------------------------------------------------------------- leitura

    /** @return ?array<string,mixed> */
    public static function find(PDO $pdo, int $userId, string $publicId): ?array
    {
        if (preg_match('/^[0-9a-f-]{36}$/D', $publicId) !== 1) {
            return null;
        }
        $statement = $pdo->prepare(
            "SELECT d.deck_id, d.public_id, d.name, d.format_code, f.name_pt AS format_name, d.leader_card_id,
                    lt.name AS leader_name, d.don_quantity, d.visibility, d.status, d.description,
                    d.revision, d.created_at, d.updated_at,
                    (SELECT GROUP_CONCAT(cc.color_code) FROM card_colors cc WHERE cc.card_id = d.leader_card_id) AS leader_colors,
                    " . Cards::imageSql('d.leader_card_id') . " AS leader_image
             FROM decks d
             JOIN play_formats f ON f.format_code = d.format_code
             LEFT JOIN card_texts lt ON lt.card_id = d.leader_card_id AND lt.language_code = 'en'
             WHERE d.public_id = :public_id AND d.user_id = :user_id AND d.deleted_at IS NULL"
        );
        $statement->execute(['public_id' => $publicId, 'user_id' => $userId]);
        $deck = $statement->fetch();
        if (!$deck) {
            return null;
        }
        $deck['deck_id'] = (int)$deck['deck_id'];
        $deck['revision'] = (int)$deck['revision'];
        $deck['don_quantity'] = (int)$deck['don_quantity'];
        $deck['leader_colors'] = $deck['leader_colors'] === null ? [] : explode(',', (string)$deck['leader_colors']);
        return $deck;
    }

    /** @return list<array<string,mixed>> */
    public static function items(PDO $pdo, int $deckId): array
    {
        $statement = $pdo->prepare(
            "SELECT i.card_id, i.quantity, c.type_code, c.cost, c.power, c.counter_value, c.deck_copy_limit, t.name,
                    (SELECT GROUP_CONCAT(cc.color_code) FROM card_colors cc WHERE cc.card_id = i.card_id) AS colors,
                    " . Cards::imageSql('i.card_id') . " AS image_path
             FROM deck_items i
             JOIN cards c ON c.card_id = i.card_id
             JOIN card_texts t ON t.card_id = i.card_id AND t.language_code = 'en'
             WHERE i.deck_id = :deck_id
             ORDER BY c.type_code, (c.cost IS NULL), c.cost, i.card_id"
        );
        $statement->execute(['deck_id' => $deckId]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['quantity'] = (int)$row['quantity'];
            $row['colors'] = $row['colors'] === null ? [] : explode(',', (string)$row['colors']);
        }
        unset($row);
        return $rows;
    }

    /** @return array<string,int> */
    public static function totals(PDO $pdo, int $deckId): array
    {
        $statement = $pdo->prepare(
            'SELECT main_quantity, character_quantity, event_quantity, stage_quantity, counter_quantity
             FROM v_deck_summary WHERE deck_id = :deck_id'
        );
        $statement->execute(['deck_id' => $deckId]);
        $row = $statement->fetch() ?: [];
        return array_map('intval', $row + [
            'main_quantity' => 0, 'character_quantity' => 0, 'event_quantity' => 0, 'stage_quantity' => 0, 'counter_quantity' => 0,
        ]);
    }

    /** @return array<int,int> custo (6 = 6 ou mais) => quantidade */
    public static function costCurve(PDO $pdo, int $deckId): array
    {
        $statement = $pdo->prepare('SELECT cost_bucket, quantity FROM v_deck_cost_curve WHERE deck_id = :deck_id');
        $statement->execute(['deck_id' => $deckId]);
        $curve = array_fill(0, 7, 0);
        foreach ($statement->fetchAll() as $row) {
            $curve[(int)$row['cost_bucket']] = (int)$row['quantity'];
        }
        return $curve;
    }

    /** Pendências estruturais; não certificam legalidade competitiva. @return list<array{issue_code:string,card_id:?string,message:string}> */
    public static function issues(PDO $pdo, int $deckId): array
    {
        $statement = $pdo->prepare('SELECT issue_code, card_id, message FROM v_deck_issues WHERE deck_id = :deck_id ORDER BY issue_code, card_id');
        $statement->execute(['deck_id' => $deckId]);
        return $statement->fetchAll();
    }

    // ------------------------------------------------------------- criação

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,errors:list<string>,public_id:?string}
     */
    public static function create(PDO $pdo, int $userId, array $input): array
    {
        [$data, $errors] = self::cleanMeta($pdo, $input);
        if (($data['status'] ?? '') === 'ready') {
            $errors[] = 'Um deck novo começa como rascunho. Marque como pronto depois de montá-lo.';
        }
        $leader = trim((string)($input['leader_card_id'] ?? ''));
        if ($leader !== '') {
            $card = Cards::find($pdo, $leader);
            if ($card === null || $card['type_code'] !== 'LEADER') {
                $errors[] = 'O Líder escolhido não existe ou não é uma carta de Líder.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'public_id' => null];
        }

        $publicId = App::uuid();
        App::tx($pdo, function () use ($pdo, $userId, $data, $leader, $publicId): void {
            $pdo->prepare(
                "INSERT INTO decks (public_id, user_id, name, format_code, leader_card_id, don_quantity, visibility, status, description, revision)
                 VALUES (:public_id, :user_id, :name, :format, :leader, :don, :visibility, :status, :description, 1)"
            )->execute([
                'public_id' => $publicId,
                'user_id' => $userId,
                'name' => $data['name'],
                'format' => $data['format_code'],
                'leader' => $leader === '' ? null : $leader,
                'don' => $data['don_quantity'],
                'visibility' => $data['visibility'],
                'status' => $data['status'],
                'description' => $data['description'],
            ]);
            App::audit($pdo, $userId, 'deck.created', 'deck', $publicId, ['name' => $data['name']]);
        });
        return ['ok' => true, 'errors' => [], 'public_id' => $publicId];
    }

    // ----------------------------------------------------------- alteração

    /**
     * Metadados do deck. 'ready' exige v_deck_issues vazio.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,errors:list<string>,reason:?string,notice:?string}
     */
    public static function update(PDO $pdo, int $userId, string $publicId, array $input): array
    {
        [$data, $errors] = self::cleanMeta($pdo, $input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'reason' => 'invalid', 'notice' => null];
        }
        return self::mutate($pdo, $userId, $publicId, self::revisionOf($input), function (array $deck) use ($pdo, $data): void {
            $pdo->prepare(
                'UPDATE decks SET name = :name, format_code = :format, visibility = :visibility,
                        status = :status, description = :description, don_quantity = :don
                 WHERE deck_id = :deck_id'
            )->execute([
                'name' => $data['name'],
                'format' => $data['format_code'],
                'visibility' => $data['visibility'],
                'status' => $data['status'],
                'description' => $data['description'],
                'don' => $data['don_quantity'],
                'deck_id' => $deck['deck_id'],
            ]);
            if ($data['status'] === 'ready') {
                $issues = self::issues($pdo, (int)$deck['deck_id']);
                if ($issues !== []) {
                    throw new Abort(array_merge(
                        ['O deck ainda não pode ser marcado como pronto:'],
                        array_values(array_unique(array_column($issues, 'message')))
                    ));
                }
            }
        }, 'deck.updated');
    }

    /** Define o Líder (a arte escolhida é limpa: ela pertencia ao Líder anterior). */
    public static function setLeader(PDO $pdo, int $userId, string $publicId, array $input): array
    {
        $cardId = trim((string)($input['card_id'] ?? ''));
        return self::mutate($pdo, $userId, $publicId, self::revisionOf($input), function (array $deck) use ($pdo, $cardId): void {
            $card = Cards::find($pdo, $cardId);
            if ($card === null || $card['type_code'] !== 'LEADER') {
                throw new Abort(['Escolha uma carta da categoria Líder.']);
            }
            $pdo->prepare('UPDATE decks SET leader_card_id = :card, leader_printing_id = NULL WHERE deck_id = :deck_id')
                ->execute(['card' => $card['card_id'], 'deck_id' => $deck['deck_id']]);
        }, 'deck.leader_set', ['leader' => $cardId]);
    }

    /** Adiciona cópias de uma carta a partir da busca (soma à quantidade existente). */
    public static function addItem(PDO $pdo, int $userId, string $publicId, array $input): array
    {
        $cardId = trim((string)($input['card_id'] ?? ''));
        $add = filter_var($input['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        if ($add === false) {
            return ['ok' => false, 'errors' => ['Informe uma quantidade de 1 a 50.'], 'reason' => 'invalid', 'notice' => null];
        }
        return self::mutate($pdo, $userId, $publicId, self::revisionOf($input), function (array $deck) use ($pdo, $cardId, $add): void {
            $current = self::quantities($pdo, (int)$deck['deck_id']);
            self::applyQuantities($pdo, (int)$deck['deck_id'], [$cardId => ($current[$cardId] ?? 0) + $add]);
        }, 'deck.item_added', ['card' => $cardId]);
    }

    /**
     * Edição em lote das quantidades: quantities[ID] = n; 0 remove a carta.
     *
     * @param array<string,mixed> $input
     */
    public static function updateQuantities(PDO $pdo, int $userId, string $publicId, array $input): array
    {
        $raw = $input['quantities'] ?? null;
        if (!is_array($raw) || $raw === [] || count($raw) > 100) {
            return ['ok' => false, 'errors' => ['Nenhuma quantidade informada.'], 'reason' => 'invalid', 'notice' => null];
        }
        $changes = [];
        foreach ($raw as $cardId => $quantity) {
            $parsed = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 50]]);
            if ($parsed === false) {
                return ['ok' => false, 'errors' => ['As quantidades devem ser números de 0 a 50.'], 'reason' => 'invalid', 'notice' => null];
            }
            $changes[(string)$cardId] = $parsed;
        }
        return self::mutate($pdo, $userId, $publicId, self::revisionOf($input), function (array $deck) use ($pdo, $changes): void {
            $current = self::quantities($pdo, (int)$deck['deck_id']);
            // Só aceita IDs que já estão no deck: esta rota não adiciona cartas.
            foreach (array_keys($changes) as $cardId) {
                if (!isset($current[$cardId])) {
                    throw new Abort(["A carta {$cardId} não está neste deck."]);
                }
            }
            self::applyQuantities($pdo, (int)$deck['deck_id'], $changes);
        }, 'deck.items_updated');
    }

    // ------------------------------------------------------------- exclusão

    /** Exclusão lógica (deleted_at): o histórico e as FKs permanecem. */
    public static function delete(PDO $pdo, int $userId, string $publicId): bool
    {
        if (preg_match('/^[0-9a-f-]{36}$/D', $publicId) !== 1) {
            return false;
        }
        return (bool)App::tx($pdo, function () use ($pdo, $userId, $publicId): bool {
            $now = App::now();
            $statement = $pdo->prepare(
                'UPDATE decks SET deleted_at = :deleted, updated_at = :updated
                 WHERE public_id = :public_id AND user_id = :user_id AND deleted_at IS NULL'
            );
            $statement->execute(['deleted' => $now, 'updated' => $now, 'public_id' => $publicId, 'user_id' => $userId]);
            if ($statement->rowCount() !== 1) {
                return false;
            }
            App::audit($pdo, $userId, 'deck.deleted', 'deck', $publicId);
            return true;
        });
    }

    // ------------------------------------------------------------ internos

    private static function revisionOf(array $input): int
    {
        $revision = filter_var($input['revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $revision === false ? 0 : $revision;
    }

    /** @return array<string,int> */
    private static function quantities(PDO $pdo, int $deckId): array
    {
        $statement = $pdo->prepare('SELECT card_id, quantity FROM deck_items WHERE deck_id = :deck_id');
        $statement->execute(['deck_id' => $deckId]);
        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(string)$row['card_id']] = (int)$row['quantity'];
        }
        return $map;
    }

    /**
     * Aplica quantidades absolutas. Regras duras: carta existente, tipo do deck principal,
     * limite por ID (deck_copy_limit) e no máximo 50 no total. Cores e demais requisitos
     * ficam para v_deck_issues, que aceita rascunhos incompletos.
     *
     * @param array<string,int> $changes
     */
    private static function applyQuantities(PDO $pdo, int $deckId, array $changes): void
    {
        $current = self::quantities($pdo, $deckId);
        $errors = [];
        foreach ($changes as $cardId => $quantity) {
            $card = Cards::find($pdo, (string)$cardId);
            if ($card === null) {
                $errors[] = "Carta {$cardId} não encontrada.";
                continue;
            }
            if (!in_array($card['type_code'], Cards::MAIN_TYPES, true)) {
                $errors[] = "{$card['card_id']} ({$card['name']}) não entra no deck principal: apenas Personagens, Eventos e Cenários.";
                continue;
            }
            if ($quantity > $card['deck_copy_limit']) {
                $errors[] = "{$card['card_id']} ({$card['name']}): o limite é {$card['deck_copy_limit']} cópias por ID.";
            }
        }
        $final = $changes + $current; // '+' preserva as chaves; as alterações têm precedência
        if (array_sum($final) > self::MAIN_SIZE) {
            $errors[] = 'O deck principal tem no máximo ' . self::MAIN_SIZE . ' cartas.';
        }
        if ($errors !== []) {
            throw new Abort($errors);
        }

        foreach ($changes as $cardId => $quantity) {
            if ($quantity === 0) {
                $pdo->prepare('DELETE FROM deck_items WHERE deck_id = :deck_id AND card_id = :card_id')
                    ->execute(['deck_id' => $deckId, 'card_id' => $cardId]);
            } elseif (isset($current[$cardId])) {
                $pdo->prepare('UPDATE deck_items SET quantity = :quantity WHERE deck_id = :deck_id AND card_id = :card_id')
                    ->execute(['quantity' => $quantity, 'deck_id' => $deckId, 'card_id' => $cardId]);
            } else {
                $pdo->prepare('INSERT INTO deck_items (deck_id, card_id, quantity) VALUES (:deck_id, :card_id, :quantity)')
                    ->execute(['deck_id' => $deckId, 'card_id' => $cardId, 'quantity' => $quantity]);
            }
        }
    }

    /**
     * Transação padrão de escrita: trava a linha do deck do dono, confere revision,
     * executa a alteração, incrementa revision e rebaixa 'ready' a rascunho se a
     * alteração deixou o deck com pendências.
     *
     * @param callable(array<string,mixed>):void $change
     * @return array{ok:bool,errors:list<string>,reason:?string,notice:?string}
     */
    private static function mutate(PDO $pdo, int $userId, string $publicId, int $revision, callable $change, string $action, array $metadata = []): array
    {
        if (preg_match('/^[0-9a-f-]{36}$/D', $publicId) !== 1) {
            return ['ok' => false, 'errors' => ['Deck não encontrado.'], 'reason' => 'not_found', 'notice' => null];
        }
        try {
            $notice = App::tx($pdo, function () use ($pdo, $userId, $publicId, $revision, $change, $action, $metadata): ?string {
                $lock = $pdo->prepare(
                    'SELECT deck_id, revision, status FROM decks
                     WHERE public_id = :public_id AND user_id = :user_id AND deleted_at IS NULL' . App::forUpdate($pdo)
                );
                $lock->execute(['public_id' => $publicId, 'user_id' => $userId]);
                $deck = $lock->fetch();
                if (!$deck) {
                    throw new Abort(['Deck não encontrado.'], 'not_found');
                }
                if ((int)$deck['revision'] !== $revision) {
                    throw new Abort(['Este deck foi alterado em outra aba ou dispositivo. Os dados abaixo já são a versão atual: confira e refaça a alteração.'], 'conflict');
                }
                $deck['deck_id'] = (int)$deck['deck_id'];
                $change($deck);

                $bump = $pdo->prepare('UPDATE decks SET revision = revision + 1, updated_at = :now WHERE deck_id = :deck_id');
                $bump->execute(['now' => App::now(), 'deck_id' => $deck['deck_id']]);

                $notice = null;
                $status = $pdo->prepare('SELECT status FROM decks WHERE deck_id = :deck_id');
                $status->execute(['deck_id' => $deck['deck_id']]);
                if ($status->fetchColumn() === 'ready' && self::issues($pdo, $deck['deck_id']) !== []) {
                    $pdo->prepare("UPDATE decks SET status = 'draft' WHERE deck_id = :deck_id")->execute(['deck_id' => $deck['deck_id']]);
                    $notice = 'O deck voltou para Rascunho: a alteração deixou pendências na estrutura.';
                }
                App::audit($pdo, $userId, $action, 'deck', $publicId, $metadata);
                return $notice;
            });
            return ['ok' => true, 'errors' => [], 'reason' => null, 'notice' => $notice];
        } catch (Abort $abort) {
            return ['ok' => false, 'errors' => $abort->errors, 'reason' => $abort->reason, 'notice' => null];
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{0:array<string,mixed>,1:list<string>}
     */
    private static function cleanMeta(PDO $pdo, array $input): array
    {
        $errors = [];
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Informe um nome de até 100 caracteres.';
        }
        $formats = array_column(self::formats($pdo), 'format_code');
        $format = (string)($input['format_code'] ?? 'study');
        if (!in_array($format, $formats, true)) {
            $errors[] = 'Formato inválido.';
        }
        $visibility = (string)($input['visibility'] ?? 'private');
        if (!isset(self::VISIBILITIES[$visibility])) {
            $errors[] = 'Visibilidade inválida.';
        }
        $status = (string)($input['status'] ?? 'draft');
        if (!isset(self::STATUSES[$status])) {
            $errors[] = 'Situação inválida.';
        }
        $description = trim((string)($input['description'] ?? ''));
        if (mb_strlen($description) > 2000) {
            $errors[] = 'A descrição pode ter até 2000 caracteres.';
        }
        $don = filter_var($input['don_quantity'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10]]);
        if ($don === false) {
            $errors[] = 'A quantidade de DON!! deve ser de 0 a 10.';
        }
        return [[
            'name' => $name,
            'format_code' => $format,
            'visibility' => $visibility,
            'status' => $status,
            'description' => $description === '' ? null : $description,
            'don_quantity' => $don === false ? 10 : $don,
        ], $errors];
    }
}
