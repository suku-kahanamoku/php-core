<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Request;
use App\Core\Response;

class InvoiceController
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->code = Franchise::code();
    }

    /** GET /invoices */
    public function list(Request $request): void
    {
        Auth::require();

        $page   = max(1, (int) $request->get('page', 1));
        $limit  = min(100, max(1, (int) $request->get('limit', 20)));
        $offset = ($page - 1) * $limit;
        $status = $request->get('status');

        $where  = ['i.franchise_code = ?', 'i.deleted_at IS NULL'];
        $params = [$this->code];

        if (!Auth::hasRole('admin')) {
            $where[]  = 'i.user_id = ?';
            $params[] = Auth::id();
        } elseif ($request->get('user_id')) {
            $where[]  = 'i.user_id = ?';
            $params[] = (int) $request->get('user_id');
        }

        if ($status) {
            $where[]  = 'i.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM invoice i WHERE {$whereStr}", $params
        )['cnt'] ?? 0;

        $items = $this->db->fetchAll(
            "SELECT i.id, i.invoice_number, i.status, i.total_amount, i.currency,
                    i.issued_at, i.due_at, i.paid_at,
                    i.order_id, i.user_id,
                    u.first_name, u.last_name, u.email
             FROM invoice i
             LEFT JOIN user u ON u.id = i.user_id
             WHERE {$whereStr}
             ORDER BY i.issued_at DESC
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

    /** GET /invoices/:id */
    public function get(Request $request, array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];

        $invoice = $this->db->fetchOne(
            "SELECT i.*, u.first_name, u.last_name, u.email,
                    ba.street AS bill_street, ba.city AS bill_city, ba.zip AS bill_zip, ba.country AS bill_country
             FROM invoice i
             LEFT JOIN user u ON u.id = i.user_id
             LEFT JOIN address ba ON ba.id = i.billing_address_id
             WHERE i.id = ? AND i.franchise_code = ? AND i.deleted_at IS NULL",
            [$id, $this->code]
        );

        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        if (!Auth::hasRole('admin') && (int) $invoice['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        $invoice['items'] = $this->db->fetchAll(
            "SELECT ii.*, p.name AS product_name, p.sku
             FROM invoice_item ii
             LEFT JOIN product p ON p.id = ii.product_id
             WHERE ii.invoice_id = ?",
            [$id]
        );

        Response::success($invoice);
    }

    /** POST /invoices */
    public function create(Request $request): void
    {
        Auth::requireRole('admin');

        $orderId = (int) $request->get('order_id', 0);
        if ($orderId <= 0) {
            Response::validationError(['order_id' => 'Required']);
        }

        $order = $this->db->fetchOne(
            'SELECT * FROM `order` WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$orderId, $this->code]
        );
        if (!$order) {
            Response::notFound('Order not found');
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM invoice WHERE franchise_code = ? AND order_id = ? AND deleted_at IS NULL',
            [$this->code, $orderId]
        );
        if ($existing) {
            Response::error('Invoice already exists for this order', 409);
        }

        $orderItems = $this->db->fetchAll(
            'SELECT oi.*, p.name AS product_name FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id WHERE oi.order_id = ?',
            [$orderId]
        );

        $dueAt    = $request->get('due_at') ?? date('Y-m-d', strtotime('+14 days'));
        $issuedAt = date('Y-m-d H:i:s');

        $db = $this->db->getPdo();
        $db->beginTransaction();

        try {
            $invoiceId = $this->db->insert('invoice', [
                'franchise_code'       => $this->code,
                'invoice_number'     => $this->generateInvoiceNumber(),
                'order_id'           => $orderId,
                'user_id'            => $order['user_id'],
                'status'             => 'issued',
                'total_amount'       => $order['total_amount'],
                'currency'           => $order['currency'],
                'issued_at'          => $issuedAt,
                'due_at'             => $dueAt,
                'billing_address_id' => $order['billing_address_id'] ?? null,
                'note'               => $request->get('note', ''),
                'created_at'         => $issuedAt,
            ]);

            foreach ($orderItems as $item) {
                $this->db->insert('invoice_item', [
                    'invoice_id'  => $invoiceId,
                    'product_id'  => $item['product_id'],
                    'description' => $item['product_name'] ?? '',
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'created_at'  => $issuedAt,
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error($e->getMessage(), 500);
        }

        Response::created(['id' => $invoiceId ?? null], 'Invoice created');
    }

    /** PATCH /invoices/:id/status */
    public function updateStatus(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id     = (int) $params['id'];
        $status = trim((string) $request->get('status', ''));

        if ($status === '') {
            Response::validationError(['status' => 'Required']);
        }

        $invoice = $this->db->fetchOne(
            'SELECT id FROM invoice WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        $set = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status === 'paid') {
            $set['paid_at'] = date('Y-m-d H:i:s');
        }

        $this->db->update('invoice', $set, 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Invoice status updated');
    }

    /** DELETE /invoices/:id */
    public function delete(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $invoice = $this->db->fetchOne(
            'SELECT id FROM invoice WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        $this->db->update('invoice', ['deleted_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Invoice deleted');
    }

    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $last = $this->db->fetchOne(
            "SELECT invoice_number FROM invoice
             WHERE franchise_code = ? AND invoice_number LIKE ?
             ORDER BY id DESC LIMIT 1",
            [$this->code, $year . '%']
        );

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last['invoice_number']);
            $seq   = ((int) end($parts)) + 1;
        }

        return $year . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
