<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Address\AddressRepository;
use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\File\FileRepository;
use App\Modules\Order\OrderRepository;
use App\Modules\Router\Response;
use App\Modules\User\UserRepository;
use App\Utils\Projection;

class InvoiceService extends BaseService
{
    private InvoiceRepository $_invoice;
    private FileRepository    $_file;
    private OrderRepository   $_order;
    private AddressRepository $_address;
    private UserRepository    $_user;

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
        $this->_file    = new FileRepository($db, $franchiseCode);
        $this->_order   = new OrderRepository($db, $franchiseCode);
        $this->_address = new AddressRepository($db, $franchiseCode);
        $this->_user    = new UserRepository($db, $franchiseCode);
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

        $result = $this->_invoice->findAll(
            $page,
            $limit,
            $userId,
            $sort,
            $filter,
            $projection,
        );

        $proj = new Projection($projection);
        if ($proj->needsJoin('files')) {
            $ids     = array_column($result['data'], 'id');
            $fileMap = $this->_file->findByJunctionList(
                'invoice_file',
                'invoice_id',
                $ids
            );
            foreach ($result['data'] as &$item) {
                $item['files'] = $fileMap[(int) $item['id']] ?? [];
            }
            unset($item);
        }

        return $result;
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
        $invoiceUserId = is_array($invoice['user'] ?? null)
            ? (int) ($invoice['user']['id'] ?? 0) : 0;
        if (
            !$this->_auth->hasRole('admin')
            && $invoiceUserId !== $this->_auth->id()
        ) {
            Response::forbidden();
        }

        $proj = new Projection($projection);
        if ($proj->needsJoin('files')) {
            $invoice['files'] = $this->_file->findByJunctionItem(
                'invoice_file',
                'invoice_id',
                $id
            );
        }

        return $invoice;
    }

    /**
     * Vystavi fakturu. Vyzaduje roli admin.
     * Kazda objednavka muze mit nejvyse jednu fakturu (409 pri duplicite).
     * invoice_number je generovano automaticky. Vsechna data jsou ulozena jako snapshot.
     * Cela operace probiha v transakci.
     *
     * @param  array<string, mixed> $input  order_id (required), status, due_at, note, file_ids
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        $orderId = (int) $input['order_id'];

        if ($this->_invoice->findByOrder($orderId)) {
            Response::error('Invoice already exists for this order', 409);
        }

        // Nacti objednavku — snapshot dat
        $order = $this->_order->findById($orderId);
        if (!$order) {
            Response::error('Order not found', 404);
        }

        // Snapshot uzivatele
        $userSnapshot = null;
        if (!empty($order['user_id'])) {
            $u = $this->_user->findById((int) $order['user_id']);
            if ($u) {
                $userSnapshot = [
                    'id'         => (int) $u['id'],
                    'first_name' => $u['first_name'] ?? null,
                    'last_name'  => $u['last_name']  ?? null,
                    'email'      => $u['email']       ?? null,
                    'phone'      => $u['phone']       ?? null,
                ];
            }
        }

        // Snapshot adres
        $billingSnapshot  = null;
        $shippingSnapshot = null;
        if (!empty($order['billing_address_id'])) {
            $a = $this->_address->findById((int) $order['billing_address_id']);
            if ($a) {
                $billingSnapshot = $this->_addressSnapshot($a);
            }
        }
        if (!empty($order['shipping_address_id'])) {
            $a = $this->_address->findById((int) $order['shipping_address_id']);
            if ($a) {
                $shippingSnapshot = $this->_addressSnapshot($a);
            }
        }

        $issuedAt = $input['issued_at'] ?? date('Y-m-d H:i:s');
        $dueAt    = $input['due_at'] ?? date('Y-m-d', strtotime($issuedAt . ' +14 days'));

        $pdo = $this->_invoice->getPdo();
        $pdo->beginTransaction();

        try {
            $invoiceRow = $this->_invoice->create([
                'invoice_number'           => $this->_invoice->generateNumber(),
                'order_id'                 => $orderId,
                'order_number'             => $order['order_number'],
                'status'                   => $input['status'] ?? 'issued',
                'currency'                 => $order['currency'],
                'payment_type'             => $order['payment_type'],
                'shipping_type'            => $order['shipping_type'],
                'shipping_price'           => $order['shipping_price'],
                'total_price'              => $order['total_price'],
                'total_price_with_vat'     => $order['total_price_with_vat'],
                'total_price_all'          => $order['total_price_all'],
                'total_price_all_with_vat' => $order['total_price_all_with_vat'],
                'user'                     => $userSnapshot !== null
                    ? json_encode($userSnapshot, JSON_UNESCAPED_UNICODE) : null,
                'billing_address'          => $billingSnapshot !== null
                    ? json_encode($billingSnapshot, JSON_UNESCAPED_UNICODE) : null,
                'shipping_address'         => $shippingSnapshot !== null
                    ? json_encode($shippingSnapshot, JSON_UNESCAPED_UNICODE) : null,
                'note'                     => $input['note'] ?? $order['note'] ?? null,
                'issued_at'                => $issuedAt,
                'due_at'                   => $dueAt,
            ]);
            $invoiceId = (int) $invoiceRow['id'];

            // Snapshot polozek z order_items
            foreach ($order['order_items'] ?? [] as $oi) {
                $this->_invoice->createItem([
                    'invoice_id'           => $invoiceId,
                    'product_name'         => $oi['product_name'] ?? '',
                    'sku'                  => $oi['sku'] ?? null,
                    'quantity'             => (int) $oi['quantity'],
                    'price'                => $oi['price'],
                    'price_with_vat'       => $oi['price_with_vat'],
                    'vat_rate'             => $oi['vat_rate'],
                    'total_price'          => $oi['total_price'],
                    'total_price_with_vat' => $oi['total_price_with_vat'],
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

        return $this->_invoice->findById($invoiceId, $projection)
            ?? ['id' => $invoiceId];
    }

    /**
     * Vytvori snapshot adresniho zaznamu (bez systemovych poli).
     *
     * @param  array<string, mixed> $address
     * @return array<string, mixed>
     */
    private function _addressSnapshot(array $address): array
    {
        return array_filter([
            'id'         => $address['id']        ?? null,
            'type'       => $address['type']       ?? null,
            'name'       => $address['name']       ?? null,
            'street'     => $address['street']     ?? null,
            'city'       => $address['city']       ?? null,
            'zip'        => $address['zip']        ?? null,
            'country'    => $address['country']    ?? null,
            'company'    => $address['company']    ?? null,
            'vat_number' => $address['vat_number'] ?? null,
            'phone'      => $address['phone']      ?? null,
        ], static fn($v) => $v !== null);
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
