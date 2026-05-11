<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Address\AddressRepository;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Product\ProductRepository;
use App\Modules\Role\RoleRepository;
use App\Modules\Router\Response;
use App\Modules\ServiceAuthTrait;
use App\Modules\User\UserRepository;

class OrderService
{
    use ServiceAuthTrait;

    private OrderRepository   $order;
    private UserRepository    $user;
    private AddressRepository $address;
    private ProductRepository $product;
    private RoleRepository    $role;
    private Auth $auth;

    /**
     * OrderService constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->order   = new OrderRepository($db, $franchiseCode);
        $this->user    = new UserRepository($db, $franchiseCode);
        $this->address = new AddressRepository($db, $franchiseCode);
        $this->product = new ProductRepository($db, $franchiseCode);
        $this->role    = new RoleRepository($db, $franchiseCode);
        $this->auth    = $auth;
    }

    protected function getAuth(): Auth
    {
        return $this->auth;
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
        $this->auth->require();

        $userId = $this->auth->hasRole('admin') ? null : $this->auth->id();

        return $this->order->findAll(
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
        $this->auth->require();

        $order = $this->requireEntity($this->order->findById($id, $projection), 'Order not found');
        $this->requireOwnerOrAdmin($order);

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
     *   total_amount: float
     * }
     */
    public function create(array $input): array
    {
        $user     = $input['user']     ?? [];
        $carts    = $input['carts']    ?? [];
        $shipping = $input['shipping'] ?? [];
        $billing  = $input['billing']  ?? [];

        if (empty($carts)) {
            Response::validationError(['carts' => 'At least one item required']);
        }

        $userId            = $this->resolveUserId($user);
        $shippingAddressId = $this->resolveAddress(
            $userId,
            $shipping['address'] ?? [],
            'shipping',
        );
        $billingAddressId = $this->resolveAddress(
            $userId,
            $billing['address'] ?? [],
            'billing',
        );

        $shippingCost  = (float)  ($shipping['total_price'] ?? 0);
        $shippingType  = (string) ($shipping['value'] ?? '');
        $paymentMethod = $this->mapPaymentMethod((string) ($billing['value'] ?? 'bank'));
        $currency      = 'CZK';

        $pdo = $this->order->getPdo();
        $pdo->beginTransaction();

        try {
            $totalAmount   = 0;
            $preparedItems = [];

            foreach ($carts as $cart) {
                $productId = (int) ($cart['product_id'] ?? 0);
                $qty       = (int) ($cart['quantity'] ?? 1);

                if ($productId <= 0 || $qty <= 0) {
                    throw new \InvalidArgumentException(
                        "Invalid item: product_id={$productId}, quantity={$qty}",
                    );
                }

                $product = $this->product->findById($productId);

                if (!$product || !$product['is_active']) {
                    throw new \RuntimeException(
                        "Product #{$productId} not found or inactive",
                    );
                }
                if ($product['stock_quantity'] < $qty) {
                    throw new \RuntimeException(
                        "Insufficient stock for product #{$productId}",
                    );
                }

                $lineTotal = round($product['price'] * $qty, 2);
                $totalAmount += $lineTotal;
                $preparedItems[] = [
                    'product_id'  => $productId,
                    'quantity'    => $qty,
                    'unit_price'  => $product['price'],
                    'total_price' => $lineTotal,
                ];
            }

            $totalAmount += $shippingCost;

            $orderRow = $this->order->create([
                'order_number'        => $this->order->generateNumber(),
                'user_id'             => $userId,
                'status'              => 'pending',
                'total_amount'        => $totalAmount,
                'currency'            => $currency,
                'payment_method'      => $paymentMethod,
                'shipping_type'       => $shippingType !== '' ? $shippingType : null,
                'shipping_cost'       => $shippingCost,
                'shipping_address_id' => $shippingAddressId,
                'billing_address_id'  => $billingAddressId,
                'note'                => $input['note'] ?? null,
            ]);
            $orderId = (int) $orderRow['id'];

            foreach ($preparedItems as $item) {
                $this->order->createItem(array_merge($item, ['order_id' => $orderId]));
                $this->product->adjustStock($item['product_id'], -$item['quantity']);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 422);
        }

        return ['id' => $orderId ?? null, 'total_amount' => $totalAmount ?? 0];
    }

    /**
     * Find existing user by email, or create a guest account. Returns null when no email supplied.
     *
     * @param  array<string, mixed> $user
     * @return int|null
     */
    private function resolveUserId(array $user): ?int
    {
        if ($this->auth->check()) {
            return $this->auth->id();
        }

        $email = trim($user['email'] ?? '');
        if ($email === '') {
            return null;
        }

        $existing = $this->user->findByEmail($email);
        if ($existing) {
            return (int) $existing['id'];
        }

        $roleId = $this->role->findIdByName('user');
        if (!$roleId) {
            return null;
        }

        return (int) $this->user->create([
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

        // Normalise country: "cs" (locale code) → "CZ" (ISO 3166)
        $rawCountry = $addr['state'] ?? $addr['country'] ?? 'CZ';
        $country = is_string($rawCountry) ? strtoupper($rawCountry) : 'CZ';
        if ($country === 'CS') {
            $country = 'CZ';
        }

        return (int) $this->address->create([
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

    private function mapPaymentMethod(string $value): string
    {
        return match ($value) {
            'bank' => 'bank_transfer',
            'cash' => 'cash',
            'card' => 'card',
            'paypal', 'gopay', 'apple_pay',
            'google_pay', 'online' => 'online',
            default                => $value,
        };
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
        $this->auth->requireRole('admin');

        VALIDATOR(['status' => $status])->required('status')->validate();

        $this->requireEntity($this->order->findById($id), 'Order not found');

        return $this->order->updateStatus($id, $status, $projection);
    }

    /**
     * Smaze objednavku. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        $this->requireEntity($this->order->findById($id), 'Order not found');

        return $this->order->delete($id);
    }
}
