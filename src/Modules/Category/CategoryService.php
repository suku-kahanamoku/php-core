<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class CategoryService
{
    private CategoryRepository $category;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->category = new CategoryRepository($db, $franchiseCode);
        $this->auth     = $auth;
    }

    public function list(int $page = 1, int $limit = 20, string $sort = '', string $filter = ''): array
    {
        return $this->category->findAll($page, $limit, $sort, $filter);
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
        $this->auth->requireRole('admin');

        if ($name === '') {
            Response::validationError(['name' => 'Required']);
        }

        return $this->category->create([
            'name'        => $name,
            'description' => $input['description'] ?? '',
            'parent_id'   => isset($input['parent_id']) && $input['parent_id'] !== ''
                ? (int) $input['parent_id']
                : null,
            'position' => (int) ($input['position'] ?? 0),
        ]);
    }

    public function update(int $id, array $input): void
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
    }

    public function replace(int $id, string $name, array $input): void
    {
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
            'name'        => $name,
            'description' => (string) ($input['description'] ?? ''),
            'parent_id'   => $parentId,
            'position'    => (int) ($input['position'] ?? 0),
        ]);
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

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
}
