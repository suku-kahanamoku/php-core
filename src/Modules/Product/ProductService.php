<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class ProductService extends BaseService
{
    private ProductRepository $_product;

    /**
     * Konstruktor tridy ProductService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_product = new ProductRepository($db, $franchiseCode);
        $this->_auth    = $auth;
    }

    /**
     * Vrati strankovany seznam produktu. Verejne dostupne.
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
        return $this->_product->findAll(
            $page,
            $limit,
            $sort,
            $filter,
            $projection,
        );
    }

    /**
     * Vrati produkt dle ID. Verejne dostupne.
     * Pokud produkt neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $product = $this->_product->findById($id, $projection);
        $this->_requireEntity($product, 'Product not found');

        return $product;
    }

    /**
     * Vytvori novy produkt. Vyzaduje roli admin.
     *
     * @param  array{name: string, price: float|int, sku?: string, description?: string, stock_quantity?: int, published?: int, kind?: string, color?: string, variant?: string, data?: array<string, mixed>, category_ids?: list<int>} $input
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function create(array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        $name  = trim((string) ($input['name'] ?? ''));
        $price = $input['price'] ?? null;
        $sku   = !empty($input['sku'])
            ? trim((string) $input['sku'])
            : $this->_product->generateSku();

        $categoryIds = array_map('intval', (array) ($input['category_ids'] ?? []));
        $fileIds     = array_map('intval', (array) ($input['file_ids'] ?? []));

        $created = $this->_product->create([
            'sku'            => $sku,
            'name'           => $name,
            'description'    => (string) ($input['description'] ?? ''),
            'price'          => (float) $price,
            'stock_quantity' => (int) ($input['stock_quantity'] ?? 0),
            'published'      => isset($input['published'])
                ? (int) $input['published'] : 1,
            'kind'           => isset($input['kind'])
                ? trim((string) $input['kind']) : null,
            'color'          => isset($input['color'])
                ? trim((string) $input['color']) : null,
            'variant'        => isset($input['variant'])
                ? trim((string) $input['variant']) : null,
            'data'           => isset($input['data']) && is_array($input['data'])
                ? $input['data'] : null,
        ]);

        $id = $created['id'];
        if ($categoryIds) {
            $this->_product->syncCategories($id, $categoryIds);
        }
        if ($fileIds) {
            $this->_product->syncFiles($id, $fileIds);
        }

        return $this->_product->findById($id, $projection) ?? $created;
    }

    /**
     * Aktualizuje existujici produkt (castecna aktualizace). Vyzaduje roli admin.
     * Pole data jsou mergovana s existujicimi; null data pole maze.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input  Libovolna podmnozina sloupcu produktu + category_ids, data
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_product->findById($id), 'Product not found');

        $set         = [];
        $textFields  = ['sku', 'name', 'description', 'kind', 'color', 'variant'];
        $floatFields = ['price'];
        $intFields   = ['stock_quantity', 'published'];

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
            $this->_product->update($id, $set);
        }

        if (
            array_key_exists('category_ids', $input) &&
            is_array($input['category_ids'])
        ) {
            $this->_product->syncCategories(
                $id,
                array_map('intval', $input['category_ids'])
            );
        }

        if (
            array_key_exists('file_ids', $input) &&
            is_array($input['file_ids'])
        ) {
            $this->_product->syncFiles(
                $id,
                array_map('intval', $input['file_ids'])
            );
        }

        if (array_key_exists('data', $input)) {
            if (is_array($input['data']) && !empty($input['data'])) {
                $existing = $this->_product->findById($id);
                $current  = array_merge($existing['data'] ?? [], $input['data']);
                $this->_product->update($id, ['data' => $current]);
            } elseif ($input['data'] === null) {
                $this->_product->update($id, ['data' => null]);
            }
        }

        return $this->_product->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Plne nahradi produkt (uplna nahrada). Vyzaduje roli admin.
     * Vyzaduje name a price. SKU je volitelne, pokud neni zadano, vygeneruje se automaticky.
     *
     * @param  int        $id
     * @param  array{name: string, sku?: string, price: float|int, description?: string, stock_quantity?: int, published?: int, kind?: string, color?: string, variant?: string, data?: array<string, mixed>, category_ids?: list<int>} $input
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function replace(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_product->findById($id), 'Product not found');

        $name  = trim((string) ($input['name'] ?? ''));
        $sku   = !empty($input['sku'])
            ? trim((string) $input['sku'])
            : $this->_product->generateSku();
        $price = $input['price'] ?? null;

        $categoryIds = array_map('intval', (array) ($input['category_ids'] ?? []));
        $fileIds     = array_map('intval', (array) ($input['file_ids'] ?? []));

        $this->_product->update($id, [
            'name'           => $name,
            'sku'            => $sku,
            'price'          => (float) $price,
            'description'    => (string) ($input['description'] ?? ''),
            'stock_quantity' => isset($input['stock_quantity'])
                ? (int) $input['stock_quantity'] : 0,
            'published'      => isset($input['published'])
                ? (int) $input['published'] : 1,
            'kind'           => isset($input['kind'])
                ? trim((string) $input['kind']) : null,
            'color'          => isset($input['color'])
                ? trim((string) $input['color']) : null,
            'variant'        => isset($input['variant'])
                ? trim((string) $input['variant']) : null,
            'data'           => isset($input['data']) && is_array($input['data'])
                ? $input['data'] : null,
        ]);

        $this->_product->syncCategories($id, $categoryIds);
        $this->_product->syncFiles($id, $fileIds);

        return $this->_product->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze produkt. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_product->findById($id), 'Product not found');

        return $this->_product->hardDelete($id);
    }

    /**
     * Soft-smazani produktu (oznaci jako smazany, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_product->findById($id), 'Product not found');

        return $this->_product->softDelete($id);
    }

    /**
     * Upravi mnozstvi na sklade o zadanou hodnotu (kladna = pridat, zaporna = odebrat).
     * Mnozstvi nemuze klesnout pod 0. Vyzaduje roli admin.
     *
     * @param  int $id
     * @param  int $delta
     * @return int  Nove mnozstvi na sklade
     */
    public function adjustStock(int $id, int $delta): int
    {
        $this->_auth->requireRole('admin');

        $newQty = $this->_product->adjustStock($id, $delta);

        if ($newQty === -1) {
            Response::notFound('Product not found');
        }
        if ($newQty < 0) {
            Response::error('Insufficient stock', 422);
        }

        return $newQty;
    }
}
