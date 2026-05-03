<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class AddressApi
{
    private AddressService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new AddressService($db, $franchiseCode, $auth);
    }

    /** GET /users/:userId/addresses */
    public function list(Request $request, array $params): void
    {
        Response::success($this->service->listByUser(
            (int) $params['userId'],
            $request->get('type'),
            (string) $request->get('sort', ''),
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
        ));
    }

    /** GET /addresses/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /addresses */
    public function create(Request $request): void
    {
        $id = $this->service->create([
            'type'       => $request->get('type', 'billing'),
            'company'    => $request->get('company', ''),
            'name'       => $request->get('name'),
            'street'     => trim((string) $request->get('street', '')),
            'city'       => trim((string) $request->get('city', '')),
            'zip'        => trim((string) $request->get('zip', '')),
            'country'    => trim((string) $request->get('country', 'CZ')),
            'is_default' => $request->get('is_default', 0),
        ], $request->get('user_id') !== null ? (int) $request->get('user_id') : null);

        Response::created($this->service->get($id), 'Address created');
    }

    /** PATCH /addresses/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'type'       => $request->get('type'),
            'company'    => $request->get('company'),
            'name'       => $request->get('name'),
            'street'     => $request->get('street'),
            'city'       => $request->get('city'),
            'zip'        => $request->get('zip'),
            'country'    => $request->get('country'),
            'is_default' => $request->get('is_default'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'Address updated');
    }

    /** PUT /addresses/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace((int) $params['id'], [
            'type'       => $request->get('type', 'billing'),
            'company'    => $request->get('company', ''),
            'name'       => $request->get('name'),
            'street'     => trim((string) $request->get('street', '')),
            'city'       => trim((string) $request->get('city', '')),
            'zip'        => trim((string) $request->get('zip', '')),
            'country'    => trim((string) $request->get('country', '')),
            'is_default' => $request->get('is_default', 0),
        ]);
        Response::success($this->service->get((int) $params['id']), 'Address replaced');
    }

    /** DELETE /addresses/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Address deleted');
    }

    public function registerRoutes(Router $router): void
    {
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
