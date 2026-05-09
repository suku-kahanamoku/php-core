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

    /**
     * InvoiceApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new InvoiceService($db, $franchiseCode, $auth);
    }

    /**
     * GET /invoices — Vrati strankovany seznam faktur.
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
        $factory = $request->factory();
        if ($factory !== null) {
            $result['items'] = Response::applyFactory($result['items'], $factory);
        }
        Response::success($result);
    }

    /**
     * GET /invoices/:id — Vrati fakturu dle ID vcetne polozek.
     * Vyzaduje prihlaseni; vlastnik nebo admin.
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
     * POST /invoices — Vystavi fakturu pro objednavku. Vyzaduje roli admin.
     *
     * @param Request $request  body: order_id (required), due_at, note
     * @return void
     */
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

    /**
     * PATCH /invoices/:id/status — Zmeni stav faktury. Vyzaduje roli admin.
     *
     * @param Request $request  body: status (required)
     * @param array{id: string} $params
     * @return void
     */
    public function updateStatus(Request $request, array $params): void
    {
        $invoice = $this->service->updateStatus(
            (int) $params['id'],
            trim((string) $request->get('status', '')),
            $request->projection(),
        );
        Response::success($invoice, 'Invoice status updated');
    }

    /**
     * DELETE /invoices/:id — Smaze fakturu. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Invoice deleted');
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
