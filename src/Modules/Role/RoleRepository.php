<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Role – DB entity layer.
 * Handles all direct database operations for the `role` table.
 */
class RoleRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = ['name', 'label', 'position'];
    private const REL = [];

    /**
     * RoleRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
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
        $orderBy = SQL_SORT($sort, 'position ASC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['franchise_code = ?'];
        $params = [$this->code];

        $f = SQL_FILTER($filter);
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'r.' . implode(', r.', $sys);
        $ownSel  = $ownCols ? ', r.' . implode(', r.', $ownCols) : '';
        $select  = "{$sysSel}{$ownSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM role WHERE {$whereStr}",
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

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Find single role by ID.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   name: string,
     *   label: string,
     *   position: int
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj    = new Projection($projection);
        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'r.' . implode(', r.', $sys);
        $ownSel  = $ownCols ? ', r.' . implode(', r.', $ownCols) : '';
        $select  = "{$sysSel}{$ownSel}";

        $role = $this->db->fetchOne(
            "SELECT {$select} FROM role r WHERE r.id = ? AND r.franchise_code = ?",
            [$id, $this->code],
        );

        if (!$role) {
            return null;
        }

        return $proj->apply($role, $sys);
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
            'created_at'     => date('Y-m-d H:i:s'),
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
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
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
        return $this->db->delete(
            'role',
            'id = ? AND franchise_code = ?',
            [$id, $this->code]
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
                'SELECT id FROM role WHERE franchise_code = ? AND name = ? AND id != ?',
                [$this->code, $name, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM role WHERE franchise_code = ? AND name = ?',
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
            'SELECT id FROM `role` WHERE franchise_code = ? AND name = ?',
            [$this->code, $name],
        );

        return $row ? (int) $row['id'] : null;
    }
}
