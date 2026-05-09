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

    /**
     * RoleApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new RoleService($db, $franchiseCode, $auth);
    }

    /**
     * GET /roles — Vrati strankovany seznam roli.
     *
     * @param Request $request  query: page, limit, sort, filter, projection
     * @return void
     */
    public function list(Request $request): void
    {
        $result  = $this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
            $request->projection(),
        );
        Response::successWithFactory($result, $request);
    }

    /**
     * GET /roles/:id — Vrati roli dle ID.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function get(Request $request, array $params): void
    {
        $item    = $this->service->get((int) $params['id'], $request->projection());
        Response::successItemWithFactory($item, $request);
    }

    /**
     * POST /roles — Vytvori novou roli. Vyzaduje roli admin.
     *
     * @param Request $request  body: name (required), label, position
     * @return void
     */
    public function create(Request $request): void
    {
        $role = $this->service->create(
            trim(strtolower((string) $request->get('name', ''))),
            trim((string) $request->get('label', '')),
            (int) $request->get('position', 0),
            $request->projection(),
        );
        Response::created($role, 'Role created');
    }

    /**
     * PATCH /roles/:id — Castecna aktualizace role. Vyzaduje roli admin.
     *
     * @param Request $request  body: name, label, position (libovolna podmnozina)
     * @param array{id: string} $params
     * @return void
     */
    public function update(Request $request, array $params): void
    {
        $fields     = [];
        $roleFields = ['name', 'label', 'position'];

        foreach ($roleFields as $f) {
            if ($request->get($f) !== null) {
                $fields[$f] = $request->get($f);
            }
        }
        $role = $this->service->update(
            (int) $params['id'],
            $fields,
            $request->projection()
        );
        Response::success($role, 'Role updated');
    }

    /**
     * PUT /roles/:id — Uplna nahrada role. Vyzaduje roli admin.
     *
     * @param Request $request  body: name (required), label, position
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $role = $this->service->replace(
            (int) $params['id'],
            trim(strtolower((string) $request->get('name', ''))),
            trim((string) $request->get('label', '')),
            (int) $request->get('position', 0),
            $request->projection(),
        );
        Response::success($role, 'Role replaced');
    }

    /**
     * DELETE /roles/:id — Smaze roli. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Role deleted');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     *
     * @param  Router $router
     * @return void
     */
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
