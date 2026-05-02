<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Core\Request;
use App\Core\Response;

class CategoryApi
{
    private CategoryService $service;

    public function __construct()
    {
        $this->service = new CategoryService();
    }

    /** GET /categories */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            (string) $request->get('sort_by', 'sort_order'),
            (string) $request->get('sort_dir', 'ASC'),
            (bool) $request->get('flat', false)
        ));
    }

    /** GET /categories/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /categories */
    public function create(Request $request): void
    {
        $id = $this->service->create(
            trim((string) $request->get('name', '')),
            [
                'slug'        => $request->get('slug'),
                'description' => $request->get('description', ''),
                'parent_id'   => $request->get('parent_id'),
                'sort_order'  => $request->get('sort_order', 0),
            ]
        );
        Response::created(['id' => $id], 'Category created');
    }

    /** PATCH /categories/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'name'        => $request->get('name'),
            'slug'        => $request->get('slug'),
            'description' => $request->get('description'),
            'parent_id'   => $request->get('parent_id'),
            'sort_order'  => $request->get('sort_order'),
        ]);
        Response::success(null, 'Category updated');
    }

    /** PUT /categories/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('name', '')),
            [
                'slug'        => $request->get('slug'),
                'description' => $request->get('description'),
                'parent_id'   => $request->get('parent_id'),
                'sort_order'  => $request->get('sort_order', 0),
            ]
        );
        Response::success(null, 'Category replaced');
    }

    /** DELETE /categories/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Category deleted');
    }
}
