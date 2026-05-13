<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Role – DB entity layer.
 * Handles all direct database operations for the `role` table.
 */
class RoleRepository extends BaseRepository
{
    /**
     * RoleRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'role';
        $this->alias = 'r';
        $this->own   = ['name', 'label', 'position'];
    }

    /**
     * Return all roles, optionally filtered and sorted.
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
     *     name: string,
     *     label: string,
     *     position: int
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
        ?array $projection = null
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'r.position ASC', 'r');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['r.franchise_code = ?'];
        $params = [$this->code];

        // Extract 'deleted' from filter (default 0 = active only).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'r.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'r');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);
        $select   = $this->buildSelect($proj);
        $sys      = $this->sys;

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM role r WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM role r WHERE {$whereStr} ORDER BY {$orderBy}
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
     * Insert a new role and return the created row.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   name: string,
     *   label: string,
     *   position: int
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('role', array_merge($data, [
            'franchise_code' => $this->code,
        ]));

        return $this->findById($id, $projection);
    }

    /**
     * Update fields on an existing role and return the updated row.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   name: string,
     *   label: string,
     *   position: int
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'role',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection);
    }

    /**
     * Hard-delete a role.
     *
     * @param  int $id
     * @return int  Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->update(
            'role',
            ['deleted' => 1],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    /**
     * Check if a name is already taken (excluding a specific ID).
     *
     * @param  string   $name
     * @param  int|null $excludeId
     * @return bool
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM role WHERE franchise_code = ? AND name = ? AND id != ? AND deleted = 0',
                [$this->code, $name, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM role WHERE franchise_code = ? AND name = ? AND deleted = 0',
                [$this->code, $name],
            );
        }

        return (bool) $row;
    }

    /**
     * Vrati ID role dle nazvu nebo null pokud neexistuje.
     *
     * @param  string   $name
     * @return int|null
     */
    public function findIdByName(string $name): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM `role` WHERE franchise_code = ? AND name = ? AND deleted = 0',
            [$this->code, $name],
        );

        return $row ? (int) $row['id'] : null;
    }
}
