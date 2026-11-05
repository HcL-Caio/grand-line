<?php
declare(strict_types=1);

namespace Atlas;

use PDO;

/** Consulta ao catálogo (somente leitura) para o editor de decks. Baseado em CatalogRepository.php. */
final class Cards
{
    /** Tipos aceitos no deck principal; Líder e DON!! têm zona própria. */
    public const MAIN_TYPES = ['CHARACTER', 'EVENT', 'STAGE'];
    public const SEARCH_TYPES = ['LEADER', 'CHARACTER', 'EVENT', 'STAGE'];
    public const COLORS = ['Red', 'Green', 'Blue', 'Purple', 'Black', 'Yellow'];

    private const IMAGE_SQL = "(SELECT a.local_path FROM printings p
        JOIN printing_assets a ON a.printing_id = p.printing_id AND a.asset_role = 'front'
        WHERE p.card_id = %s AND p.archived_at IS NULL ORDER BY p.printing_id LIMIT 1)";

    public static function imageSql(string $cardIdExpr): string
    {
        return sprintf(self::IMAGE_SQL, $cardIdExpr);
    }

    /** '=' é o caractere de escape: não depende de NO_BACKSLASH_ESCAPES. */
    private static function like(string $term): string
    {
        return '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $term) . '%';
    }

    /**
     * Filtros: q (nome ou ID), type, color, max_cost, compatible_with (ID de Líder).
     *
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public static function search(PDO $pdo, array $filters, int $page = 1, int $perPage = 24): array
    {
        $perPage = max(1, min(96, $perPage));
        $where = ["v.type_code <> 'DON'"];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(v.name LIKE :q_name ESCAPE '=' OR v.card_id LIKE :q_id ESCAPE '=')";
            $params['q_name'] = self::like(mb_substr($q, 0, 100));
            $params['q_id'] = self::like(mb_substr($q, 0, 32));
        }
        $type = (string)($filters['type'] ?? '');
        if (in_array($type, self::SEARCH_TYPES, true)) {
            $where[] = 'v.type_code = :type';
            $params['type'] = $type;
        }
        $color = (string)($filters['color'] ?? '');
        if (in_array($color, self::COLORS, true)) {
            $where[] = 'EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id = v.card_id AND cc.color_code = :color)';
            $params['color'] = $color;
        }
        $maxCost = $filters['max_cost'] ?? '';
        if ($maxCost !== '' && $maxCost !== null) {
            $cost = filter_var($maxCost, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
            if ($cost !== false) {
                $where[] = 'v.cost <= :max_cost';
                $params['max_cost'] = $cost;
            }
        }
        $leader = (string)($filters['compatible_with'] ?? '');
        if ($leader !== '' && preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $leader) === 1) {
            // Mesma regra de v_deck_issues: toda cor da carta precisa existir no Líder.
            $where[] = 'NOT EXISTS (SELECT 1 FROM card_colors cc2 WHERE cc2.card_id = v.card_id
                AND NOT EXISTS (SELECT 1 FROM card_colors lc WHERE lc.card_id = :leader AND lc.color_code = cc2.color_code))';
            $params['leader'] = $leader;
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        $count = $pdo->prepare('SELECT COUNT(*) FROM v_catalog_cards v' . $clause);
        foreach ($params as $key => $value) {
            $count->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $sql = 'SELECT v.card_id, v.type_code, v.name, v.cost, v.power, v.counter_value, v.life,
                       v.deck_copy_limit, v.is_preview, v.colors, ' . self::imageSql('v.card_id') . ' AS image_path
                FROM v_catalog_cards v' . $clause . ' ORDER BY v.card_id LIMIT :page_limit OFFSET :page_offset';
        $statement = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':page_limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':page_offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $decoded = json_decode((string)$row['colors'], true);
            $row['colors'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return ?array{card_id:string,type_code:string,deck_copy_limit:int,name:string} */
    public static function find(PDO $pdo, string $cardId): ?array
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $cardId) !== 1) {
            return null;
        }
        $statement = $pdo->prepare(
            "SELECT c.card_id, c.type_code, c.deck_copy_limit, t.name
             FROM cards c JOIN card_texts t ON t.card_id = c.card_id AND t.language_code = 'en'
             WHERE c.card_id = :id AND c.archived_at IS NULL"
        );
        $statement->execute(['id' => $cardId]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        $row['deck_copy_limit'] = (int)$row['deck_copy_limit'];
        return $row;
    }

    /** @return list<array{card_id:string,name:string,colors:list<string>}> */
    public static function leaders(PDO $pdo): array
    {
        $sql = "SELECT c.card_id, t.name, (SELECT GROUP_CONCAT(cc.color_code) FROM card_colors cc WHERE cc.card_id = c.card_id) AS colors
                FROM cards c JOIN card_texts t ON t.card_id = c.card_id AND t.language_code = 'en'
                WHERE c.type_code = 'LEADER' AND c.archived_at IS NULL ORDER BY c.card_id";
        $rows = $pdo->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['colors'] = $row['colors'] === null ? [] : explode(',', (string)$row['colors']);
        }
        unset($row);
        return $rows;
    }
}
