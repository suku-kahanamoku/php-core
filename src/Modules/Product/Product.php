<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Database\Database;

/**
 * Product – DB entity layer.
 */
class Product
{
    private Database $db;
    private string   $code;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    public function findAll(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?int $categoryId = null,
        ?string $status = null,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $allowed = ['created_at', 'name', 'price', 'sku', 'stock_quantity', 'status'];
        $sortBy  = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['p.franchise_code = ?', 'p.deleted_at IS NULL'];
        $params = [$this->code];

        if ($search) {
            $where[]  = '(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)';
            $s = '%' . $search . '%';
            array_push($params, $s, $s, $s);
        }
        if ($categoryId !== null) {
            $where[]  = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($status !== null) {
            $where[]  = 'p.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM product p WHERE {$whereStr}", $params
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT p.id, p.sku, p.name, p.description, p.price, p.vat_rate, p.stock_quantity,
                    p.status, p.category_id, c.name AS category_name, p.created_at, p.updated_at
             FROM product p
             LEFT JOIN category c ON c.id = p.category_id
             WHERE {$whereStr}
             ORDER BY p.{$sortBy} {$sortDir}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT p.*, c.name AS category_name
             FROM product p
             LEFT JOIN category c ON c.id = p.category_id
             WHERE p.id = ? AND p.franchise_code = ? AND p.deleted_at IS NULL',
            [$id, $this->code]
        );

        return $row ?: null;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM product WHERE franchise_code = ? AND sku = ? AND id != ? AND deleted_at IS NULL',
                [$this->code, $sku, $excludeId]
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM product WHERE franchise_code = ? AND sku = ? AND deleted_at IS NULL',
                [$this->code, $sku]
            );
        }

        return (bool) $row;
    }

    public function create(array $data): int
    {
        return $this->db->insert('product', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function update(int $id, array $data): void
    {
        $this->db->update(
            'product',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->update('product', ['deleted_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    public function adjustStock(int $id, int $delta): int
    {
        $product = $this->db->fetchOne(
            'SELECT stock_quantity FROM product WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );

        if (!$product) {
            return -1; // not found signal
        }

        $newQty = $product['stock_quantity'] + $delta;
        $this->db->update('product',
            ['stock_quantity' => $newQty, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?', [$id, $this->code]
        );

        return $newQty;
    }

    public function generateSku(): string
    {
        return 'SKU-' . strtoupper(substr(uniqid(), -6));
    }
}
