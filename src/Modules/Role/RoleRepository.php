<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\Database\Database;

/**
 * Role – DB entity layer.
 * Handles all direct database operations for the `role` table.
 */
class RoleRepository
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /** Return all roles, optionally filtered and sorted. */
    public function findAll(int $page = 1, int $limit = 20, string $sort = '', string $filter = ''): array
    {
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

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM role WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT id, name, label, position, created_at, updated_at
             FROM role WHERE {$whereStr} ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /** Find single role by ID. */
    public function findById(int $id): ?array
    {
        $role = $this->db->fetchOne(
            'SELECT id, name, label, position, created_at, updated_at
             FROM role WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $role ?: null;
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

    /** Insert a new role and return the new ID. */
    public function create(array $data): int
    {
        return $this->db->insert('role', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    /** Update fields on an existing role. */
    public function update(int $id, array $data): void
    {
        $this->db->update(
            'role',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
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
