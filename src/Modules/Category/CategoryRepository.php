<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Category – DB entity layer.
 */
class CategoryRepository extends BaseRepository
{
    /**
     * CategoryRepository constructor.
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

        // Extract 'deleted' from filter (default 0 = active only).
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

        return $this->paginationResult($items, $total, $page, $limit);
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
            'created_at'     => date('Y-m-d H:i:s'),
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
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection);
    }

    /**
     * Smaze kategorii.
     *
     * @param  int $id
     * @return int  Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->update(
            'category',
            ['deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }
}
