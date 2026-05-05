<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class ProductApi
{
    private ProductService $service;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new ProductService($db, $franchiseCode, $auth);
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
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
            $request->get('category_syscode') !== null
                ? (string) $request->get('category_syscode')
                : null,
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
            'category_ids'   => $request->get('category_ids'),
        ]);
        Response::created($this->service->get($id), 'Product created');
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
            'category_ids'   => $request->get('category_ids'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'Product updated');
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
            'category_ids'   => $request->get('category_ids'),
        ]);
        Response::success($this->service->get((int) $params['id']), 'Product replaced');
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

    public function registerRoutes(Router $router): void
    {
        $router->get('/', [$this, 'list']);
        $router->post('/', [$this, 'create']);
        $router->get('/:id', [$this, 'get']);
        $router->put('/:id', [$this, 'replace']);
        $router->patch('/:id', [$this, 'update']);
        $router->delete('/:id', [$this, 'delete']);
        $router->patch('/:id/stock', [$this, 'adjustStock']);
    }
}
