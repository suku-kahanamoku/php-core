<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class OrderController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** GET /orders */
    public function index(Request $request): void
    {
        Auth::require();

        $page   = max(1, (int) $request->get('page', 1));
        $limit  = min(100, max(1, (int) $request->get('limit', 20)));
        $offset = ($page - 1) * $limit;
        $status = $request->get('status');

        $where  = ['o.deleted_at IS NULL'];
        $params = [];

        // Non-admins see only their own orders
        if (!Auth::hasRole('admin')) {
            $where[]  = 'o.user_id = ?';
            $params[] = Auth::id();
        } elseif ($request->get('user_id')) {
            $where[]  = 'o.user_id = ?';
            $params[] = (int) $request->get('user_id');
        }

        if ($status) {
            $where[]  = 'o.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM `order` o WHERE {$whereStr}", $params
        )['cnt'] ?? 0;

        $items = $this->db->fetchAll(
            "SELECT o.id, o.order_number, o.status, o.total_amount, o.currency,
                    o.payment_method, o.user_id,
                    u.first_name, u.last_name, u.email,
                    o.created_at, o.updated_at
             FROM `order` o
             LEFT JOIN user u ON u.id = o.user_id
             WHERE {$whereStr}
             ORDER BY o.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        Response::success([
            'items'      => $items,
            'total'      => (int) $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    /** GET /orders/:id */
    public function show(Request $request, array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];

        $order = $this->db->fetchOne(
            "SELECT o.*, u.first_name, u.last_name, u.email,
                    a.street AS ship_street, a.city AS ship_city, a.zip AS ship_zip, a.country AS ship_country
             FROM `order` o
             LEFT JOIN user u ON u.id = o.user_id
             LEFT JOIN address a ON a.id = o.shipping_address_id
             WHERE o.id = ? AND o.deleted_at IS NULL",
            [$id]
        );

        if (!$order) {
            Response::notFound('Order not found');
        }

        if (!Auth::hasRole('admin') && (int) $order['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        // Load items
        $order['items'] = $this->db->fetchAll(
            "SELECT oi.*, p.name AS product_name, p.sku
             FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id
             WHERE oi.order_id = ?",
            [$id]
        );

        Response::success($order);
    }

    /** POST /orders */
    public function store(Request $request): void
    {
        Auth::require();

        $items = $request->get('items', []);
        if (empty($items) || !is_array($items)) {
            Response::validationError(['items' => 'At least one item required']);
        }

        $userId   = Auth::id();
        $currency = $request->get('currency', 'CZK');
        $db       = $this->db->getPdo();

        $db->beginTransaction();

        try {
            $totalAmount  = 0;
            $preparedItems = [];

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $qty       = (int) ($item['quantity']   ?? 1);

                if ($productId <= 0 || $qty <= 0) {
                    throw new \InvalidArgumentException("Invalid item: product_id={$productId}, quantity={$qty}");
                }

                $product = $this->db->fetchOne(
                    'SELECT id, name, price, stock_quantity FROM product WHERE id = ? AND status = ? AND deleted_at IS NULL',
                    [$productId, 'active']
                );

                if (!$product) {
                    throw new \RuntimeException("Product #{$productId} not found or inactive");
                }

                if ($product['stock_quantity'] < $qty) {
                    throw new \RuntimeException("Insufficient stock for product #{$productId}");
                }

                $lineTotal      = round($product['price'] * $qty, 2);
                $totalAmount   += $lineTotal;
                $preparedItems[] = [
                    'product_id'  => $productId,
                    'quantity'    => $qty,
                    'unit_price'  => $product['price'],
                    'total_price' => $lineTotal,
                ];
            }

            $orderId = $this->db->insert('order', [
                'order_number'       => $this->generateOrderNumber(),
                'user_id'            => $userId,
                'status'             => 'pending',
                'total_amount'       => $totalAmount,
                'currency'           => $currency,
                'payment_method'     => $request->get('payment_method', 'bank_transfer'),
                'note'               => $request->get('note', ''),
                'shipping_address_id'=> $request->get('shipping_address_id') ? (int) $request->get('shipping_address_id') : null,
                'billing_address_id' => $request->get('billing_address_id')  ? (int) $request->get('billing_address_id')  : null,
                'created_at'         => date('Y-m-d H:i:s'),
            ]);

            foreach ($preparedItems as $item) {
                $this->db->insert('order_item', array_merge($item, [
                    'order_id'   => $orderId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));

                // Decrement stock
                $this->db->update('product',
                    ['stock_quantity' => $this->db->fetchOne('SELECT stock_quantity FROM product WHERE id = ?', [$item['product_id']])['stock_quantity'] - $item['quantity']],
                    'id = ?',
                    [$item['product_id']]
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error($e->getMessage(), 422);
        }

        Response::created(['id' => $orderId ?? null, 'total_amount' => $totalAmount ?? 0], 'Order created');
    }

    /** PATCH /orders/:id/status */
    public function updateStatus(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id     = (int) $params['id'];
        $status = trim((string) $request->get('status', ''));

        if ($status === '') {
            Response::validationError(['status' => 'Required']);
        }

        $order = $this->db->fetchOne('SELECT id FROM `order` WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $this->db->update('order', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Response::success(null, 'Order status updated');
    }

    /** DELETE /orders/:id */
    public function destroy(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $order = $this->db->fetchOne('SELECT id FROM `order` WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $this->db->update('order', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        Response::success(null, 'Order deleted');
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }
}
