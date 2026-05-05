<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class ProductService
{
    private ProductRepository $product;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->product = new ProductRepository($db, $franchiseCode);
        $this->auth    = $auth;
    }

    public function list(
        int $page,
        int $limit,
        ?string $search,
        ?int $categoryId,
        string $sort = '',
        string $filter = '',
    ): array {
        return $this->product->findAll(
            $page,
            $limit,
            $search,
            $categoryId,
            $sort,
            $filter,
        );
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
        $this->auth->requireRole('admin');

        VALIDATOR($input)->required('name')->numeric('price', 0)->validate();

        $name  = trim((string) ($input['name'] ?? ''));
        $price = $input['price'] ?? null;
        $sku   = !empty($input['sku'])
            ? trim((string) $input['sku'])
            : $this->product->generateSku();

        $categoryIds = array_map('intval', (array) ($input['category_ids'] ?? []));

        $id = $this->product->create([
            'sku'            => $sku,
            'name'           => $name,
            'description'    => (string) ($input['description'] ?? ''),
            'price'          => (float) $price,
            'vat_rate'       => (float) ($input['vat_rate'] ?? 21),
            'stock_quantity' => (int) ($input['stock_quantity'] ?? 0),
        ]);

        if ($categoryIds) {
            $this->product->syncCategories($id, $categoryIds);
        }

        return $id;
    }

    public function update(int $id, array $input): void
    {
        $this->auth->requireRole('admin');

        if (!$this->product->findById($id)) {
            Response::notFound('Product not found');
        }

        $set         = [];
        $textFields  = ['sku', 'name', 'description'];
        $floatFields = ['price', 'vat_rate'];
        $intFields   = ['stock_quantity'];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }
        foreach ($floatFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = (float) $input[$f];
            }
        }
        foreach ($intFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = (int) $input[$f];
            }
        }

        if (!empty($set)) {
            $this->product->update($id, $set);
        }

        if (array_key_exists('category_ids', $input) && is_array($input['category_ids'])) {
            $this->product->syncCategories($id, array_map('intval', $input['category_ids']));
        }
    }

    public function replace(int $id, array $input): void
    {
        $this->auth->requireRole('admin');

        if (!$this->product->findById($id)) {
            Response::notFound('Product not found');
        }

        VALIDATOR($input)
            ->required(['name', 'sku'])
            ->numeric('price', 0)
            ->validate();

        $name  = trim((string) ($input['name'] ?? ''));
        $sku   = trim((string) ($input['sku'] ?? ''));
        $price = $input['price'] ?? null;

        $categoryIds = array_map('intval', (array) ($input['category_ids'] ?? []));

        $this->product->update($id, [
            'name'        => $name,
            'sku'         => $sku,
            'price'       => (float) $price,
            'description' => (string) ($input['description'] ?? ''),
            'vat_rate'    => isset($input['vat_rate'])
                ? (float) $input['vat_rate']
                : 21.0,
            'stock_quantity' => isset($input['stock_quantity'])
                ? (int) $input['stock_quantity']
                : 0,
        ]);

        $this->product->syncCategories($id, $categoryIds);
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

        if (!$this->product->findById($id)) {
            Response::notFound('Product not found');
        }

        $this->product->delete($id);
    }

    public function adjustStock(int $id, int $delta): int
    {
        $this->auth->requireRole('admin');

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
