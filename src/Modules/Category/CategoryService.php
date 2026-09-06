<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Utils\QueryPolicy;

class CategoryService extends BaseService
{
    private const PUBLIC_FIELDS = [
        'id', 'created_at', 'updated_at', 'syscode', 'name', 'description',
        'position', 'published', 'parent_id', 'products', 'children',
    ];
    private const PUBLIC_FILTERS = ['id', 'syscode', 'name', 'parent_id'];
    private const PUBLIC_SORTS = ['id', 'created_at', 'updated_at', 'syscode', 'name', 'position'];
    private CategoryRepository $_category;

    /**
     * Konstruktor tridy CategoryService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_category = new CategoryRepository($db, $franchiseCode);
        $this->_auth     = $auth;
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
        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin) {
            $filter = QueryPolicy::filter($filter, self::PUBLIC_FILTERS, [
                'deleted' => 0,
                'published' => ['value' => 1],
            ]);
            $sort = QueryPolicy::sort($sort, self::PUBLIC_SORTS);
            $projection = QueryPolicy::projection($projection, self::PUBLIC_FIELDS);
        }
        $result = $this->_category->findAll($page, $limit, $sort, $filter, $projection);
        return $isAdmin ? $result : QueryPolicy::listFields($result, self::PUBLIC_FIELDS);
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
        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin) {
            $visibility = $this->_category->findById($id, ['published']);
            if (!$visibility || (int) ($visibility['published'] ?? 0) !== 1) {
                Response::notFound('Category not found');
            }
            $projection = QueryPolicy::projection($projection, self::PUBLIC_FIELDS);
        }
        $category = $this->_category->findById($id, $projection);
        $this->_requireEntity($category, 'Category not found');

        $category['products'] = $this->_category->findProducts($id, !$isAdmin);
        return $isAdmin ? $category : QueryPolicy::fields($category, self::PUBLIC_FIELDS);
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
        $this->_auth->requireRole('admin');

        return $this->_category->create([
            'syscode'     => $input['syscode'] ?? null,
            'name'        => $name,
            'description' => $input['description'] ?? null,
            'parent_id'   => isset($input['parent_id']) && $input['parent_id'] !== ''
                ? (int) $input['parent_id']
                : null,
            'position' => (int) ($input['position'] ?? 0),
            'published' => (int) ($input['published'] ?? 1),
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
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_category->findById($id), 'Category not found');

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
        if (isset($input['published'])) {
            $set['published'] = (int) $input['published'];
        }
        if (array_key_exists('parent_id', $input)) {
            $isEmptyParent    = $input['parent_id'] === null || $input['parent_id'] === '';
            $set['parent_id'] = $isEmptyParent ? null : (int) $input['parent_id'];
        }

        if (!empty($set)) {
            $this->_category->update($id, $set);
        }

        return $this->_category->findById($id, $projection) ?? ['id' => $id];
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
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_category->findById($id), 'Category not found');

        $parentId = ($input['parent_id'] ?? null);
        $parentId = ($parentId !== null && $parentId !== '') ? (int) $parentId : null;

        $this->_category->update($id, [
            'syscode'     => $input['syscode'] ?? null,
            'name'        => $name,
            'description' => (string) ($input['description'] ?? ''),
            'parent_id'   => $parentId,
            'position'    => (int) ($input['position'] ?? 0),
            'published'   => (int) ($input['published'] ?? 1),
        ]);

        return $this->_category->findById($id, $projection) ?? ['id' => $id];
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
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_category->findById($id), 'Category not found');

        if ($this->_category->hasProducts($id)) {
            Response::error('Category is in use by products', 409);
        }

        return $this->_category->hardDelete($id);
    }

    /**
     * Soft-smazani kategorie (oznaci jako smazanou, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_category->findById($id), 'Category not found');

        if ($this->_category->hasProducts($id)) {
            Response::error('Category is in use by products', 409);
        }

        return $this->_category->softDelete($id);
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
