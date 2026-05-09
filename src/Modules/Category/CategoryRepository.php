<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Category – DB entity layer.
 */
class CategoryRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = ['syscode', 'name', 'description', 'position', 'parent_id'];
    private const REL = [];

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    public function findAll(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'position ASC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['franchise_code = ?'];
        $params = [$this->code];

        $f = SQL_FILTER($filter);
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'c.' . implode(', c.', $sys);
        $ownSel  = $ownCols ? ', c.' . implode(', c.', $ownCols) : '';
        $select  = "{$sysSel}{$ownSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM category WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM category c WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys);
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
        $proj    = new Projection($projection);
        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $cols    = array_merge($sys, $ownCols);
        $select  = implode(', ', $cols);

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM category WHERE id = ? AND franchise_code = ?",
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        return $proj->apply($row, $sys);
    }

    public function findBySyscode(string $syscode): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM category WHERE syscode = ? AND franchise_code = ?',
            [$syscode, $this->code],
        );

        return $row ?: null;
    }

    public function hasProducts(int $id): bool
    {
        $row = $this->db->fetchOne(
            'SELECT pc.product_id FROM product_category pc
             INNER JOIN product p ON p.id = pc.product_id
             WHERE p.franchise_code = ? AND pc.category_id = ? LIMIT 1',
            [$this->code, $id],
        );

        return (bool) $row;
    }

    public function getProducts(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.sku, p.name, p.price FROM product p
             INNER JOIN product_category pc ON pc.product_id = p.id
             WHERE p.franchise_code = ? AND pc.category_id = ?',
            [$this->code, $id],
        );
    }

    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('category', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection);
    }

    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'category',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection);
    }

    public function delete(int $id): void
    {
        $this->db->delete('category', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }
}
