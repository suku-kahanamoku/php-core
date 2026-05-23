<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Address\AddressRepository;
use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Enumeration\EnumerationRepository;
use App\Modules\Product\ProductRepository;
use App\Modules\Role\RoleRepository;
use App\Modules\Router\Response;
use App\Modules\User\UserRepository;

class OrderService extends BaseService
{
    private OrderRepository         $_order;
    private UserRepository           $_user;
    private AddressRepository        $_address;
    private ProductRepository        $_product;
    private RoleRepository           $_role;
    private EnumerationRepository    $_enum;

    /**
     * Konstruktor tridy OrderService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_order   = new OrderRepository($db, $franchiseCode);
        $this->_user    = new UserRepository($db, $franchiseCode);
        $this->_address = new AddressRepository($db, $franchiseCode);
        $this->_product = new ProductRepository($db, $franchiseCode);
        $this->_role    = new RoleRepository($db, $franchiseCode);
        $this->_enum    = new EnumerationRepository($db, $franchiseCode);
        $this->_auth    = $auth;
    }

    /**
     * Vrati strankovany seznam objednavek.
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

        return $this->_order->findAll(
            $page,
            $limit,
            $userId,
            $sort,
            $filter,
            $projection
        );
    }

    /**
     * Vrati objednavku dle ID vcetne polozek.
     * Vyzaduje prihlaseni; vlastnik nebo admin. Pokud objednavka neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $this->_auth->require();

        $order = $this->_order->findById($id, $projection);
        $this->_requireEntity($order, 'Order not found');
        if (!$this->_auth->hasRole('admin') && (int) $order['user_id'] !== $this->_auth->id()) {
            Response::forbidden();
        }

        return $order;
    }

    /**
     * Vytvori novou objednavku. Verejne dostupne (bez prihlaseni = guest checkout).
     * Automaticky vytvori uzivatele / adresy kdyz neexistuji.
     * Kontroluje dostupnost skladu; cela operace probiha v transakci.
     * Vraci pole {id, total_amount} (ne kompletni zaznam, pouzij get() pro detail).
     *
     * @param  array{
     *   user?: array{email?: string, first_name?: string, last_name?: string, phone?: string},
     *   carts: list<array{product_id: int, quantity: int}>,
     *   shipping?: array{value?: string, total_price?: float, address?: array<string, mixed>},
     *   billing?: array{value?: string, address?: array<string, mixed>},
     *   note?: string
     * } $input
     * @return array{
     *   id: int|null,
     *   total_price: float
     * }
     */
    public function create(array $input): array
    {
        $user     = $input['user']     ?? [];
        $carts    = $input['carts']    ?? [];
        $shipping = $input['shipping'] ?? [];
        $billing  = $input['billing']  ?? [];

        $userId            = $this->resolveUserId($user);
        $shippingAddressId = $this->resolveAddress(
            $userId,
            $user['address']['shipping'] ?? [],
            'shipping',
        );
        $billingAddressId = $this->resolveAddress(
            $userId,
            $user['address']['main'] ?? [],
            'billing',
        );

        $shippingSyscode = (string) ($shipping['value'] ?? '');
        $paymentSyscode  = (string) ($billing['value'] ?? 'bank');
        $currency        = 'CZK';

        // Snapshot platebni metody z ciselníku
        $paymentEnum     = $this->_enum->findBySyscode('payment', $paymentSyscode);
        $paymentSnapshot = null;
        if ($paymentEnum) {
            $data            = is_array($paymentEnum['data'] ?? null) ? $paymentEnum['data'] : [];
            $paymentSnapshot = array_merge(
                ['type' => $paymentEnum['syscode'], 'label' => $paymentEnum['label']],
                $data,
            );
        }

        // Snapshot zpusobu dopravy z ciselníku
        $shippingEnum     = $shippingSyscode !== '' ? $this->_enum->findBySyscode('shipping', $shippingSyscode) : null;
        $shippingSnapshot = null;
        $shippingPrice    = 0.0;
        if ($shippingEnum) {
            $data             = is_array($shippingEnum['data'] ?? null) ? $shippingEnum['data'] : [];
            $shippingPrice    = (float) ($data['price'] ?? 0);
            $shippingSnapshot = array_merge(
                ['type' => $shippingEnum['syscode'], 'label' => $shippingEnum['label']],
                $data,
            );
        }

        $totalPrice        = 0.0;
        $totalPriceWithVat = 0.0;
        $preparedItems     = [];

        foreach ($carts as $cart) {
            $productId = (int) ($cart['product_id'] ?? 0);
            $qty       = (int) ($cart['quantity'] ?? 1);

            if ($productId <= 0 || $qty <= 0) {
                Response::error("Invalid item: product_id={$productId}, quantity={$qty}", 422);
            }

            $product = $this->_product->findById($productId);

            if (!$product || !$product['published']) {
                Response::error("Product #{$productId} not found or inactive", 422);
            }
            if ($product['stock_quantity'] < $qty) {
                Response::error("Insufficient stock for product #{$productId}", 422);
            }

            $price        = (float) $product['price'];
            $priceWithVat = (float) $product['price_with_vat'];
            $vatRate      = (float) $product['vat_rate'];

            $lineTotal        = round($price * $qty, 2);
            $lineTotalWithVat = round($priceWithVat * $qty, 2);

            $totalPrice        += $lineTotal;
            $totalPriceWithVat += $lineTotalWithVat;

            $preparedItems[] = [
                'product_id'           => $productId,
                'product_name'         => $product['name'],
                'sku'                  => $product['sku'],
                'quantity'             => $qty,
                'price'                => $price,
                'price_with_vat'       => $priceWithVat,
                'vat_rate'             => $vatRate,
                'total_price'          => $lineTotal,
                'total_price_with_vat' => $lineTotalWithVat,
            ];
        }

        $totalPriceAll        = round($totalPrice + $shippingPrice, 2);
        $totalPriceAllWithVat = round($totalPriceWithVat + $shippingPrice, 2);

        $orderRow = $this->_order->create([
            'order_number'             => $this->_order->generateNumber(),
            'user_id'                  => $userId,
            'status'                   => 'pending',
            'total_price'              => round($totalPrice, 2),
            'total_price_with_vat'     => round($totalPriceWithVat, 2),
            'total_price_all'          => $totalPriceAll,
            'total_price_all_with_vat' => $totalPriceAllWithVat,
            'currency'                 => $currency,
            'payment'                  => $paymentSnapshot !== null
                ? json_encode($paymentSnapshot, JSON_UNESCAPED_UNICODE) : null,
            'shipping'                 => $shippingSnapshot !== null
                ? json_encode($shippingSnapshot, JSON_UNESCAPED_UNICODE) : null,
            'shipping_address_id'      => $shippingAddressId,
            'billing_address_id'       => $billingAddressId,
            'note'                     => $input['note'] ?? null,
        ]);
        $orderId = (int) $orderRow['id'];

        foreach ($preparedItems as $item) {
            $this->_order->createItem(array_merge($item, ['order_id' => $orderId]));
            $this->_product->adjustStock($item['product_id'], -$item['quantity']);
        }

        return $this->_order->findById($orderId) ?? ['id' => $orderId];
    }

    /**
     * Find existing user by email, or create a guest account. Returns null when no email supplied.
     *
     * @param  array<string, mixed> $user
     * @return int|null
     */
    private function resolveUserId(array $user): ?int
    {
        if ($this->_auth->check()) {
            return $this->_auth->id();
        }

        $email = trim($user['email'] ?? '');
        if ($email === '') {
            return null;
        }

        $existing = $this->_user->findByEmail($email);
        if ($existing) {
            return (int) $existing['id'];
        }

        $roleId = $this->_role->findIdByName('user');
        if (!$roleId) {
            return null;
        }

        return (int) $this->_user->create([
            'first_name' => $user['first_name'] ?? '',
            'last_name'  => $user['last_name']  ?? '',
            'email'      => $email,
            'phone'      => $user['phone'] ?? null,
            'password'   => '',   // guest – login disabled
            'role_id'    => $roleId,
        ])['id'];
    }

    /**
     * Create an address record for the order and return its ID. Returns null when required fields are missing.
     *
     * @param  int|null             $userId
     * @param  array<string, mixed> $addr
     * @param  string               $type
     * @return int|null
     */
    private function resolveAddress(?int $userId, array $addr, string $type): ?int
    {
        if ($userId === null || empty($addr['street'])) {
            return null;
        }

        // Normalizace zeme: "cs" (locale kod) → "CZ" (ISO 3166)
        $rawCountry = $addr['state'] ?? $addr['country'] ?? 'CZ';
        $country = is_string($rawCountry) ? strtoupper($rawCountry) : 'CZ';
        if ($country === 'CS') {
            $country = 'CZ';
        }

        return (int) $this->_address->create([
            'user_id'    => $userId,
            'type'       => $type,
            'name'       => $addr['name']    ?? null,
            'company'    => $addr['company'] ?? null,
            'street'     => $addr['street'],
            'city'       => $addr['city'] ?? '',
            'zip'        => $addr['zip']  ?? '',
            'country'    => $country,
            'is_default' => 0,
        ])['id'];
    }

    /**
     * Zmeni stav objednavky. Vyzaduje roli admin.
     * Pokud objednavka neexistuje, vraci 404.
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

        $order = $this->_order->findById($id);
        $this->_requireEntity($order, 'Order not found');

        return $this->_order->updateStatus($id, $status, $projection);
    }

    /**
     * Smaze objednavku. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->_auth->requireRole('admin');

        $order = $this->_order->findById($id);
        $this->_requireEntity($order, 'Order not found');

        return $this->_order->hardDelete($id);
    }

    /**
     * Soft-smazani objednavky (oznaci jako smazanou, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $order = $this->_order->findById($id);
        $this->_requireEntity($order, 'Order not found');

        return $this->_order->softDelete($id);
    }
}
