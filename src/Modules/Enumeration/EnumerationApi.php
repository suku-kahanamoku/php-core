<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class EnumerationApi
{
    private EnumerationService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new EnumerationService($db, $franchiseCode, $auth);
    }

    /** GET /enumerations */
    public function list(Request $request): void
    {
        $isActive = $request->get('is_active');
        Response::success($this->service->list(
            $request->get('type'),
            $isActive !== null ? (bool)(int) $isActive : null,
            (string) $request->get('sort', ''),
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('filter', ''),
            $request->projection(),
        ));
    }

    /** GET /enumerations/types */
    public function types(Request $request): void
    {
        Response::success($this->service->types());
    }

    /** GET /enumerations/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id'], $request->projection()));
    }

    /** POST /enumerations */
    public function create(Request $request): void
    {
        $item = $this->service->create(
            trim((string) $request->get('type', '')),
            trim((string) $request->get('syscode', '')),
            trim((string) $request->get('label', '')),
            [
                'value'     => $request->get('value'),
                'position'  => $request->get('position', 0),
                'is_active' => $request->get('is_active', 1),
            ],
            $request->projection(),
        );
        Response::created($item, 'Enumeration created');
    }

    /** PATCH /enumerations/:id */
    public function update(Request $request, array $params): void
    {
        $item = $this->service->update((int) $params['id'], [
            'type'      => $request->get('type'),
            'syscode'   => $request->get('syscode'),
            'label'     => $request->get('label'),
            'value'     => $request->get('value'),
            'position'  => $request->get('position'),
            'is_active' => $request->get('is_active'),
        ], $request->projection());
        Response::success($item, 'Enumeration updated');
    }

    /** PUT /enumerations/:id */
    public function replace(Request $request, array $params): void
    {
        $item = $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('type', '')),
            trim((string) $request->get('syscode', '')),
            trim((string) $request->get('label', '')),
            [
                'value'     => $request->get('value'),
                'position'  => $request->get('position', 0),
                'is_active' => $request->get('is_active', 1),
            ],
            $request->projection(),
        );
        Response::success($item, 'Enumeration replaced');
    }

    /** DELETE /enumerations/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Enumeration deleted');
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->get('/types', [$this, 'types']);
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
