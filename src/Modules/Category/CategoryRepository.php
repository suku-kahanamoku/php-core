<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Category – DB vrstva entity.
 */
class CategoryRepository extends BaseRepository
{
    /**
     * Konstruktor tridy CategoryRepository.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'category';
        $this->alias = 'c';
        $this->own   = ['syscode', 'name', 'description', 'position', 'parent_id'];
    }

    /**
     * Vrati strankovany seznam kategorii.
     *
     * @param  int        $page
     * @param  int        $limit
     * @param  string     $sort
     * @param  string     $filter
     * @param  array|null $projection
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     syscode: string,
     *     name: string,
     *     description: string|null,
     *     position: int,
     *     parent_id: int|null
     *   }>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'c.position ASC', 'c');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['c.franchise_code = ?'];
        $params = [$this->code];

        // Extrahuj 'deleted' z filtru (vychozi 0 = pouze aktivni).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'c.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'c');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);
        $select   = $this->buildSelect($proj);
        $sys      = $this->sys;

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM category c WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM category c WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys);
        }
        unset($item);

        return $this->resultList($items, $total, $page, $limit);
    }

    /**
     * Najde kategorii dle syscode.
     *
     * @param  string $syscode
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   syscode: string,
     *   name: string,
     *   description: string|null,
     *   position: int,
     *   parent_id: int|null
     * }|null
     */
    public function findBySyscode(string $syscode): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM category WHERE syscode = ? AND franchise_code = ? AND deleted = 0',
            [$syscode, $this->code],
        );

        return $row ?: null;
    }

    /**
     * Vlozi novou kategorii a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   syscode: string,
     *   name: string,
     *   description: string|null,
     *   position: int,
     *   parent_id: int|null
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('category', array_merge($data, [
            'franchise_code' => $this->code,
        ]));

        return $this->findById($id, $projection);
    }

    /**
     * Aktualizuje kategorii a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   syscode: string,
     *   name: string,
     *   description: string|null,
     *   position: int,
     *   parent_id: int|null
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'category',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection);
    }

    /**
     * Nacte IDs kategorii z junction tabulky pro danou entitu.
     *
     * @param  string $junctionTable   Nazev junction tabulky (napr. 'product_category')
     * @param  string $entityFkColumn  FK sloupec entity v junction tabulce (napr. 'product_id')
     * @param  int    $entityId
     * @return list<int>
     */
    public function findIdsByJunction(string $junctionTable, string $entityFkColumn, int $entityId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT j.category_id
             FROM {$junctionTable} j
             LEFT JOIN category c ON c.id = j.category_id AND c.deleted = 0
             WHERE j.{$entityFkColumn} = ?",
            [$entityId],
        );

        return array_map('intval', array_column($rows, 'category_id'));
    }

    /**
     * Nacte kategorie entity pres junction tabulku.
     * Vraci pole objektu [{id, syscode, name, description, position, parent_id}].
     *
     * @param  string $junctionTable
     * @param  string $entityFkColumn
     * @param  int    $entityId
     * @return list<array{id: int, syscode: string, name: string, description: string|null, position: int, parent_id: int|null}>
     */
    public function findByJunctionItem(string $junctionTable, string $entityFkColumn, int $entityId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT c.id, c.syscode, c.name, c.description, c.position, c.parent_id
             FROM {$junctionTable} j
             INNER JOIN category c ON c.id = j.category_id AND c.deleted = 0
             WHERE j.{$entityFkColumn} = ?
             ORDER BY c.position ASC",
            [$entityId],
        );

        return array_map(static fn($r) => [
            'id'          => (int) $r['id'],
            'syscode'     => $r['syscode'],
            'name'        => $r['name'],
            'description' => $r['description'],
            'position'    => (int) $r['position'],
            'parent_id'   => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
        ], $rows);
    }

    /**
     * Batch load kategorii pro vice entit pres junction tabulku.
     * Vraci mapu [entityId => [{id, syscode, name, ...}]].
     *
     * @param  string    $junctionTable
     * @param  string    $entityFkColumn
     * @param  list<int> $entityIds
     * @return array<int, list<array{id: int, syscode: string, name: string, description: string|null, position: int, parent_id: int|null}>>
     */
    public function findByJunctionList(string $junctionTable, string $entityFkColumn, array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entityIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT j.{$entityFkColumn} AS entity_id, c.id, c.syscode, c.name, c.description, c.position, c.parent_id
             FROM {$junctionTable} j
             INNER JOIN category c ON c.id = j.category_id AND c.deleted = 0
             WHERE j.{$entityFkColumn} IN ({$placeholders})
             ORDER BY c.position ASC",
            $entityIds,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['entity_id']][] = [
                'id'          => (int) $row['id'],
                'syscode'     => $row['syscode'],
                'name'        => $row['name'],
                'description' => $row['description'],
                'position'    => (int) $row['position'],
                'parent_id'   => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            ];
        }

        return $map;
    }
}
