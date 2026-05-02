<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Core\Franchise;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class ProductService
{
    private Product $product;

    public function __construct()
    {
        $this->product = new Product(Database::getInstance(), Franchise::code());
    }

    public function list(
        int $page, int $limit,
        ?string $search, ?int $categoryId, ?string $status,
        string $sortBy, string $sortDir
    ): array {
        return $this->product->findAll($page, $limit, $search, $categoryId, $status, $sortBy, $sortDir);
    }

    public function get(int $id): array
    {
        $product = $this->product->findById($id);
        if (!$product) {
            Response::notFound('Product not found');
        }

        return $product;
    }

    public function create(array $input): int
    {
        Auth::requireRole('admin');

        Validator::make($input)->required('name')->numeric('price', 0)->validate();

        $name  = trim((string) ($input['name'] ?? ''));
        $price = $input['price'] ?? null;
        $sku = !empty($input['sku']) ? trim((string) $input['sku']) : $this->product->generateSku();

        return $this->product->create([
            'sku'            => $sku,
            'name'           => $name,
            'description'    => (string) ($input['description'] ?? ''),
            'price'          => (float) $price,
            'vat_rate'       => (float) ($input['vat_rate'] ?? 21),
            'stock_quantity' => (int) ($input['stock_quantity'] ?? 0),
            'category_id'    => isset($input['category_id']) ? (int) $input['category_id'] : null,
            'status'         => (string) ($input['status'] ?? 'active'),
        ]);
    }

    public function update(int $id, array $input): void
    {
        Auth::requireRole('admin');

        if (!$this->product->findById($id)) {
            Response::notFound('Product not found');
        }

        $set = [];
        foreach (['sku', 'name', 'description', 'status'] as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }
        foreach (['price', 'vat_rate'] as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = (float) $input[$f];
            }
        }
        foreach (['stock_quantity', 'category_id'] as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = (int) $input[$f];
            }
        }

        if (!empty($set)) {
            $this->product->update($id, $set);
        }
    }

    public function replace(int $id, array $input): void
    {
        Auth::requireRole('admin');

        if (!$this->product->findById($id)) {
            Response::notFound('Product not found');
        }

        Validator::make($input)->required(['name', 'sku'])->numeric('price', 0)->validate();

        $name  = trim((string) ($input['name']  ?? ''));
        $sku   = trim((string) ($input['sku']   ?? ''));
        $price = $input['price'] ?? null;

        $this->product->update($id, [
            'name'           => $name,
            'sku'            => $sku,
            'price'          => (float) $price,
            'description'    => (string) ($input['description']    ?? ''),
            'vat_rate'       => isset($input['vat_rate'])       ? (float) $input['vat_rate']       : 21.0,
            'stock_quantity' => isset($input['stock_quantity']) ? (int)   $input['stock_quantity'] : 0,
            'status'         => (string) ($input['status']         ?? 'active'),
            'category_id'    => isset($input['category_id']) && $input['category_id'] !== null ? (int) $input['category_id'] : null,
        ]);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');

        if (!$this->product->findById($id)) {
            Response::notFound('Product not found');
        }

        $this->product->softDelete($id);
    }

    public function adjustStock(int $id, int $delta): int
    {
        Auth::requireRole('admin');

        $newQty = $this->product->adjustStock($id, $delta);

        if ($newQty === -1) {
            Response::notFound('Product not found');
        }
        if ($newQty < 0) {
            Response::error('Insufficient stock', 422);
        }

        return $newQty;
    }
}
