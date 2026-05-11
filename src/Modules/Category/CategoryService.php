<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Product\ProductRepository;
use App\Modules\Router\Response;

class CategoryService
{
    private CategoryRepository $category;
    private ProductRepository  $product;
    private Auth $auth;

    /**
     * CategoryService constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->category = new CategoryRepository($db, $franchiseCode);
        $this->product  = new ProductRepository($db, $franchiseCode);
        $this->auth     = $auth;
    }

    /**
     * Vrati strankovany seznam kategorii. Verejne dostupne.
     *
     * @param  int        $page
     * @param  int        $limit
     * @param  string     $sort
     * @param  string     $filter
     * @param  array|null $projection
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function list(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null
    ): array {
        return $this->category->findAll($page, $limit, $sort, $filter, $projection);
    }

    /**
     * Vrati kategorii dle ID vcetne seznamu prirazanych produktu (pole products).
     * Verejne dostupne. Pokud kategorie neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   name: string,
     *   products: list<array{id: int, sku: string, name: string, price: float}>
     * }
     */
    public function get(int $id, ?array $projection = null): array
    {
        $category = $this->category->findById($id, $projection);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $category['products'] = $this->product->findByCategoryId($id);
        return $category;
    }

    /**
     * Vytvori novou kategorii. Vyzaduje roli admin.
     * Povinna pole: name.
     *
     * @param  string               $name
     * @param  array<string, mixed> $input  description, parent_id, position
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(string $name, array $input, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        return $this->category->create([
            'syscode'     => $input['syscode'] ?? null,
            'name'        => $name,
            'description' => $input['description'] ?? null,
            'parent_id'   => isset($input['parent_id']) && $input['parent_id'] !== ''
                ? (int) $input['parent_id']
                : null,
            'position' => (int) ($input['position'] ?? 0),
        ], $projection);
    }

    /**
     * Castecna aktualizace kategorie (PATCH). Vyzaduje roli admin.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input  name, description, parent_id, position
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        if (!$this->category->findById($id)) {
            Response::notFound('Category not found');
        }

        $set = [];
        if (isset($input['name'])) {
            $set['name'] = trim((string) $input['name']);
        }
        if (isset($input['description'])) {
            $set['description'] = trim((string) $input['description']);
        }
        if (isset($input['position'])) {
            $set['position'] = (int) $input['position'];
        }
        if (array_key_exists('parent_id', $input)) {
            $isEmptyParent    = $input['parent_id'] === null || $input['parent_id'] === '';
            $set['parent_id'] = $isEmptyParent ? null : (int) $input['parent_id'];
        }

        if (!empty($set)) {
            $this->category->update($id, $set);
        }

        return $this->category->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada kategorie (PUT). Vyzaduje roli admin. Povinna pole: name.
     *
     * @param  int                  $id
     * @param  string               $name
     * @param  array<string, mixed> $input  syscode, description, parent_id, position
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function replace(
        int $id,
        string $name,
        array $input,
        ?array $projection = null
    ): array {
        $this->auth->requireRole('admin');

        if (!$this->category->findById($id)) {
            Response::notFound('Category not found');
        }

        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        $parentId = ($input['parent_id'] ?? null);
        $parentId = ($parentId !== null && $parentId !== '') ? (int) $parentId : null;

        $this->category->update($id, [
            'syscode'     => $input['syscode'] ?? null,
            'name'        => $name,
            'description' => (string) ($input['description'] ?? ''),
            'parent_id'   => $parentId,
            'position'    => (int) ($input['position'] ?? 0),
        ]);

        return $this->category->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze kategorii. Vyzaduje roli admin.
     * Blokuje smazani kdyz je kategorie prirazena k produktum (409).
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        if (!$this->category->findById($id)) {
            Response::notFound('Category not found');
        }

        if ($this->product->existsForCategory($id)) {
            Response::error('Category is in use by products', 409);
        }

        return $this->category->delete($id);
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
}
