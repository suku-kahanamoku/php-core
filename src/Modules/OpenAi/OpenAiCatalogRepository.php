<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use App\Modules\Auth\Auth;
use App\Modules\CustomerProfile\CustomerProfileRepository;
use App\Modules\Database\Database;
use App\Modules\Product\ProductService;

/** Databazovy zdroj publikovanych FAnn profilu a produktu pro AI nastroje. */
final class OpenAiCatalogRepository implements OpenAiCatalogGateway
{
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
    public function publishedProfiles(): array
    {
        $result = $this->profiles->findAll(
            1,
            100,
            json_encode([['position' => 1]], JSON_THROW_ON_ERROR),
            json_encode(['published' => ['value' => 1]], JSON_THROW_ON_ERROR),
        );
        return array_values($result['data'] ?? []);
    }

    /** @inheritDoc */
    public function publishedProducts(): array
    {
        $result = $this->products->list(1, 100, '', '', [
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
            'profile_probabilities',
            'alternatives',
        ]);
        return array_values($result['data'] ?? []);
    }
}
