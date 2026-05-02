<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Database\Database;

/**
 * Invoice – DB entity layer.
 */
class InvoiceRepository
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
        string $sortBy = 'issued_at',
        string $sortDir = 'DESC',
    ): array {
        $allowed = ['issued_at', 'due_at', 'total_amount', 'status', 'invoice_number'];
        $sortBy  = in_array($sortBy, $allowed, true) ? $sortBy : 'issued_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['i.franchise_code = ?'];
        $params = [$this->code];

        if ($userId !== null) {
            $where[]  = 'i.user_id = ?';
            $params[] = $userId;
        }
        if ($status !== null) {
            $where[]  = 'i.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM invoice i WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT i.id, i.invoice_number, i.status, i.total_amount, i.currency,
                    i.issued_at, i.due_at, i.paid_at,
                    i.order_id, i.user_id, i.created_at, i.updated_at,
                    u.first_name, u.last_name, u.email
             FROM invoice i
             LEFT JOIN user u ON u.id = i.user_id
             WHERE {$whereStr}
             ORDER BY i.{$sortBy} {$sortDir}
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

    public function findById(int $id): ?array
    {
        $invoice = $this->db->fetchOne(
            'SELECT i.*, u.first_name, u.last_name, u.email,
                    ba.street AS bill_street, ba.city AS bill_city,
                    ba.zip AS bill_zip, ba.country AS bill_country
             FROM invoice i
             LEFT JOIN user u ON u.id = i.user_id
             LEFT JOIN address ba ON ba.id = i.billing_address_id
             WHERE i.id = ? AND i.franchise_code = ?',
            [$id, $this->code],
        );

        if (!$invoice) {
            return null;
        }

        $invoice['items'] = $this->db->fetchAll(
            'SELECT ii.*, p.name AS product_name, p.sku
             FROM invoice_item ii
             LEFT JOIN product p ON p.id = ii.product_id
             WHERE ii.invoice_id = ?',
            [$id],
        );

        return $invoice;
    }

    public function findByOrder(int $orderId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM invoice
             WHERE franchise_code = ? AND order_id = ?',
            [$this->code, $orderId],
        );

        return $row ?: null;
    }

    public function getOrder(int $orderId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM `order`
             WHERE id = ? AND franchise_code = ?',
            [$orderId, $this->code],
        );

        return $row ?: null;
    }

    public function getOrderItems(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT oi.*, p.name AS product_name FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id WHERE oi.order_id = ?',
            [$orderId],
        );
    }

    public function create(array $data): int
    {
        return $this->db->insert('invoice', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }

    public function createItem(array $data): int
    {
        return $this->db->insert('invoice_item', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function updateStatus(int $id, string $status): void
    {
        $set = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status === 'paid') {
            $set['paid_at'] = date('Y-m-d H:i:s');
        }
        $this->db->update(
            'invoice',
            $set,
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete('invoice', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    public function generateNumber(): string
    {
        $year = date('Y');
        $last = $this->db->fetchOne(
            'SELECT invoice_number FROM invoice
             WHERE franchise_code = ? AND invoice_number LIKE ?
             ORDER BY id DESC LIMIT 1',
            [$this->code, $year . '%'],
        );

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last['invoice_number']);
            $seq   = ((int) end($parts)) + 1;
        }

        return $year . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function getPdo(): \PDO
    {
        return $this->db->getPdo();
    }
}
