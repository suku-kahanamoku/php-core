<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Validuje AI tool cally a vraci pouze omezeny read-only FAnn katalog.
 *
 * Model nikdy nedostava pristup k obecným admin endpointum. Sluzba povoluje
 * pouze tri pojmenovane operace, filtruje publikovane zaznamy a omezuje pocet
 * produktu predanych zpet do Realtime kontextu.
 */
final class OpenAiCatalogService
{
    public const LIST_PROFILES   = 'list_customer_profiles';
    public const SEARCH_PRODUCTS = 'search_products';
    public const GET_PRODUCT     = 'get_product';

    /** @param OpenAiCatalogGateway $catalog Tenantovy zdroj publikovanych dat. */
    public function __construct(private OpenAiCatalogGateway $catalog) {}

    /**
     * Provede jeden povoleny nastroj s neduveryhodnymi argumenty od modelu.
     *
     * @param string $name Jmeno funkce deklarovane v Realtime relaci.
     * @param array<string, mixed> $arguments JSON argumenty vygenerovane modelem.
     * @return array<string, mixed> Kompaktni JSON serializovatelny vysledek.
     * @throws \InvalidArgumentException Pri neznamem nastroji nebo neplatnych argumentech.
     */
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            self::LIST_PROFILES   => $this->listProfiles(),
            self::SEARCH_PRODUCTS => $this->searchProducts($arguments),
            self::GET_PRODUCT     => $this->getProduct($arguments),
            default               => throw new \InvalidArgumentException('Unknown AI catalog tool.'),
        };
    }

    /** @return array{profiles:list<array<string, mixed>>} Verejne profilove podklady. */
    private function listProfiles(): array
    {
        $profiles = array_map(fn(array $profile): array => $this->publicProfile($profile), $this->catalog->publishedProfiles());
        return ['profiles' => $profiles];
    }

    /**
     * Filtruje katalog podle pevnych omezeni a radi jej podle profilu a textove shody.
     *
     * @param array<string, mixed> $arguments profile_id, query, max_price, category a limit.
     * @return array{products:list<array<string, mixed>>,count:int}
     */
    private function searchProducts(array $arguments): array
    {
        $profileId = $this->optionalPositiveInt($arguments, 'profile_id');
        $query     = $this->limitedText($arguments, 'query', 200);
        $category  = $this->limitedText($arguments, 'category', 100);
        $maxPrice  = $this->optionalPositiveFloat($arguments, 'max_price');
        $limit     = $this->optionalPositiveInt($arguments, 'limit') ?? 3;
        if ($limit > 5) {
            throw new \InvalidArgumentException('Product result limit must not exceed 5.');
        }
        if ($profileId === null && $query === '' && $category === '' && $maxPrice === null) {
            throw new \InvalidArgumentException('At least one product search criterion is required.');
        }

        $ranked = [];
        foreach ($this->catalog->publishedProducts() as $product) {
            $price = (float) ($product['price_with_vat'] ?? 0);
            if ($maxPrice !== null && $price > $maxPrice) {
                continue;
            }
            if ($category !== '' && !$this->contains($this->categoryText($product), $category)) {
                continue;
            }
            $probability = $this->profileProbability($product, $profileId);
            $relevance   = $this->relevance($product, $query);
            $ranked[]    = [
                'score'       => $probability + ($relevance * 25),
                'probability' => $probability,
                'relevance'   => $relevance,
                'product'     => $product,
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            return [$right['score'], $right['probability'], $right['relevance'], -(int) ($right['product']['id'] ?? 0)]
                <=> [$left['score'], $left['probability'], $left['relevance'], -(int) ($left['product']['id'] ?? 0)];
        });
        $products = array_map(
            fn(array $entry): array => $this->publicProduct($entry['product'], $profileId),
            array_slice($ranked, 0, $limit),
        );
        return ['products' => $products, 'count' => count($products)];
    }

    /**
     * Najde publikovany detail podle ID bez zpristupneni obecneho produktoveho API.
     *
     * @param array<string, mixed> $arguments Povinne product_id.
     * @return array{product:array<string, mixed>|null}
     */
    private function getProduct(array $arguments): array
    {
        $productId = $this->optionalPositiveInt($arguments, 'product_id');
        if ($productId === null) {
            throw new \InvalidArgumentException('product_id is required.');
        }
        foreach ($this->catalog->publishedProducts() as $product) {
            if ((int) ($product['id'] ?? 0) === $productId) {
                return ['product' => $this->publicProduct($product, null, true)];
            }
        }
        return ['product' => null];
    }

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    private function publicProfile(array $profile): array
    {
        return $this->pick($profile, [
            'id',
            'profile_number',
            'syscode',
            'name',
            'selection_need',
            'summary',
            'behavior',
            'business_potential',
            'typical_quote',
            'average_basket',
            'questions',
            'objections',
            'preferences',
        ]);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function publicProduct(array $product, ?int $profileId, bool $detail = false): array
    {
        $result = $this->pick($product, [
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
            'profile_probabilities',
        ]);
        if (!$detail) {
            unset($result['description'], $result['alternatives'], $result['profile_probabilities']);
        }
        if ($profileId !== null) {
            $result['profile_probability'] = $this->profileProbability($product, $profileId);
        }
        return $result;
    }

    /** @param array<string, mixed> $source @param list<string> $keys @return array<string, mixed> */
    private function pick(array $source, array $keys): array
    {
        return array_intersect_key($source, array_fill_keys($keys, true));
    }

    /** @param array<string, mixed> $product */
    private function profileProbability(array $product, ?int $profileId): int
    {
        if ($profileId === null) {
            return 0;
        }
        foreach (($product['profile_probabilities'] ?? []) as $probability) {
            if (is_array($probability) && (int) ($probability['customer_profile_id'] ?? 0) === $profileId) {
                return min(100, max(0, (int) ($probability['probability_percent'] ?? 0)));
            }
        }
        return 0;
    }

    /** @param array<string, mixed> $product */
    private function relevance(array $product, string $query): int
    {
        if ($query === '') {
            return 0;
        }
        $haystack = $this->normalize(implode(' ', [
            (string) ($product['sku'] ?? ''),
            (string) ($product['name'] ?? ''),
            (string) ($product['description'] ?? ''),
            (string) ($product['kind'] ?? ''),
            (string) ($product['color'] ?? ''),
            (string) ($product['variant'] ?? ''),
            $this->categoryText($product),
            json_encode($product['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));
        $tokens = preg_split('/\s+/u', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return count(array_filter($tokens, fn(string $token): bool => $this->textLength($token) >= 2 && str_contains($haystack, $token)));
    }

    /** @param array<string, mixed> $product */
    private function categoryText(array $product): string
    {
        return implode(' ', array_map(
            static fn(mixed $category): string => is_array($category) ? (string) ($category['name'] ?? '') : '',
            is_array($product['categories'] ?? null) ? $product['categories'] : [],
        ));
    }

    private function contains(string $haystack, string $needle): bool
    {
        return str_contains($this->normalize($haystack), $this->normalize($needle));
    }

    private function normalize(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        return function_exists('mb_strtolower') ? mb_strtolower($normalized) : strtolower($normalized);
    }

    /** @param array<string, mixed> $arguments */
    private function optionalPositiveInt(array $arguments, string $key): ?int
    {
        if (!array_key_exists($key, $arguments) || $arguments[$key] === null || $arguments[$key] === '') {
            return null;
        }
        $value = filter_var($arguments[$key], FILTER_VALIDATE_INT);
        if ($value === false || $value < 1) {
            throw new \InvalidArgumentException("{$key} must be a positive integer.");
        }
        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function optionalPositiveFloat(array $arguments, string $key): ?float
    {
        if (!array_key_exists($key, $arguments) || $arguments[$key] === null || $arguments[$key] === '') {
            return null;
        }
        if (!is_numeric($arguments[$key]) || (float) $arguments[$key] <= 0) {
            throw new \InvalidArgumentException("{$key} must be a positive number.");
        }
        return (float) $arguments[$key];
    }

    /** @param array<string, mixed> $arguments */
    private function limitedText(array $arguments, string $key, int $maxLength): string
    {
        $value = trim((string) ($arguments[$key] ?? ''));
        if ($this->textLength($value) > $maxLength) {
            throw new \InvalidArgumentException("{$key} is too long.");
        }
        return $value;
    }

    /** Vrati Unicode delku s bezpecnym fallbackem pro instalaci bez mbstring. */
    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
