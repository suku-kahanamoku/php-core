<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\Database\Database;

/**
 * Address – DB entity layer.
 */
class AddressRepository
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /** All addresses for a given user. */
    public function findByUser(
        int $userId,
        ?string $type = null,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
    ): array {
        $orderBy = SQL_SORT($sort, 'is_default DESC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['franchise_code = ?', 'user_id = ?'];
        $params = [$this->code, $userId];

        if ($type !== null) {
            $where[]  = 'type = ?';
            $params[] = $type;
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM address WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT id, user_id, type, company, name,
                    street, city, zip, country, is_default, created_at, updated_at
             FROM address WHERE {$whereStr} ORDER BY {$orderBy}
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

    /** Find single address by ID. */
    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM address WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $row ?: null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('address', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            'address',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete('address', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    /** Clear is_default flag for all user addresses of a given type. */
    public function clearDefault(int $userId, string $type): void
    {
        $this->db->update(
            'address',
            ['is_default' => 0],
            'franchise_code = ? AND user_id = ? AND type = ?',
            [$this->code, $userId, $type],
        );
    }
}
