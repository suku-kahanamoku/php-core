<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Database\Database;

/**
 * Category – DB entity layer.
 */
class CategoryRepository
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    public function findAll(string $sort = ''): array
    {
        $orderBy = SQL_SORT($sort, 'position ASC');

        return $this->db->fetchAll(
            "SELECT id, name, parent_id,
                    description, position, created_at, updated_at
             FROM category WHERE franchise_code = ?
             ORDER BY {$orderBy}",
            [$this->code],
        );
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM category WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $row ?: null;
    }

    public function hasProducts(int $id): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM product
             WHERE franchise_code = ? AND category_id = ? LIMIT 1',
            [$this->code, $id],
        );

        return (bool) $row;
    }

    public function getProducts(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT id, sku, name, price FROM product
             WHERE franchise_code = ? AND category_id = ?',
            [$this->code, $id],
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('category', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            'category',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete(
            'category',
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }
}
