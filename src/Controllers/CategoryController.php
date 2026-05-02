<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Request;
use App\Core\Response;

class CategoryController
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->code = Franchise::code();
    }

    /** GET /categories */
    public function list(Request $request): void
    {
        $items = $this->db->fetchAll(
            'SELECT id, name, slug, parent_id, description, sort_order, created_at
             FROM category WHERE franchise_code = ?
             ORDER BY sort_order ASC, name ASC',
            [$this->code]
        );

        Response::success($this->buildTree($items));
    }

    /** GET /categories/:id */
    public function get(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        $category = $this->db->fetchOne(
            'SELECT * FROM category WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$category) {
            Response::notFound('Category not found');
        }

        $products = $this->db->fetchAll(
            'SELECT id, sku, name, price, status FROM product
             WHERE franchise_code = ? AND category_id = ? AND deleted_at IS NULL',
            [$this->code, $id]
        );

        $category['products'] = $products;
        Response::success($category);
    }

    /** POST /categories */
    public function create(Request $request): void
    {
        Auth::requireRole('admin');

        $name = trim((string) $request->get('name', ''));
        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        $slug = $request->get('slug') ?? $this->toSlug($name);

        $id = $this->db->insert('category', [
            'franchise_code' => $this->code,
            'name'         => $name,
            'slug'         => $slug,
            'description'  => $request->get('description') ?? '',
            'parent_id'    => $request->get('parent_id') ? (int) $request->get('parent_id') : null,
            'sort_order'   => (int) ($request->get('sort_order') ?? 0),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Category created');
    }

    /** PATCH /categories/:id */
    public function update(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $category = $this->db->fetchOne(
            'SELECT id FROM category WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$category) {
            Response::notFound('Category not found');
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['name', 'slug', 'description'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = trim((string) $v);
        }
        if (($v = $request->get('parent_id')) !== null) {
            $set['parent_id'] = $v === '' ? null : (int) $v;
        }
        if (($v = $request->get('sort_order')) !== null) {
            $set['sort_order'] = (int) $v;
        }

        $this->db->update('category', $set, 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Category updated');
    }

    /** PUT /categories/:id */
    public function replace(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $category = $this->db->fetchOne(
            'SELECT id FROM category WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$category) {
            Response::notFound('Category not found');
        }

        $name = trim((string) $request->get('name', ''));
        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        $slug     = $request->get('slug') !== null ? trim((string) $request->get('slug')) : $this->toSlug($name);
        $parentId = $request->get('parent_id');

        $this->db->update('category', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => (string) ($request->get('description') ?? ''),
            'parent_id'   => $parentId !== null && $parentId !== '' ? (int) $parentId : null,
            'sort_order'  => (int) ($request->get('sort_order') ?? 0),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        Response::success(null, 'Category replaced');
    }

    /** DELETE /categories/:id */
    public function delete(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $category = $this->db->fetchOne(
            'SELECT id FROM category WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$category) {
            Response::notFound('Category not found');
        }

        $inUse = $this->db->fetchOne(
            'SELECT id FROM product WHERE franchise_code = ? AND category_id = ? AND deleted_at IS NULL LIMIT 1',
            [$this->code, $id]
        );
        if ($inUse) {
            Response::error('Category is in use by products', 409);
        }

        $this->db->delete('category', 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Category deleted');
    }

    private function buildTree(array $items, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($items as $item) {
            if ((int) ($item['parent_id'] ?? 0) === (int) ($parentId ?? 0)) {
                $children = $this->buildTree($items, (int) $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $branch[] = $item;
            }
        }
        return $branch;
    }

    private function toSlug(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    }
}
