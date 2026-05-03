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

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->invoice = new InvoiceRepository($db, $franchiseCode);
        $this->auth    = $auth;
    }

    public function list(
        int $page,
        int $limit,
        ?string $status,
        string $sort = '',
    ): array {
        $this->auth->require();

        $userId = $this->auth->hasRole('admin') ? null : $this->auth->id();

        return $this->invoice->findAll(
            $page,
            $limit,
            $userId,
            $status,
            $sort,
        );
    }

    public function get(int $id): array
    {
        $this->auth->require();

        $invoice = $this->invoice->findById($id);
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $invoice['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        return $invoice;
    }

    public function create(int $orderId, array $input): int
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
            $invoiceId = $this->invoice->create([
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

        return $invoiceId ?? 0;
    }

    public function updateStatus(int $id, string $status): void
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
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

        $invoice = $this->invoice->findById($id);
        if (!$invoice) {
            Response::notFound('Invoice not found');
        }

        $this->invoice->delete($id);
    }
}
