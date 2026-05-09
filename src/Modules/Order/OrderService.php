<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Address\AddressRepository;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Product\ProductRepository;
use App\Modules\Router\Response;
use App\Modules\User\UserRepository;

class OrderService
{
    private OrderRepository   $order;
    private UserRepository    $user;
    private AddressRepository $address;
    private ProductRepository $product;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->order   = new OrderRepository($db, $franchiseCode);
        $this->user    = new UserRepository($db, $franchiseCode);
        $this->address = new AddressRepository($db, $franchiseCode);
        $this->product = new ProductRepository($db, $franchiseCode);
        $this->auth    = $auth;
    }

    public function list(
        int $page,
        int $limit,
        ?string $status,
        string $sort = '',
        string $filter = '',
    ): array {
        $this->auth->require();

        $userId = $this->auth->hasRole('admin') ? null : $this->auth->id();

        return $this->order->findAll($page, $limit, $userId, $status, $sort, $filter);
    }

    public function get(int $id): array
    {
        $this->auth->require();

        $order = $this->order->findById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $order['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        return $order;
    }

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

            $orderId = $this->order->create([
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

    /** Find existing user by email, or create a guest account. Returns null when no email supplied. */
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

        $roleId = $this->user->resolveRoleId('user');
        if (!$roleId) {
            return null;
        }

        return $this->user->create([
            'first_name' => $user['first_name'] ?? '',
            'last_name'  => $user['last_name']  ?? '',
            'email'      => $email,
            'phone'      => $user['phone'] ?? null,
            'password'   => '',   // guest – login disabled
            'role_id'    => $roleId,
        ]);
    }

    /** Create an address record for the order and return its ID. Returns null when required fields are missing. */
    private function resolveAddress(?int $userId, array $addr, string $type): ?int
    {
        if ($userId === null || empty($addr['street'])) {
            return null;
        }

        // Normalise country: "cs" (locale code) → "CZ" (ISO 3166)
        $country = strtoupper($addr['state'] ?? $addr['country'] ?? 'CZ');
        if ($country === 'CS') {
            $country = 'CZ';
        }

        return $this->address->create([
            'user_id'    => $userId,
            'type'       => $type,
            'name'       => $addr['name']    ?? null,
            'company'    => $addr['company'] ?? null,
            'street'     => $addr['street'],
            'city'       => $addr['city'] ?? '',
            'zip'        => $addr['zip']  ?? '',
            'country'    => $country,
            'is_default' => 0,
        ]);
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

    public function updateStatus(int $id, string $status): void
    {
        $this->auth->requireRole('admin');

        VALIDATOR(['status' => $status])->required('status')->validate();

        $order = $this->order->findById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $this->order->updateStatus($id, $status);
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

        $order = $this->order->findById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $this->order->delete($id);
    }
}
