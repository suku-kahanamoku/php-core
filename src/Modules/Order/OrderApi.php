<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class OrderApi
{
    private OrderService $service;

    /**
     * OrderApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new OrderService($db, $franchiseCode, $auth);
    }

    /**
     * GET /orders — Vrati strankovany seznam objednavek.
     * Vyzaduje prihlaseni; admin vidi vsechny, uzivatel vidi pouze vlastni.
     *
     * @param Request $request  query: page, limit, status, sort, filter, projection
     * @return void
     */
    public function list(Request $request): void
    {
        $result  = $this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            $request->get('status'),
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
            $request->projection(),
        );
        Response::successWithFactory($result, $request);
    }

    /**
     * GET /orders/:id — Vrati objednavku dle ID vcetne polozek.
     * Vyzaduje prihlaseni; vlastnik nebo admin.
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
     * POST /orders — Vytvori novou objednavku. Verejne dostupne (guest checkout).
     *
     * @param Request $request  body: user?, carts, shipping?, billing?, note?
     * @return void
     */
    public function create(Request $request): void
    {
        $result = $this->service->create($request->body);
        Response::created($result, 'Order created');
    }

    /**
     * PATCH /orders/:id/status — Zmeni stav objednavky. Vyzaduje roli admin.
     *
     * @param Request $request  body: status (required)
     * @param array{id: string} $params
     * @return void
     */
    public function updateStatus(Request $request, array $params): void
    {
        $order = $this->service->updateStatus(
            (int) $params['id'],
            trim((string) $request->get('status', '')),
            $request->projection(),
        );
        Response::success($order, 'Order status updated');
    }

    /**
     * DELETE /orders/:id — Smaze objednavku. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Order deleted');
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
        $router->patch('/:id/status', [$this, 'updateStatus']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
