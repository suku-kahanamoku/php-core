<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Database\Database;

/**
 * User – DB entity layer.
 */
class UserRepository
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /** Paginated list with optional filters. */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $role = null,
        string $sort = '',
    ): array {
        $orderBy = SQL_SORT($sort, 'u.created_at DESC', 'u');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['u.franchise_code = ?'];
        $params = [$this->code];

        if ($search) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $s       = '%' . $search . '%';
            array_push($params, $s, $s, $s);
        }
        if ($role) {
            $where[]  = 'r.name = ?';
            $params[] = $role;
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM user u'
            . ' JOIN role r ON r.id = u.role_id'
            . " WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email,
                    r.name AS role, r.id AS role_id,
                    u.phone, u.created_at, u.last_login_at
             FROM user u
             JOIN role r ON r.id = u.role_id
             WHERE {$whereStr}
             ORDER BY {$orderBy}
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

    /** Find single user by ID (with role join). */
    public function findById(int $id): ?array
    {
        $user = $this->db->fetchOne(
            'SELECT u.id, u.first_name, u.last_name, u.email,
                    r.name AS role, r.id AS role_id,
                    u.phone,
                    u.created_at, u.updated_at, u.last_login_at
             FROM user u
             JOIN role r ON r.id = u.role_id
             WHERE u.id = ? AND u.franchise_code = ?',
            [$id, $this->code],
        );

        return $user ?: null;
    }

    /** Find role_id by role name. */
    public function resolveRoleId(string $name): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM role WHERE franchise_code = ? AND name = ?',
            [$this->code, $name],
        );

        return $row ? (int) $row['id'] : null;
    }

    /** Check if email is taken. */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ? AND id != ?',
                [$this->code, $email, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ?',
                [$this->code, $email],
            );
        }

        return (bool) $row;
    }

    public function create(array $data): int
    {
        return $this->db->insert('user', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            'user',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete('user', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }
}
