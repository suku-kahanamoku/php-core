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
        string $sortBy = 'is_default',
        string $sortDir = 'DESC',
    ): array {
        $allowed = ['is_default', 'type', 'created_at'];
        $sortBy  = in_array($sortBy, $allowed, true) ? $sortBy : 'is_default';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = ['franchise_code = ?', 'user_id = ?'];
        $params = [$this->code, $userId];

        if ($type !== null) {
            $where[]  = 'type = ?';
            $params[] = $type;
        }

        $whereStr = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT id, user_id, type, company, name,
                    street, city, zip, country, is_default, created_at, updated_at
             FROM address WHERE {$whereStr} ORDER BY {$sortBy} {$sortDir}",
            $params,
        );
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
