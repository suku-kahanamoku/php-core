<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Request;
use App\Core\Response;

class ProductController
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->code = Franchise::code();
    }

    /** GET /products */
    public function list(Request $request): void
    {
        $page       = max(1, (int) $request->get('page', 1));
        $limit      = min(100, max(1, (int) $request->get('limit', 20)));
        $offset     = ($page - 1) * $limit;
        $search     = $request->get('search');
        $categoryId = $request->get('category_id');
        $status     = $request->get('status');

        $where  = ['p.franchise_code = ?', 'p.deleted_at IS NULL'];
        $params = [$this->code];

        if ($search) {
            $where[]  = '(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)';
            $s = '%' . $search . '%';
            $params = [...$params, $s, $s, $s];
        }
        if ($categoryId) {
            $where[]  = 'p.category_id = ?';
            $params[] = (int) $categoryId;
        }
        if ($status) {
            $where[]  = 'p.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM product p WHERE {$whereStr}", $params
        )['cnt'] ?? 0;

        $items = $this->db->fetchAll(
            "SELECT p.id, p.sku, p.name, p.description, p.price, p.vat_rate, p.stock_quantity,
                    p.status, p.category_id, c.name AS category_name, p.created_at, p.updated_at
             FROM product p
             LEFT JOIN category c ON c.id = p.category_id
             WHERE {$whereStr}
             ORDER BY p.created_at DESC
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

    /** GET /products/:id */
    public function get(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        $product = $this->db->fetchOne(
            'SELECT p.*, c.name AS category_name
             FROM product p
             LEFT JOIN category c ON c.id = p.category_id
             WHERE p.id = ? AND p.franchise_code = ? AND p.deleted_at IS NULL',
            [$id, $this->code]
        );

        if (!$product) {
            Response::notFound('Product not found');
        }

        Response::success($product);
    }

    /** POST /products */
    public function create(Request $request): void
    {
        Auth::requireRole('admin');

        $errors = [];
        $name   = trim((string) $request->get('name', ''));
        $price  = $request->get('price');

        if ($name === '') $errors['name'] = 'Required';
        if ($price === null || !is_numeric($price) || (float) $price < 0) $errors['price'] = 'Valid price required';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $id = $this->db->insert('product', [
            'franchise_code'   => $this->code,
            'sku'            => $request->get('sku') ?? $this->generateSku(),
            'name'           => $name,
            'description'    => $request->get('description') ?? '',
            'price'          => (float) $price,
            'vat_rate'       => (float) ($request->get('vat_rate') ?? 21),
            'stock_quantity' => (int) ($request->get('stock_quantity') ?? 0),
            'category_id'    => $request->get('category_id') ? (int) $request->get('category_id') : null,
            'status'         => $request->get('status') ?? 'active',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Product created');
    }

    /** PATCH /products/:id */
    public function update(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $product = $this->db->fetchOne(
            'SELECT id FROM product WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );
        if (!$product) {
            Response::notFound('Product not found');
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['sku', 'name', 'description', 'status'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = trim((string) $v);
        }
        foreach (['price', 'vat_rate'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = (float) $v;
        }
        foreach (['stock_quantity', 'category_id'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = (int) $v;
        }

        if (count($set) > 1) {
            $this->db->update('product', $set, 'id = ? AND franchise_code = ?', [$id, $this->code]);
        }

        Response::success(null, 'Product updated');
    }

    /** PUT /products/:id */
    public function replace(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $product = $this->db->fetchOne(
            'SELECT id FROM product WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );
        if (!$product) {
            Response::notFound('Product not found');
        }

        $errors = [];
        $name  = trim((string) $request->get('name', ''));
        $sku   = trim((string) $request->get('sku',  ''));
        $price = $request->get('price');

        if ($name  === '') $errors['name']  = 'Required';
        if ($sku   === '') $errors['sku']   = 'Required';
        if ($price === null || !is_numeric($price) || (float) $price < 0) $errors['price'] = 'Required, must be >= 0';
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $this->db->update('product', [
            'name'           => $name,
            'sku'            => $sku,
            'price'          => (float) $price,
            'description'    => (string) ($request->get('description')    ?? ''),
            'vat_rate'       => $request->get('vat_rate')       !== null ? (float) $request->get('vat_rate')       : 21.0,
            'stock_quantity' => $request->get('stock_quantity') !== null ? (int)   $request->get('stock_quantity') : 0,
            'status'         => (string) ($request->get('status')         ?? 'active'),
            'category_id'    => $request->get('category_id')   !== null ? (int)   $request->get('category_id')   : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        Response::success(null, 'Product replaced');
    }

    /** DELETE /products/:id */
    public function delete(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $product = $this->db->fetchOne(
            'SELECT id FROM product WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );
        if (!$product) {
            Response::notFound('Product not found');
        }

        $this->db->update('product', ['deleted_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Product deleted');
    }

    /** PATCH /products/:id/stock */
    public function adjustStock(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id       = (int) $params['id'];
        $quantity = (int) $request->get('quantity', 0);

        $product = $this->db->fetchOne(
            'SELECT id, stock_quantity FROM product WHERE id = ? AND franchise_code = ? AND deleted_at IS NULL',
            [$id, $this->code]
        );
        if (!$product) {
            Response::notFound('Product not found');
        }

        $newQty = $product['stock_quantity'] + $quantity;
        if ($newQty < 0) {
            Response::error('Insufficient stock', 422);
        }

        $this->db->update('product',
            ['stock_quantity' => $newQty, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(['stock_quantity' => $newQty], 'Stock adjusted');
    }

    private function generateSku(): string
    {
        return 'SKU-' . strtoupper(substr(uniqid(), -6));
    }
}
