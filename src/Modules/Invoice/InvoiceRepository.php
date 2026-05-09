<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Invoice – DB entity layer.
 */
class InvoiceRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = ['invoice_number', 'status', 'total_amount', 'currency', 'issued_at', 'due_at', 'paid_at', 'order_id', 'user_id', 'billing_address_id'];
    private const REL = ['user'];

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /**
     * Vrati strankovany seznam faktur.
     *
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     invoice_number: string,
     *     status: string,
     *     total_amount: string,
     *     currency: string,
     *     issued_at: string,
     *     due_at: string|null,
     *     paid_at: string|null,
     *     order_id: int|null,
     *     user_id: int|null,
     *     billing_address_id: int|null,
     *     user?: array{first_name: string, last_name: string, email: string}
     *   }>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        ?int $userId = null,
        ?string $status = null,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'i.issued_at DESC', 'i');

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

        $f = SQL_FILTER($filter, 'i');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'i.' . implode(', i.', $sys);
        $ownSel  = $ownCols ? ', i.' . implode(', i.', $ownCols) : '';

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('user')) {
            $joinSql = 'LEFT JOIN user u ON u.id = i.user_id';
            $relSel  = ', u.first_name, u.last_name, u.email';
        }

        $select = "{$sysSel}{$ownSel}{$relSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM invoice i WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM invoice i {$joinSql}
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys, ['user' => ['fk' => 'user_id', 'nest' => ['first_name', 'last_name', 'email']]]);
        }
        unset($item);

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Najde fakturu dle ID vcetne polozek.
     *
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   invoice_number: string,
     *   status: string,
     *   total_amount: string,
     *   currency: string,
     *   issued_at: string,
     *   due_at: string|null,
     *   paid_at: string|null,
     *   order_id: int|null,
     *   user_id: int|null,
     *   billing_address_id: int|null,
     *   items: list<array{id: int, invoice_id: int, product_id: int, quantity: int, unit_price: string, vat_rate: string, product_name: string|null, sku: string|null}>,
     *   user?: array{first_name: string, last_name: string, email: string}
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj = new Projection($projection);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'i.' . implode(', i.', $sys);
        $ownSel  = $ownCols ? ', i.' . implode(', i.', $ownCols) : '';

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('user')) {
            $joinSql = 'LEFT JOIN user u ON u.id = i.user_id';
            $relSel  = ', u.first_name, u.last_name, u.email';
        }

        $select = "{$sysSel}{$ownSel}{$relSel}";

        $invoice = $this->db->fetchOne(
            "SELECT {$select} FROM invoice i {$joinSql}
             WHERE i.id = ? AND i.franchise_code = ?",
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

        return $proj->apply($invoice, $sys, ['user' => ['fk' => 'user_id', 'nest' => ['first_name', 'last_name', 'email']]]);
    }

    /**
     * Vraci ID zaznamu faktury pro danou objednavku, nebo null pokud neexistuje.
     *
     * @return array{id: int}|null
     */
    public function findByOrder(int $orderId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM invoice
             WHERE franchise_code = ? AND order_id = ?',
            [$this->code, $orderId],
        );

        return $row ?: null;
    }

    /**
     * Nacte raw zaznam objednavky pro ucely vystaveni faktury (interni pouziti).
     *
     * @return array<string, mixed>|null
     */
    public function getOrder(int $orderId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM `order`
             WHERE id = ? AND franchise_code = ?',
            [$orderId, $this->code],
        );

        return $row ?: null;
    }

    /**
     * Nacte polozky objednavky pro ucely vystaveni faktury (interni pouziti).
     *
     * @return list<array{id: int, order_id: int, product_id: int, quantity: int, unit_price: string, vat_rate: string, product_name: string|null}>
     */
    public function getOrderItems(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT oi.*, p.name AS product_name FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id WHERE oi.order_id = ?',
            [$orderId],
        );
    }

    /**
     * Vlozi novou fakturu a vrati vytvoreny zaznam vcetne polozek.
     *
     * @param  array<string, mixed> $data
     * @return array{id: int, invoice_number: string, status: string, total_amount: string, user_id: int|null, items: list<array<string, mixed>>}
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('invoice', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Vlozi polozku faktury a vrati jeji ID.
     *
     * @param  array<string, mixed> $data
     */
    public function createItem(array $data): int
    {
        return $this->db->insert('invoice_item', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Zmeni stav faktury (a nastavi paid_at pro status 'paid') a vrati aktualizovany zaznam.
     *
     * @return array{id: int, invoice_number: string, status: string, total_amount: string, user_id: int|null, items: list<array<string, mixed>>}
     */
    public function updateStatus(int $id, string $status, ?array $projection = null): array
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

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    public function delete(int $id): void
    {
        $this->db->delete('invoice', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    /**
     * Vygeneruje unikatni cislo faktury ve formatu YYYY-NNNNN.
     */
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
