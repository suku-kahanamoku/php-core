<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Product\ProductService;

/** Strankovany databazovy zdroj publikovanych tenantovych dat pro AI nastroje. */
final class OpenAiCatalogRepository implements OpenAiCatalogGateway
{
    private const PAGE_SIZE = 100;

    private ProductService $products;

    /**
     * Pripravi tenantove omezene existujici repozitare a verejnou produktovou sluzbu.
     *
     * @param Database $db Sdilene databazove spojeni.
     * @param string $franchiseCode Tenant vyreseny z duveryhodne HTTP hlavicky.
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->products = new ProductService($db, $franchiseCode, new Auth($db));
    }

    /** @inheritDoc */
    public function publishedProducts(): iterable
    {
        $page = 1;
        do {
            $result = $this->products->list($page, self::PAGE_SIZE, '', '', [
                'id',
                'sku',
                'name',
                'description',
                'price_with_vat',
                'stock_quantity',
                'published',
                'kind',
                'color',
                'variant',
                'data',
                'categories',
                'alternatives',
            ]);
            $items = array_values($result['data'] ?? []);
            yield from $items;
            $page++;
        } while (count($items) === self::PAGE_SIZE);
    }

    /** @inheritDoc */
    public function publishedProduct(int $productId): ?array
    {
        $result = $this->products->list(1, 1, '', json_encode([
            'id' => ['value' => $productId],
        ], JSON_THROW_ON_ERROR), [
            'id',
            'sku',
            'name',
            'description',
            'price_with_vat',
            'stock_quantity',
            'kind',
            'color',
            'variant',
            'data',
            'categories',
            'alternatives',
        ]);
        $items = array_values($result['data'] ?? []);
        return isset($items[0]) && is_array($items[0]) ? $items[0] : null;
    }
}
