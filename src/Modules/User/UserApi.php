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

    /**
     * UserApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new UserService($db, $franchiseCode, $auth);
    }

    /**
     * GET /users — Vrati strankovany seznam uzivatelu. Vyzaduje roli admin.
     *
     * @param Request $request  query: page, limit, search, role, sort, filter, projection
     * @return void
     */
    public function list(Request $request): void
    {
        $result  = $this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            $request->get('search'),
            $request->get('role'),
            (string) $request->get('sort', ''),
            (string) $request->get('q', ''),
            $request->projection(),
        );
        Response::successWithFactory($result, $request);
    }

    /**
     * GET /users/:id — Vrati uzivatele dle ID. Vyzaduje prihlaseni; vlastnik nebo admin.
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
     * POST /users — Vytvori noveho uzivatele. Vyzaduje roli admin.
     *
     * @param Request $request  body: first_name, last_name, email (required), password (required), phone, role
     * @return void
     */
    public function create(Request $request): void
    {
        $user = $this->service->create([
            'first_name' => trim((string) $request->get('first_name', '')),
            'last_name'  => trim((string) $request->get('last_name', '')),
            'email'      => trim((string) $request->get('email', '')),
            'password'   => (string) $request->get('password', ''),
            'phone'      => $request->get('phone'),
            'role'       => $request->get('role'),
        ], $request->projection());
        Response::created($user, 'User created');
    }

    /**
     * PATCH /users/:id — Castecna aktualizace uzivatele. Vyzaduje prihlaseni; vlastnik nebo admin.
     *
     * @param Request $request  body: first_name, last_name, phone, role
     * @param array{id: string} $params
     * @return void
     */
    public function update(Request $request, array $params): void
    {
        $user = $this->service->update((int) $params['id'], [
            'first_name' => $request->get('first_name'),
            'last_name'  => $request->get('last_name'),
            'phone'      => $request->get('phone'),
            'role'       => $request->get('role'),
        ], $request->projection());
        Response::success($user, 'User updated');
    }

    /**
     * PUT /users/:id — Uplna nahrada uzivatele. Vyzaduje prihlaseni; vlastnik nebo admin.
     *
     * @param Request $request  body: first_name, last_name (required), phone, role
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $user = $this->service->replace((int) $params['id'], [
            'first_name' => trim((string) $request->get('first_name', '')),
            'last_name'  => trim((string) $request->get('last_name', '')),
            'phone'      => $request->get('phone'),
            'role'       => $request->get('role'),
        ], $request->projection());
        Response::success($user, 'User replaced');
    }

    /**
     * DELETE /users/:id — Smaze uzivatele. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'User deleted');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     *
     * @param  Router     $router
     * @param  AddressApi $addressApi
     * @return void
     */
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
