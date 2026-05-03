<?php

declare(strict_types=1);

namespace App\Modules\Category;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class CategoryApi
{
    private CategoryService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new CategoryService($db, $franchiseCode, $auth);
    }

    /** GET /categories */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            (string) $request->get('sort_by', 'position'),
            (string) $request->get('sort_dir', 'ASC'),
            (bool) $request->get('flat', false),
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
                'description' => $request->get('description', ''),
                'parent_id'   => $request->get('parent_id'),
                'position'    => $request->get('position', 0),
            ],
        );
        Response::created($this->service->get($id), 'Category created');
    }

    /** PATCH /categories/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'name'        => $request->get('name'),
            'description' => $request->get('description'),
            'parent_id'   => $request->get('parent_id'),
            'position'    => $request->get('position'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'Category updated');
    }

    /** PUT /categories/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('name', '')),
            [
                'description' => $request->get('description'),
                'parent_id'   => $request->get('parent_id'),
                'position'    => $request->get('position', 0),
            ],
        );
        Response::success($this->service->get((int) $params['id']), 'Category replaced');
    }

    /** DELETE /categories/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Category deleted');
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
