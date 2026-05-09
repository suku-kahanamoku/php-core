<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class InvoiceApi
{
    private InvoiceService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new InvoiceService($db, $franchiseCode, $auth);
    }

    /** GET /invoices */
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

    /** GET /invoices/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id'], $request->projection()));
    }

    /** POST /invoices */
    public function create(Request $request): void
    {
        $invoice = $this->service->create(
            (int) $request->get('order_id', 0),
            [
                'due_at' => $request->get('due_at'),
                'note'   => $request->get('note', ''),
            ],
            $request->projection(),
        );
        Response::created($invoice, 'Invoice created');
    }

    /** PATCH /invoices/:id/status */
    public function updateStatus(Request $request, array $params): void
    {
        $invoice = $this->service->updateStatus(
            (int) $params['id'],
            trim((string) $request->get('status', '')),
            $request->projection(),
        );
        Response::success($invoice, 'Invoice status updated');
    }

    /** DELETE /invoices/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Invoice deleted');
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
