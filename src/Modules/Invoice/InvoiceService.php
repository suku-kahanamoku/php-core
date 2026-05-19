<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Order\OrderRepository;
use App\Modules\Router\Response;

class InvoiceService extends BaseService
{
    private InvoiceRepository $_invoice;
    private OrderRepository   $_order;

    /**
     * Konstruktor tridy InvoiceService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_invoice = new InvoiceRepository($db, $franchiseCode);
        $this->_order   = new OrderRepository($db, $franchiseCode);
        $this->_auth    = $auth;
    }

    /**
     * Vrati strankovany seznam faktur.
     * Vyzaduje prihlaseni; admin vidi vsechny, uzivatel vidi pouze vlastni.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function list(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $this->_auth->require();

        $userId = $this->_auth->hasRole('admin') ? null : $this->_auth->id();

        return $this->_invoice->findAll(
            $page,
            $limit,
            $userId,
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
        $this->_auth->require();

        $invoice = $this->_invoice->findById($id, $projection);
        $this->_requireEntity($invoice, 'Invoice not found');
        if (!$this->_auth->hasRole('admin') && (int) $invoice['user_id'] !== $this->_auth->id()) {
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
    public function create(
        int $orderId,
        array $input,
        ?array $projection = null
    ): array {
        $this->_auth->requireRole('admin');

        $order = $this->_order->findById($orderId);
        $this->_requireEntity($order, 'Order not found');

        if ($this->_invoice->findByOrder($orderId)) {
            Response::error('Invoice already exists for this order', 409);
        }

        $orderItems = $order['order_items'] ?? [];
        $dueAt      = $input['due_at'] ?? date('Y-m-d', strtotime('+14 days'));

        $pdo = $this->_invoice->getPdo();
        $pdo->beginTransaction();

        try {
            $invoiceRow = $this->_invoice->create([
                'invoice_number'     => $this->_invoice->generateNumber(),
                'order_id'           => $orderId,
                'user_id'            => $order['user_id'],
                'status'             => 'issued',
                'total_amount'       => $order['total_price'],
                'currency'           => $order['currency'],
                'due_at'             => $dueAt,
                'billing_address_id' => $order['billing_address_id'] ?? null,
                'note'               => $input['note']               ?? '',
            ]);
            $invoiceId = (int) $invoiceRow['id'];

            foreach ($orderItems as $item) {
                $this->_invoice->createItem([
                    'invoice_id'  => $invoiceId,
                    'product_id'  => $item['product_id'],
                    'description' => $item['product_name'] ?? '',
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['price'],
                    'total_price' => $item['total_price'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 500);
        }

        $invoiceId = $invoiceId ?? 0;

        $fileIds = array_map('intval', (array) ($input['file_ids'] ?? []));
        if ($fileIds) {
            $this->_invoice->syncFiles($invoiceId, $fileIds);
        }

        return $this->_invoice->findById($invoiceId, $projection) ?? ['id' => $invoiceId];
    }

    /**
     * Synchronizuje soubory faktury. Vyzaduje roli admin.
     *
     * @param  int        $id
     * @param  list<int>  $fileIds
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function syncFiles(int $id, array $fileIds, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_invoice->findById($id), 'Invoice not found');
        $this->_invoice->syncFiles($id, $fileIds);

        return $this->_invoice->findById($id, $projection) ?? ['id' => $id];
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
    public function updateStatus(
        int $id,
        string $status,
        ?array $projection = null
    ): array {
        $this->_auth->requireRole('admin');

        $invoice = $this->_invoice->findById($id);
        $this->_requireEntity($invoice, 'Invoice not found');

        $this->_invoice->updateStatus($id, $status);

        return $this->_invoice->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze fakturu. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->_auth->requireRole('admin');

        $invoice = $this->_invoice->findById($id);
        $this->_requireEntity($invoice, 'Invoice not found');

        return $this->_invoice->hardDelete($id);
    }

    /**
     * Soft-smazani faktury (oznaci jako smazanou, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $invoice = $this->_invoice->findById($id);
        $this->_requireEntity($invoice, 'Invoice not found');

        return $this->_invoice->softDelete($id);
    }
}
