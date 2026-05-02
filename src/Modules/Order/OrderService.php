<?php

declare(strict_types=1);

namespace App\Modules\Order;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class OrderService
{
    private Order $order;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->order = new Order($db, $franchiseCode);
    }

    public function list(
        int $page,
        int $limit,
        ?string $status,
        string $sortBy,
        string $sortDir,
    ): array {
        Auth::require();

        $userId = Auth::hasRole('admin') ? null : Auth::id();

        return $this->order->findAll($page, $limit, $userId, $status, $sortBy, $sortDir);
    }

    public function listForUser(
        int $page,
        int $limit,
        int $userId,
        ?string $status,
        string $sortBy,
        string $sortDir,
    ): array {
        Auth::requireRole('admin');

        return $this->order->findAll($page, $limit, $userId, $status, $sortBy, $sortDir);
    }

    public function get(int $id): array
    {
        Auth::require();

        $order = $this->order->findById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        if (!Auth::hasRole('admin') && (int) $order['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        return $order;
    }

    public function create(array $items, string $currency, array $input): array
    {
        Auth::require();

        if (empty($items)) {
            Response::validationError(['items' => 'At least one item required']);
        }

        $userId = Auth::id();
        $pdo    = $this->order->getPdo();

        $pdo->beginTransaction();

        try {
            $totalAmount   = 0;
            $preparedItems = [];

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $qty       = (int) ($item['quantity'] ?? 1);

                if ($productId <= 0 || $qty <= 0) {
                    throw new \InvalidArgumentException(
                        "Invalid item: product_id={$productId}, quantity={$qty}",
                    );
                }

                $product = $this->order->getProduct($productId);

                if (!$product) {
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

            $orderId = $this->order->create([
                'order_number'        => $this->order->generateNumber(),
                'user_id'             => $userId,
                'status'              => 'pending',
                'total_amount'        => $totalAmount,
                'currency'            => $currency,
                'payment_method'      => $input['payment_method'] ?? 'bank_transfer',
                'note'                => $input['note']           ?? '',
                'shipping_address_id' => isset($input['shipping_address_id'])
                    ? (int) $input['shipping_address_id']
                    : null,
                'billing_address_id' => isset($input['billing_address_id'])
                    ? (int) $input['billing_address_id']
                    : null,
            ]);

            foreach ($preparedItems as $item) {
                $this->order->createItem(array_merge($item, ['order_id' => $orderId]));
                $this->order->decreaseStock($item['product_id'], $item['quantity']);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 422);
        }

        return ['id' => $orderId ?? null, 'total_amount' => $totalAmount ?? 0];
    }

    public function updateStatus(int $id, string $status): void
    {
        Auth::requireRole('admin');

        Validator::make(['status' => $status])->required('status')->validate();

        $order = $this->order->findById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $this->order->updateStatus($id, $status);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');

        $order = $this->order->findById($id);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $this->order->softDelete($id);
    }
}
