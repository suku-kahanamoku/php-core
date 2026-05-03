<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class TextApi
{
    private TextService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new TextService($db, $franchiseCode, $auth);
    }

    /** GET /texts */
    public function list(Request $request): void
    {
        $isActive = $request->get('is_active');
        Response::success($this->service->list(
            (string) $request->get('language', 'cs'),
            $isActive !== null ? (bool)(int) $isActive : null,
            $request->get('search'),
            (string) $request->get('sort', ''),
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
        ));
    }

    /** GET /texts/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** GET /texts/by-key/:key */
    public function getByKey(Request $request, array $params): void
    {
        Response::success($this->service->getByKey(
            $params['key'],
            (string) $request->get('language', 'cs'),
        ));
    }

    /** POST /texts */
    public function create(Request $request): void
    {
        $id = $this->service->create(
            trim((string) $request->get('syscode', '')),
            trim((string) $request->get('title', '')),
            trim((string) $request->get('language', 'cs')),
            [
                'content'   => $request->get('content'),
                'is_active' => $request->get('is_active', 1),
            ],
        );
        Response::created($this->service->get($id), 'Text created');
    }

    /** PATCH /texts/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'syscode'   => $request->get('syscode'),
            'title'     => $request->get('title'),
            'content'   => $request->get('content'),
            'language'  => $request->get('language'),
            'is_active' => $request->get('is_active'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'Text updated');
    }

    /** PUT /texts/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('syscode', '')),
            trim((string) $request->get('title', '')),
            [
                'content'   => $request->get('content'),
                'language'  => $request->get('language', 'cs'),
                'is_active' => $request->get('is_active', 1),
            ],
        );
        Response::success($this->service->get((int) $params['id']), 'Text replaced');
    }

    /** DELETE /texts/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Text deleted');
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/by-key/:key', [$this, 'getByKey']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
