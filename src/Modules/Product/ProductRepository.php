<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Database\Database;

/**
 * Product – DB entity layer.
 */
class ProductRepository
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
        string $sort = '',
        string $filter = '',
        ?string $categorySyscode = null,
    ): array {
        $orderBy = SQL_SORT($sort, 'p.created_at DESC', 'p');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['p.franchise_code = ?'];
        $params = [$this->code];

        if ($search) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)';
            $s       = '%' . $search . '%';
            array_push($params, $s, $s, $s);
        }
        if ($categoryId !== null) {
            $where[]  = 'EXISTS (SELECT 1 FROM product_category pc WHERE pc.product_id = p.id AND pc.category_id = ?)';
            $params[] = $categoryId;
        }
        if ($categorySyscode !== null) {
            $where[]  = 'EXISTS (SELECT 1 FROM product_category pc INNER JOIN category c ON c.id = pc.category_id WHERE pc.product_id = p.id AND c.syscode = ? AND c.franchise_code = p.franchise_code)';
            $params[] = $categorySyscode;
        }

        $f = SQL_FILTER($filter, 'p');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM product p WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT p.id, p.sku, p.name, p.description,
                    p.price, p.vat_rate, p.stock_quantity,
                    GROUP_CONCAT(pc.category_id ORDER BY pc.category_id) AS category_ids,
                    p.created_at, p.updated_at
             FROM product p
             LEFT JOIN product_category pc ON pc.product_id = p.id
             WHERE {$whereStr}
             GROUP BY p.id
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item['category_ids'] = $item['category_ids']
                ? array_map('intval', explode(',', $item['category_ids']))
                : [];
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

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM product WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        $categoryRows = $this->db->fetchAll(
            'SELECT pc.category_id, c.name AS category_name
             FROM product_category pc
             LEFT JOIN category c ON c.id = pc.category_id
             WHERE pc.product_id = ?',
            [$id],
        );

        $row['category_ids']   = array_map('intval', array_column($categoryRows, 'category_id'));
        $row['category_names'] = array_column($categoryRows, 'category_name');

        return $row;
    }

    public function syncCategories(int $productId, array $categoryIds): void
    {
        $this->db->delete('product_category', 'product_id = ?', [$productId]);

        foreach ($categoryIds as $catId) {
            $this->db->insert('product_category', [
                'product_id'  => $productId,
                'category_id' => (int) $catId,
            ]);
        }
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
            [$id, $this->code],
        );
    }

    public function delete(int $id): void
    {
        $this->db->delete('product_category', 'product_id = ?', [$id]);
        $this->db->delete('product', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    public function adjustStock(int $id, int $delta): int
    {
        $product = $this->db->fetchOne(
            'SELECT stock_quantity FROM product
             WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        if (!$product) {
            return -1; // not found signal
        }

        $newQty = $product['stock_quantity'] + $delta;
        $this->db->update(
            'product',
            ['stock_quantity' => $newQty, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $newQty;
    }

    public function generateSku(): string
    {
        return 'SKU-' . strtoupper(substr(uniqid(), -6));
    }
}
