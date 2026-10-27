<?php
declare(strict_types=1);

/** Exemplo de adaptação, não uma rota HTTP pronta. PHP 8.2+ com pdo_mysql. */
final class CatalogRepository
{
    public function __construct(private PDO $pdo) {}

    /** Filtros canônicos; impressões específicas usam v_catalog_printings. */
    public function search(array $filters = [], ?string $afterId = null, int $limit = 24): array
    {
        foreach (['name', 'type', 'color'] as $field) {
            if (isset($filters[$field]) && !is_string($filters[$field])) {
                throw new InvalidArgumentException('Filtro textual inválido.');
            }
        }
        $limit = max(1, min(96, $limit));
        $where = [];
        $params = [];
        if ($afterId !== null && $afterId !== '') {
            $where[] = 'v.card_id > :after_id';
            $params['after_id'] = $afterId;
        }
        if (isset($filters['name']) && $filters['name'] !== '') {
            // '=' é o caractere de escape: não depende de NO_BACKSLASH_ESCAPES.
            $term = str_replace(['=', '%', '_'], ['==', '=%', '=_'], (string)$filters['name']);
            $where[] = "v.name LIKE :name ESCAPE '='";
            $params['name'] = '%' . $term . '%';
        }
        if (!empty($filters['type'])) {
            $where[] = 'v.type_code = :type';
            $params['type'] = (string)$filters['type'];
        }
        if (!empty($filters['color'])) {
            $where[] = 'EXISTS (SELECT 1 FROM card_colors cc WHERE cc.card_id=v.card_id AND cc.color_code=:color)';
            $params['color'] = (string)$filters['color'];
        }
        if (isset($filters['max_cost'])) {
            if (filter_var($filters['max_cost'], FILTER_VALIDATE_INT) === false || (int)$filters['max_cost'] < 0) {
                throw new InvalidArgumentException('Custo máximo inválido.');
            }
            $where[] = 'v.cost <= :max_cost';
            $params['max_cost'] = (int)$filters['max_cost'];
        }
        $tags = $filters['tags'] ?? [];
        if (!is_array($tags) || count($tags) > 20) {
            throw new InvalidArgumentException('Lista de grupos inválida.');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException('Grupo inválido.');
            }
        }
        foreach (array_values(array_unique($tags)) as $index => $tag) {
            if (!is_string($tag) || !preg_match('/^[a-z0-9-]{1,40}$/D', $tag)) {
                throw new InvalidArgumentException('Grupo inválido.');
            }
            $key = 'tag_' . $index;
            $where[] = "EXISTS (SELECT 1 FROM card_discovery_tags dt WHERE dt.card_id=v.card_id AND dt.tag_code=:{$key})";
            $params[$key] = $tag;
        }
        $sql = 'SELECT v.* FROM v_catalog_cards v';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY v.card_id LIMIT :page_limit';
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':page_limit', $limit + 1, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        foreach ($rows as &$row) {
            $row['colors'] = json_decode($row['colors'], true, 512, JSON_THROW_ON_ERROR);
        }
        unset($row);
        return [
            'data' => $rows,
            'next_cursor' => $hasMore ? $rows[array_key_last($rows)]['card_id'] : null,
        ];
    }

    public function findPrintings(string $cardId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM v_catalog_printings WHERE card_id=:id ORDER BY printing_id');
        $statement->execute(['id' => $cardId]);
        return $statement->fetchAll();
    }
}
