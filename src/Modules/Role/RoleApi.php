<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class RoleApi
{
    private RoleService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new RoleService($db, $franchiseCode, $auth);
    }

    /** GET /roles */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            (string) $request->get('sort', ''),
        ));
    }

    /** GET /roles/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /roles */
    public function create(Request $request): void
    {
        $id = $this->service->create(
            trim(strtolower((string) $request->get('name', ''))),
            trim((string) $request->get('label', '')),
            (int) $request->get('position', 0),
        );
        Response::created($this->service->get($id), 'Role created');
    }

    /** PATCH /roles/:id */
    public function update(Request $request, array $params): void
    {
        $fields     = [];
        $roleFields = ['name', 'label', 'position'];

        foreach ($roleFields as $f) {
            if ($request->get($f) !== null) {
                $fields[$f] = $request->get($f);
            }
        }
        $this->service->update((int) $params['id'], $fields);
        Response::success($this->service->get((int) $params['id']), 'Role updated');
    }

    /** PUT /roles/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace(
            (int) $params['id'],
            trim(strtolower((string) $request->get('name', ''))),
            trim((string) $request->get('label', '')),
            (int) $request->get('position', 0),
        );
        Response::success($this->service->get((int) $params['id']), 'Role replaced');
    }

    /** DELETE /roles/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Role deleted');
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
