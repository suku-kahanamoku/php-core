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

    /**
     * Konstruktor AddressApi.
     * 
     * @param Database $db
     * @param string $franchiseCode
     * @param Auth $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new AddressService($db, $franchiseCode, $auth);
    }

    /**
     * GET /users/:userId/addresses — Vrati seznam adres uzivatele.
     *
     * @param Request $request  query: type, sort, page, limit, filter, projection
     * @param array{userId: string} $params
     * @return void
     */
    public function list(Request $request, array $params): void
    {
        $result  = $this->service->listByUser(
            (int) $params['userId'],
            $request->get('type'),
            (string) $request->get('sort', ''),
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('filter', ''),
            $request->projection(),
        );
        $factory = $request->factory();
        if ($factory !== null) {
            $result['items'] = Response::applyFactory($result['items'], $factory);
        }
        Response::success($result);
    }

    /**
     * GET /addresses/:id — Vrati adresu dle ID.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function get(Request $request, array $params): void
    {
        $item    = $this->service->get((int) $params['id'], $request->projection());
        $factory = $request->factory();
        if ($factory !== null) {
            $item = Response::applyFactory([$item], $factory)[0];
        }
        Response::success($item);
    }

    /**
     * POST /addresses — Vytvori novou adresu. Vyzaduje prihlaseni.
     *
     * @param Request $request  body: type, name, street, city, zip, country, company, is_default, user_id
     * @return void
     */
    public function create(Request $request): void
    {
        $address = $this->service->create(
            [
                'type'       => $request->get('type', 'billing'),
                'company'    => $request->get('company', ''),
                'name'       => $request->get('name'),
                'street'     => trim((string) $request->get('street', '')),
                'city'       => trim((string) $request->get('city', '')),
                'zip'        => trim((string) $request->get('zip', '')),
                'country'    => trim((string) $request->get('country', 'CZ')),
                'is_default' => $request->get('is_default', 0),
            ],
            $request->get('user_id') !== null ? (int) $request->get('user_id') : null,
            $request->projection()
        );

        Response::created($address, 'Address created');
    }

    /**
     * PATCH /addresses/:id — Castecna aktualizace adresy. Vyzaduje prihlaseni; vlastnik nebo admin.
     *
     * @param Request $request  body: libovolna podmnozina sloupcu adresy
     * @param array{id: string} $params
     * @return void
     */
    public function update(Request $request, array $params): void
    {
        $address = $this->service->update((int) $params['id'], [
            'type'       => $request->get('type'),
            'company'    => $request->get('company'),
            'name'       => $request->get('name'),
            'street'     => $request->get('street'),
            'city'       => $request->get('city'),
            'zip'        => $request->get('zip'),
            'country'    => $request->get('country'),
            'is_default' => $request->get('is_default'),
        ], $request->projection());
        Response::success($address, 'Address updated');
    }

    /**
     * PUT /addresses/:id — Uplna nahrada adresy. Vyzaduje prihlaseni; vlastnik nebo admin.
     *
     * @param Request $request  body: type, name, street, city, zip, country (vsechna pole povinne)
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $address = $this->service->replace((int) $params['id'], [
            'type'       => $request->get('type', 'billing'),
            'company'    => $request->get('company', ''),
            'name'       => $request->get('name'),
            'street'     => trim((string) $request->get('street', '')),
            'city'       => trim((string) $request->get('city', '')),
            'zip'        => trim((string) $request->get('zip', '')),
            'country'    => trim((string) $request->get('country', '')),
            'is_default' => $request->get('is_default', 0),
        ], $request->projection());
        Response::success($address, 'Address replaced');
    }

    /**
     * DELETE /addresses/:id — Smaze adresu. Vyzaduje prihlaseni; vlastnik nebo admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        Response::success($this->service->delete((int) $params['id']), 'Address deleted');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     *
     * @param  Router $router
     * @return void
     */
    public function registerRoutes(Router $router): void
    {
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
