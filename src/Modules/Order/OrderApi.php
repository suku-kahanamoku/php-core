<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;

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
            (string) $request->get('sort_by', 'created_at'),
            (string) $request->get('sort_dir', 'DESC'),
        ));
    }

    /** GET /orders/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /orders */
    public function create(Request $request): void
    {
        $result = $this->service->create(
            (array) $request->get('items', []),
            (string) $request->get('currency', 'CZK'),
            [
                'payment_method'      => $request->get('payment_method', 'bank_transfer'),
                'note'                => $request->get('note', ''),
                'shipping_address_id' => $request->get('shipping_address_id'),
                'billing_address_id'  => $request->get('billing_address_id'),
            ],
        );
        Response::created($result, 'Order created');
    }

    /** PATCH /orders/:id/status */
    public function updateStatus(Request $request, array $params): void
    {
        $this->service->updateStatus(
            (int) $params['id'],
            trim((string) $request->get('status', '')),
        );
        Response::success(null, 'Order status updated');
    }

    /** DELETE /orders/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Order deleted');
    }
}
