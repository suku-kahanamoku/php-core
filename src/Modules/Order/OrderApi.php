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

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new OrderService($db, $franchiseCode, $auth);
    }

    /** GET /orders */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            $request->get('status'),
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
            $request->projection(),
        ));
    }

    /** GET /orders/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id'], $request->projection()));
    }

    /** POST /orders */
    public function create(Request $request): void
    {
        $result = $this->service->create($request->body);
        Response::created($result, 'Order created');
    }

    /** PATCH /orders/:id/status */
    public function updateStatus(Request $request, array $params): void
    {
        $order = $this->service->updateStatus(
            (int) $params['id'],
            trim((string) $request->get('status', '')),
            $request->projection(),
        );
        Response::success($order, 'Order status updated');
    }

    /** DELETE /orders/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Order deleted');
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->patch('/:id/status', [$this, 'updateStatus']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
