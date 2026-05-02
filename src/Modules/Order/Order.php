<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Database\Database;

/**
 * Order – DB entity layer.
 */
class Order
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
        ?int $userId = null,
        ?string $status = null,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $allowed = ['created_at', 'total_amount', 'status', 'order_number'];
        $sortBy  = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['o.franchise_code = ?', 'o.deleted_at IS NULL'];
        $params = [$this->code];

        if ($userId !== null) {
            $where[]  = 'o.user_id = ?';
            $params[] = $userId;
        }
        if ($status !== null) {
            $where[]  = 'o.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `order` o WHERE {$whereStr}", $params
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT o.id, o.order_number, o.status, o.total_amount, o.currency,
                    o.payment_method, o.user_id,
                    u.first_name, u.last_name, u.email,
                    o.created_at, o.updated_at
             FROM `order` o
             LEFT JOIN user u ON u.id = o.user_id
             WHERE {$whereStr}
             ORDER BY o.{$sortBy} {$sortDir}
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
        $order = $this->db->fetchOne(
            "SELECT o.*, u.first_name, u.last_name, u.email,
                    a.street AS ship_street, a.city AS ship_city, a.zip AS ship_zip, a.country AS ship_country
             FROM `order` o
             LEFT JOIN user u ON u.id = o.user_id
             LEFT JOIN address a ON a.id = o.shipping_address_id
             WHERE o.id = ? AND o.franchise_code = ? AND o.deleted_at IS NULL",
            [$id, $this->code]
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->db->fetchAll(
            "SELECT oi.*, p.name AS product_name, p.sku
             FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id
             WHERE oi.order_id = ?",
            [$id]
        );

        return $order;
    }

    public function getProduct(int $productId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, price, stock_quantity FROM product
             WHERE id = ? AND franchise_code = ? AND status = ? AND deleted_at IS NULL',
            [$productId, $this->code, 'active']
        ) ?: null;
    }

    public function create(array $data): int
    {
        return $this->db->insert('order', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function createItem(array $data): int
    {
        return $this->db->insert('order_item', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function decreaseStock(int $productId, int $qty): void
    {
        $this->db->update(
            'product',
            ['stock_quantity' => $this->db->fetchOne(
                'SELECT stock_quantity FROM product WHERE id = ?', [$productId]
            )['stock_quantity'] - $qty],
            'id = ?',
            [$productId]
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->update('order', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    public function softDelete(int $id): void
    {
        $this->db->update('order', ['deleted_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    public function generateNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function getPdo(): \PDO
    {
        return $this->db->getPdo();
    }
}
