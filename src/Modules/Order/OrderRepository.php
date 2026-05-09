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

    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('order', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    public function createItem(array $data): int
    {
        return $this->db->insert('order_item', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function updateStatus(int $id, string $status, ?array $projection = null): array
    {
        $this->db->update('order', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    public function delete(int $id): void
    {
        $this->db->delete('order', 'id = ? AND franchise_code = ?', [$id, $this->code]);
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
