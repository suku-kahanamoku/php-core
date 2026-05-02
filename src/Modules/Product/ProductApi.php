<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Router\Request;
use App\Modules\Router\Response;

class ProductApi
{
    private ProductService $service;

    public function __construct()
    {
        $this->service = new ProductService();
    }

    /** GET /products */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            $request->get('search'),
            $request->get('category_id') !== null
                ? (int) $request->get('category_id')
                : null,
            $request->get('status'),
            (string) $request->get('sort_by', 'created_at'),
            (string) $request->get('sort_dir', 'DESC'),
        ));
    }

    /** GET /products/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /products */
    public function create(Request $request): void
    {
        $id = $this->service->create([
            'name'           => $request->get('name'),
            'sku'            => $request->get('sku'),
            'description'    => $request->get('description'),
            'price'          => $request->get('price'),
            'vat_rate'       => $request->get('vat_rate'),
            'stock_quantity' => $request->get('stock_quantity'),
            'category_id'    => $request->get('category_id'),
            'status'         => $request->get('status'),
        ]);
        Response::created(['id' => $id], 'Product created');
    }

    /** PATCH /products/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'sku'            => $request->get('sku'),
            'name'           => $request->get('name'),
            'description'    => $request->get('description'),
            'price'          => $request->get('price'),
            'vat_rate'       => $request->get('vat_rate'),
            'stock_quantity' => $request->get('stock_quantity'),
            'category_id'    => $request->get('category_id'),
            'status'         => $request->get('status'),
        ]);
        Response::success(null, 'Product updated');
    }

    /** PUT /products/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace((int) $params['id'], [
            'name'           => $request->get('name'),
            'sku'            => $request->get('sku'),
            'price'          => $request->get('price'),
            'description'    => $request->get('description'),
            'vat_rate'       => $request->get('vat_rate'),
            'stock_quantity' => $request->get('stock_quantity'),
            'status'         => $request->get('status'),
            'category_id'    => $request->get('category_id'),
        ]);
        Response::success(null, 'Product replaced');
    }

    /** DELETE /products/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Product deleted');
    }

    /** PATCH /products/:id/stock */
    public function adjustStock(Request $request, array $params): void
    {
        $newQty = $this->service->adjustStock(
            (int) $params['id'],
            (int) $request->get('quantity', 0),
        );
        Response::success(['stock_quantity' => $newQty], 'Stock adjusted');
    }
}
