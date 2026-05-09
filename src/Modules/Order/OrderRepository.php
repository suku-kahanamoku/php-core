<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Order – DB entity layer.
 */
class OrderRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = ['order_number', 'status', 'total_amount', 'currency', 'payment_method', 'shipping_type', 'shipping_cost', 'shipping_address_id', 'billing_address_id', 'user_id', 'note'];
    private const REL = ['user'];

    /**
     * OrderRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /**
     * Vrati strankovany seznam objednavek.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  int|null    $userId
     * @param  string|null $status
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     order_number: string,
     *     status: string,
     *     total_amount: string,
     *     currency: string,
     *     payment_method: string|null,
     *     shipping_type: string|null,
     *     shipping_cost: string|null,
     *     shipping_address_id: int|null,
     *     billing_address_id: int|null,
     *     user_id: int|null,
     *     note: string|null,
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
        $orderBy = SQL_SORT($sort, 'o.created_at DESC', 'o');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['o.franchise_code = ?'];
        $params = [$this->code];

        if ($userId !== null) {
            $where[]  = 'o.user_id = ?';
            $params[] = $userId;
        }
        if ($status !== null) {
            $where[]  = 'o.status = ?';
            $params[] = $status;
        }

        $f = SQL_FILTER($filter, 'o');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'o.' . implode(', o.', $sys);
        $ownSel  = $ownCols ? ', o.' . implode(', o.', $ownCols) : '';

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('user')) {
            $joinSql = 'LEFT JOIN user u ON u.id = o.user_id';
            $relSel  = ', u.first_name, u.last_name, u.email';
        }

        $select = "{$sysSel}{$ownSel}{$relSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `order` o WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM `order` o {$joinSql}
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
     * Najde objednavku dle ID vcetne polozek.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   order_number: string,
     *   status: string,
     *   total_amount: string,
     *   currency: string,
     *   payment_method: string|null,
     *   shipping_type: string|null,
     *   shipping_cost: string|null,
     *   shipping_address_id: int|null,
     *   billing_address_id: int|null,
     *   user_id: int|null,
     *   note: string|null,
     *   items: list<array{id: int, order_id: int, product_id: int, quantity: int, unit_price: string, vat_rate: string, product_name: string|null, sku: string|null}>,
     *   user?: array{first_name: string, last_name: string, email: string}
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj = new Projection($projection);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'o.' . implode(', o.', $sys);
        $ownSel  = $ownCols ? ', o.' . implode(', o.', $ownCols) : '';

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('user')) {
            $joinSql = 'LEFT JOIN user u ON u.id = o.user_id';
            $relSel  = ', u.first_name, u.last_name, u.email';
        }

        $select = "{$sysSel}{$ownSel}{$relSel}";

        $order = $this->db->fetchOne(
            "SELECT {$select} FROM `order` o {$joinSql}
             WHERE o.id = ? AND o.franchise_code = ?",
            [$id, $this->code],
        );

        if (!$order) {
            return null;
        }

        $order['items'] = $this->db->fetchAll(
            'SELECT oi.*, p.name AS product_name, p.sku
             FROM order_item oi
             LEFT JOIN product p ON p.id = oi.product_id
             WHERE oi.order_id = ?',
            [$id],
        );

        return $proj->apply($order, $sys, ['user' => ['fk' => 'user_id', 'nest' => ['first_name', 'last_name', 'email']]]);
    }

    /**
     * Vlozi novou objednavku a vrati vytvoreny zaznam vcetne polozek.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   order_number: string,
     *   status: string,
     *   total_amount: string,
     *   user_id: int|null,
     *   items: list<array<string, mixed>>
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('order', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Vlozi polozku objednavky a vrati jeji ID.
     *
     * @param  array<string, mixed> $data
     * @return int
     */
    public function createItem(array $data): int
    {
        return $this->db->insert('order_item', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Zmeni stav objednavky a vrati aktualizovany zaznam.
     *
     * @param  int        $id
     * @param  string     $status
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   order_number: string,
     *   status: string,
     *   total_amount: string,
     *   user_id: int|null,
     *   items: list<array<string, mixed>>
     * }
     */
    public function updateStatus(int $id, string $status, ?array $projection = null): array
    {
        $this->db->update('order', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze objednavku.
     *
     * @param  int $id
     * @return int  Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->delete('order', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    /**
     * Vygeneruje unikatni cislo objednavky ve formatu ORD-YYYYMMDD-XXXXX.
     *
     * @return string
     */
    public function generateNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    /**
     * Vrati nativni PDO instanci (pro transakce).
     *
     * @return \PDO
     */
    public function getPdo(): \PDO
    {
        return $this->db->getPdo();
    }
}
