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
    private InvoiceService $_service;

    /**
     * Konstruktor tridy InvoiceApi.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_service = new InvoiceService($db, $franchiseCode, $auth);
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
        $result  = $this->_service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            (string) $request->get('sort', ''),
            (string) $request->get('q', ''),
            $request->projection(),
        );
        Response::successList($result, $request);
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
        $item    = $this->_service->get((int) $params['id'], $request->projection());
        Response::successItem($item, $request);
    }

    /**
     * POST /invoices — Vystavi fakturu. Vyzaduje roli admin.
     *
     * @param Request $request  body: order_id, user_id, status, total_amount, billing_address_id (required),
     *                                currency, due_at, note, file_ids, items
     * @return void
     */
    public function create(Request $request): void
    {
        $input = [
            'order_id'           => (int) $request->get('order_id', 0),
            'user_id'            => (int) $request->get('user_id', 0),
            'status'             => trim((string) $request->get('status', '')),
            'total_amount'       => $request->get('total_amount'),
            'billing_address_id' => (int) $request->get('billing_address_id', 0),
            'currency'           => $request->get('currency'),
            'due_at'             => $request->get('due_at'),
            'note'               => $request->get('note', ''),
            'file_ids'           => $request->get('file_ids'),
            'items'              => $request->get('items'),
        ];

        VALIDATOR($input)
            ->numeric('order_id', 1)
            ->numeric('user_id', 1)
            ->required('status')
            ->numeric('total_amount', 0)
            ->numeric('billing_address_id', 1)
            ->validate();

        $invoice = $this->_service->create($input, $request->projection());
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
        $status = trim((string) $request->get('status', ''));
        VALIDATOR(['status' => $status])->required('status')->validate();

        $invoice = $this->_service->updateStatus(
            (int) $params['id'],
            $status,
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
        VALIDATOR(['id' => $params['id'] ?? ''])->required('id')->validate();
        $force = filter_var($request->query['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($force) {
            $this->_service->delete((int) $params['id']);
        } else {
            $this->_service->remove((int) $params['id']);
        }
        Response::success(null, 'Invoice deleted');
    }

    /**
     * PATCH /invoices/:id/files — Synchronizuje soubory faktury. Vyzaduje roli admin.
     *
     * @param Request $request  body: file_ids (array of ints)
     * @param array{id: string} $params
     * @return void
     */
    public function syncFiles(Request $request, array $params): void
    {
        VALIDATOR(['id' => $params['id'] ?? ''])
            ->required('id')
            ->numeric('id', 1)
            ->validate();

        $invoice = $this->_service->syncFiles(
            (int) $params['id'],
            array_map('intval', (array) ($request->get('file_ids') ?? [])),
            $request->projection(),
        );
        Response::success($invoice, 'Invoice files updated');
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
        $router->patch('/:id/files', [$this, 'syncFiles']);
        $router->delete('/:id', [$this, 'delete']);
    }
}
