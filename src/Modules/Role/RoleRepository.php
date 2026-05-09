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

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /** Return all roles, optionally filtered and sorted. */
    public function findAll(int $page = 1, int $limit = 20, string $sort = '', string $filter = '', ?array $projection = null): array
    {
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

    /** Find single role by ID. */
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

    /** Count users assigned to this role. */
    public function countUsers(int $id): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM user
             WHERE role_id = ? AND franchise_code = ?',
            [$id, $this->code],
        )['cnt'];
    }

    /** Insert a new role and return the created row. */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('role', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection);
    }

    /** Update fields on an existing role and return the updated row. */
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

    /** Hard-delete a role. */
    public function delete(int $id): void
    {
        $this->db->delete('role', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    /** Check if a name is already taken (excluding a specific ID). */
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
}
