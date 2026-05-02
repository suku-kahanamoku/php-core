<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Core\Franchise;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class CategoryService
{
    private Category $category;

    public function __construct()
    {
        $this->category = new Category(Database::getInstance(), Franchise::code());
    }

    public function list(string $sortBy, string $sortDir, bool $flat = false): array
    {
        $items = $this->category->findAll($sortBy, $sortDir);
        return $flat ? $items : $this->buildTree($items);
    }

    public function get(int $id): array
    {
        $category = $this->category->findById($id);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $category['products'] = $this->category->getProducts($id);
        return $category;
    }

    public function create(string $name, array $input): int
    {
        Auth::requireRole('admin');

        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        $slug = !empty($input['slug']) ? $input['slug'] : $this->toSlug($name);

        if ($this->category->slugExists($slug)) {
            Response::error("Slug '{$slug}' already exists", 409);
        }

        return $this->category->create([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $input['description'] ?? '',
            'parent_id'   => isset($input['parent_id']) && $input['parent_id'] !== ''
                ? (int) $input['parent_id']
                : null,
            'sort_order'  => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    public function update(int $id, array $input): void
    {
        Auth::requireRole('admin');

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
        if (isset($input['sort_order'])) {
            $set['sort_order'] = (int) $input['sort_order'];
        }
        if (array_key_exists('parent_id', $input)) {
            $isEmptyParent = $input['parent_id'] === null || $input['parent_id'] === '';
            $set['parent_id'] = $isEmptyParent ? null : (int) $input['parent_id'];
        }

        if (isset($input['slug'])) {
            $newSlug = trim((string) $input['slug']);
            if ($this->category->slugExists($newSlug, $id)) {
                Response::error("Slug '{$newSlug}' already exists", 409);
            }
            $set['slug'] = $newSlug;
        } elseif (isset($set['name'])) {
            // Auto-update slug only if name changed and no explicit slug given
        }

        if (!empty($set)) {
            $this->category->update($id, $set);
        }
    }

    public function replace(int $id, string $name, array $input): void
    {
        Auth::requireRole('admin');

        if (!$this->category->findById($id)) {
            Response::notFound('Category not found');
        }

        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        $slug = !empty($input['slug'])
            ? trim((string) $input['slug'])
            : $this->toSlug($name);

        if ($this->category->slugExists($slug, $id)) {
            Response::error("Slug '{$slug}' already exists", 409);
        }

        $parentId = ($input['parent_id'] ?? null);
        $parentId = ($parentId !== null && $parentId !== '') ? (int) $parentId : null;

        $this->category->update($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => (string) ($input['description'] ?? ''),
            'parent_id'   => $parentId,
            'sort_order'  => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');

        if (!$this->category->findById($id)) {
            Response::notFound('Category not found');
        }

        if ($this->category->hasProducts($id)) {
            Response::error('Category is in use by products', 409);
        }

        $this->category->delete($id);
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
