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

    /**
     * GET /products — Vrati strankovany seznam produktu. Verejne dostupne.
     *
     * @param Request $request  query: page, limit, search, category_id, sort, filter, category_syscode, projection
     * @return void
     */
    public function list(Request $request): void
    {
        Response::success($this->service->list(
            max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('limit', 20))),
            $request->get('search'),
            $request->get('category_id') !== null ? (int) $request->get('category_id') : null,
            (string) $request->get('sort', ''),
            (string) $request->get('filter', ''),
            $request->get('category_syscode') !== null ? (string) $request->get('category_syscode') : null,
            $request->projection(),
        ));
    }

    /**
     * GET /products/:id — Vrati produkt dle ID. Verejne dostupne.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id'], $request->projection()));
    }

    /**
     * POST /products — Vytvori novy produkt. Vyzaduje roli admin.
     *
     * @param Request $request  body: name (required), price (required), sku, description, vat_rate, stock_quantity, is_active, kind, color, variant, data, category_ids
     * @return void
     */
    public function create(Request $request): void
    {
        $product = $this->service->create([
            'name'           => $request->get('name'),
            'sku'            => $request->get('sku'),
            'description'    => $request->get('description'),
            'price'          => $request->get('price'),
            'vat_rate'       => $request->get('vat_rate'),
            'stock_quantity' => $request->get('stock_quantity'),
            'is_active'      => $request->get('is_active'),
            'kind'           => $request->get('kind'),
            'color'          => $request->get('color'),
            'variant'        => $request->get('variant'),
            'data'           => $request->get('data'),
            'category_ids'   => $request->get('category_ids'),
        ], $request->projection());
        Response::created($product, 'Product created');
    }

    /**
     * PATCH /products/:id — Castecna aktualizace produktu. Vyzaduje roli admin.
     *
     * @param Request $request  body: libovolna podmnozina sloupcu produktu + category_ids, data
     * @param array{id: string} $params
     * @return void
     */
    public function update(Request $request, array $params): void
    {
        $product = $this->service->update((int) $params['id'], [
            'sku'            => $request->get('sku'),
            'name'           => $request->get('name'),
            'description'    => $request->get('description'),
            'price'          => $request->get('price'),
            'vat_rate'       => $request->get('vat_rate'),
            'stock_quantity' => $request->get('stock_quantity'),
            'is_active'      => $request->get('is_active'),
            'kind'           => $request->get('kind'),
            'color'          => $request->get('color'),
            'variant'        => $request->get('variant'),
            'data'           => $request->get('data'),
            'category_ids'   => $request->get('category_ids'),
        ], $request->projection());
        Response::success($product, 'Product updated');
    }

    /**
     * PUT /products/:id — Uplna nahrada produktu. Vyzaduje roli admin.
     *
     * @param Request $request  body: name, sku, price (required), ostatni volitelne
     * @param array{id: string} $params
     * @return void
     */
    public function replace(Request $request, array $params): void
    {
        $product = $this->service->replace((int) $params['id'], [
            'name'           => $request->get('name'),
            'sku'            => $request->get('sku'),
            'price'          => $request->get('price'),
            'description'    => $request->get('description'),
            'vat_rate'       => $request->get('vat_rate'),
            'stock_quantity' => $request->get('stock_quantity'),
            'is_active'      => $request->get('is_active'),
            'kind'           => $request->get('kind'),
            'color'          => $request->get('color'),
            'variant'        => $request->get('variant'),
            'data'           => $request->get('data'),
            'category_ids'   => $request->get('category_ids'),
        ], $request->projection());
        Response::success($product, 'Product replaced');
    }

    /**
     * DELETE /products/:id — Smaze produkt. Vyzaduje roli admin.
     *
     * @param Request $request
     * @param array{id: string} $params
     * @return void
     */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Product deleted');
    }

    /**
     * PATCH /products/:id/stock — Upravi mnozstvi na sklade. Vyzaduje roli admin.
     *
     * @param Request $request  body: quantity (pozitivni = pridat, negativni = odebrat)
     * @param array{id: string} $params
     * @return void
     */
    public function adjustStock(Request $request, array $params): void
    {
        $newQty = $this->service->adjustStock(
            (int) $params['id'],
            (int) $request->get('quantity', 0),
        );
        Response::success(['stock_quantity' => $newQty], 'Stock adjusted');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     */
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
