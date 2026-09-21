<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use App\Modules\Auth\Auth;
use App\Modules\CustomerProfile\CustomerProfileRepository;
use App\Modules\Database\Database;
use App\Modules\Product\ProductService;

/** Strankovany databazovy zdroj publikovanych tenantovych dat pro AI nastroje. */
final class OpenAiCatalogRepository implements OpenAiCatalogGateway
{
    private const PAGE_SIZE = 100;

    private CustomerProfileRepository $profiles;
    private ProductService $products;

    /**
     * Pripravi tenantove omezene existujici repozitare a verejnou produktovou sluzbu.
     *
     * @param Database $db Sdilene databazove spojeni.
     * @param string $franchiseCode Tenant vyreseny z duveryhodne HTTP hlavicky.
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->profiles = new CustomerProfileRepository($db, $franchiseCode);
        $this->products = new ProductService($db, $franchiseCode, new Auth($db));
    }

    /** @inheritDoc */
    public function publishedProfiles(): iterable
    {
        $page = 1;
        do {
            $result = $this->profiles->findAll(
                $page,
                self::PAGE_SIZE,
                json_encode([['position' => 1]], JSON_THROW_ON_ERROR),
                json_encode(['published' => ['value' => 1]], JSON_THROW_ON_ERROR),
            );
            $items = array_values($result['data'] ?? []);
            yield from $items;
            $page++;
        } while (count($items) === self::PAGE_SIZE);
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
}
