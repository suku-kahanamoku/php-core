<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Core\Database;

/**
 * Category – DB entity layer.
 */
class Category
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    public function findAll(string $sortBy = 'sort_order', string $sortDir = 'ASC'): array
    {
        $allowed = ['sort_order', 'name', 'created_at'];
        $sortBy  = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        return $this->db->fetchAll(
            "SELECT id, name, slug, parent_id, description, sort_order, created_at, updated_at
             FROM category WHERE franchise_code = ?
             ORDER BY {$sortBy} {$sortDir}",
            [$this->code]
        );
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM category WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );

        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM category WHERE franchise_code = ? AND slug = ? AND id != ?',
                [$this->code, $slug, $excludeId]
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM category WHERE franchise_code = ? AND slug = ?',
                [$this->code, $slug]
            );
        }

        return (bool) $row;
    }

    public function hasProducts(int $id): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM product WHERE franchise_code = ? AND category_id = ? AND deleted_at IS NULL LIMIT 1',
            [$this->code, $id]
        );

        return (bool) $row;
    }

    public function getProducts(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT id, sku, name, price, status FROM product
             WHERE franchise_code = ? AND category_id = ? AND deleted_at IS NULL',
            [$this->code, $id]
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
            [$id, $this->code]
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete('category', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }
}
