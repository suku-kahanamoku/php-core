<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Address\AddressApi;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class UserApi
{
    private UserService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new UserService($db, $franchiseCode, $auth);
    }

    /** GET /users */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            $request->get('search'),
            $request->get('role'),
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
        ));
    }

    /** GET /users/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /users */
    public function create(Request $request): void
    {
        $id = $this->service->create([
            'first_name' => trim((string) $request->get('first_name', '')),
            'last_name'  => trim((string) $request->get('last_name', '')),
            'email'      => trim((string) $request->get('email', '')),
            'password'   => (string) $request->get('password', ''),
            'phone'      => $request->get('phone'),
            'role'       => $request->get('role'),
        ]);
        Response::created($this->service->get($id), 'User created');
    }

    /** PATCH /users/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'first_name' => $request->get('first_name'),
            'last_name'  => $request->get('last_name'),
            'phone'      => $request->get('phone'),
            'role'       => $request->get('role'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'User updated');
    }

    /** PUT /users/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace((int) $params['id'], [
            'first_name' => trim((string) $request->get('first_name', '')),
            'last_name'  => trim((string) $request->get('last_name', '')),
            'phone'      => $request->get('phone'),
            'role'       => $request->get('role'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'User replaced');
    }

    /** DELETE /users/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'User deleted');
    }

    public function registerRoutes(Router $router, AddressApi $addressApi): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
        $router->get('/:userId/address', [$addressApi, 'list']);
    }
}
