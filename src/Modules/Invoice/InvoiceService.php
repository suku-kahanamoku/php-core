<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class InvoiceService
{
    private InvoiceRepository $invoice;
    private Auth $auth;

    /**
     * InvoiceService constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->invoice = new InvoiceRepository($db, $franchiseCode);
        $this->auth    = $auth;
    }

    /**
     * Vrati strankovany seznam faktur.
     * Vyzaduje prihlaseni; admin vidi vsechny, uzivatel vidi pouze vlastni.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string|null $status
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(
        int $page,
        int $limit,
        ?string $status,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $this->auth->require();

        $userId = $this->auth->hasRole('admin') ? null : $this->auth->id();

        return $this->invoice->findAll(
            $page,
            $limit,
            $userId,
            $status,
            $sort,
            $filter,
            $projection,
        );
    }

    /**
     * Vrati fakturu dle ID vcetne polozek.
     * Vyzaduje prihlaseni; vlastnik nebo admin. Pokud faktura neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $this->auth->require();

        $invoice = $this->invoice->findById($id, $projection);
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $invoice['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        return $invoice;
    }

    /**
     * Vystavi fakturu pro existujici objednavku. Vyzaduje roli admin.
     * Kazda objednavka muze mit nejvyse jednu fakturu (409 pri duplicite).
     * Polozky faktury jsou zkopirovat z order_item. Cela operace probiha v transakci.
     *
     * @param  int                  $orderId
     * @param  array<string, mixed> $input  due_at, note
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(int $orderId, array $input, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        if ($orderId <= 0) {
            Response::validationError(['order_id' => 'Required']);
        }

        $order = $this->invoice->getOrder($orderId);
        if (!$order) {
            Response::notFound('Order not found');
        }

        if ($this->invoice->findByOrder($orderId)) {
            Response::error('Invoice already exists for this order', 409);
        }

        $orderItems = $this->invoice->getOrderItems($orderId);
        $issuedAt   = date('Y-m-d H:i:s');
        $dueAt      = $input['due_at'] ?? date('Y-m-d', strtotime('+14 days'));

        $pdo = $this->invoice->getPdo();
        $pdo->beginTransaction();

        try {
            $invoiceRow = $this->invoice->create([
                'invoice_number'     => $this->invoice->generateNumber(),
                'order_id'           => $orderId,
                'user_id'            => $order['user_id'],
                'status'             => 'issued',
                'total_amount'       => $order['total_amount'],
                'currency'           => $order['currency'],
                'issued_at'          => $issuedAt,
                'due_at'             => $dueAt,
                'billing_address_id' => $order['billing_address_id'] ?? null,
                'note'               => $input['note']               ?? '',
            ]);
            $invoiceId = (int) $invoiceRow['id'];

            foreach ($orderItems as $item) {
                $this->invoice->createItem([
                    'invoice_id'  => $invoiceId,
                    'product_id'  => $item['product_id'],
                    'description' => $item['product_name'] ?? '',
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 500);
        }

        return $this->invoice->findById($invoiceId ?? 0, $projection) ?? ['id' => $invoiceId ?? 0];
    }

    /**
     * Zmeni stav faktury. Vyzaduje roli admin.
     * Status 'paid' automaticky nastavi paid_at na aktualni cas.
     *
     * @param  int        $id
     * @param  string     $status
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function updateStatus(int $id, string $status, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        if ($status === '') {
            Response::validationError(['status' => 'Required']);
        }

        $invoice = $this->invoice->findById($id);
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        $this->invoice->updateStatus($id, $status);

        return $this->invoice->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze fakturu. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        $invoice = $this->invoice->findById($id);
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        return $this->invoice->delete($id);
    }
}
