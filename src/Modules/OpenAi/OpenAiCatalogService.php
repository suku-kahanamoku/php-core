<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Validuje AI tool cally a vraci pouze omezeny read-only tenantovy katalog.
 *
 * Model nikdy nedostava pristup k obecným admin endpointum. Sluzba povoluje
 * pouze pojmenovane katalogove operace, filtruje publikovane zaznamy a omezuje
 * pocet produktu predanych zpet do kontextu.
 */
final class OpenAiCatalogService
{
    public const LIST_PROFILES   = 'list_customer_profiles';
    public const SEARCH_PRODUCTS = 'search_products';
    public const GET_PRODUCT     = 'get_product';
    public const MAX_EXCLUDED_PRODUCTS = 50;
    public const MAX_SEARCH_RESULTS = 5;
    public const MAX_SEARCH_ATTRIBUTES = 12;
    public const MAX_SEARCH_ATTRIBUTE_LENGTH = 80;

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
        $profiles = [];
        foreach ($this->catalog->publishedProfiles() as $profile) {
            $profiles[] = $this->publicProfile($profile);
        }
        return ['profiles' => $profiles];
    }

    /**
     * Filtruje katalog podle pevných omezení a řadí jej podle atributů a textové shody.
     *
     * @param array<string, mixed> $arguments Potřeby, omezení, limit a vyloučená produktová ID.
     * @return array{status:string,products:list<array<string, mixed>>,count:int,
     *     eligible_count:int,catalog_scan_complete:bool}
     */
    private function searchProducts(array $arguments): array
    {
        $query     = $this->limitedText($arguments, 'query', 200);
        $category  = $this->limitedText($arguments, 'category', 100);
        $maxPrice  = $this->optionalPositiveFloat($arguments, 'max_price');
        $limit     = $this->optionalPositiveInt($arguments, 'limit') ?? 3;
        $legacyAttributes = $this->textList(
            $arguments,
            'attributes',
            self::MAX_SEARCH_ATTRIBUTES,
            self::MAX_SEARCH_ATTRIBUTE_LENGTH,
        );
        $requiredAttributes = $this->textList(
            $arguments,
            'required_attributes',
            self::MAX_SEARCH_ATTRIBUTES,
            self::MAX_SEARCH_ATTRIBUTE_LENGTH,
        );
        $preferredAttributes = $this->mergeTextLists(
            $this->textList(
                $arguments,
                'preferred_attributes',
                self::MAX_SEARCH_ATTRIBUTES,
                self::MAX_SEARCH_ATTRIBUTE_LENGTH,
            ),
            $legacyAttributes,
        );
        $negativePreferences = $this->textList(
            $arguments,
            'negative_preferences',
            self::MAX_SEARCH_ATTRIBUTES,
            self::MAX_SEARCH_ATTRIBUTE_LENGTH,
        );
        $excludedAttributes = $this->textList(
            $arguments,
            'excluded_attributes',
            self::MAX_SEARCH_ATTRIBUTES,
            self::MAX_SEARCH_ATTRIBUTE_LENGTH,
        );
        $rejectedProductIds = $this->mergePositiveIntLists(
            $this->positiveIntList(
                $arguments,
                'rejected_product_ids',
                self::MAX_EXCLUDED_PRODUCTS,
            ),
            $this->positiveIntList(
                $arguments,
                'excluded_product_ids',
                self::MAX_EXCLUDED_PRODUCTS,
            ),
        );
        $displayedProductIds = $this->positiveIntList(
            $arguments,
            'displayed_product_ids',
            self::MAX_EXCLUDED_PRODUCTS,
        );
        if ($limit > self::MAX_SEARCH_RESULTS) {
            throw new \InvalidArgumentException('Product result limit must not exceed 5.');
        }
        if (
            $query === '' && $category === '' && $maxPrice === null
            && $requiredAttributes === [] && $preferredAttributes === []
            && $negativePreferences === [] && $excludedAttributes === []
        ) {
            throw new \InvalidArgumentException('At least one product search criterion is required.');
        }

        $ranked = [];
        $eligibleCount = 0;
        foreach ($this->catalog->publishedProducts() as $product) {
            $productId = (int) ($product['id'] ?? 0);
            if (in_array($productId, $rejectedProductIds, true)) {
                continue;
            }
            $price = (float) ($product['price_with_vat'] ?? 0);
            if ($maxPrice !== null && $price > $maxPrice) {
                continue;
            }
            if ($category !== '' && !$this->contains($this->categoryText($product), $category)) {
                continue;
            }
            $searchableText = $this->searchableText($product);
            if ($this->matchedAttributes($searchableText, $excludedAttributes) !== []) {
                continue;
            }
            $matchedRequiredAttributes = $this->matchedAttributes($searchableText, $requiredAttributes);
            if (count($matchedRequiredAttributes) !== count($requiredAttributes)) {
                continue;
            }
            $matchedPreferredAttributes = $this->matchedAttributes($searchableText, $preferredAttributes);
            $negativePreferenceMatches = $this->matchedAttributes($searchableText, $negativePreferences);
            $relevance = $this->relevance($searchableText, $query);
            $eligibleCount++;
            $ranked[]    = [
                'was_displayed' => in_array($productId, $displayedProductIds, true),
                'preference_matches' => count($matchedPreferredAttributes),
                'negative_preference_matches' => count($negativePreferenceMatches),
                'matched_required_attributes' => $matchedRequiredAttributes,
                'matched_preferred_attributes' => $matchedPreferredAttributes,
                'negative_preference_conflicts' => $negativePreferenceMatches,
                'unmatched_preferred_attributes' => array_values(array_diff(
                    $preferredAttributes,
                    $matchedPreferredAttributes,
                )),
                'relevance' => $relevance,
                'attribute_richness' => $this->attributeRichness($product),
                'product' => $product,
            ];
            usort($ranked, self::compareRankedProducts(...));
            if (count($ranked) > $limit) {
                array_pop($ranked);
            }
        }
        $products = array_map(function (array $entry): array {
            $product = $this->publicSearchProduct($entry['product']);
            $product['was_displayed'] = $entry['was_displayed'];
            $product['matched_required_attributes'] = $entry['matched_required_attributes'];
            $product['matched_preferred_attributes'] = $entry['matched_preferred_attributes'];
            $product['negative_preference_conflicts'] = $entry['negative_preference_conflicts'];
            $product['unmatched_preferred_attributes'] = $entry['unmatched_preferred_attributes'];
            // Zachovává kompatibilitu se starší Android relací a dokumentací.
            $product['matched_attributes'] = $entry['matched_preferred_attributes'];
            $product['attribute_match_count'] = $entry['preference_matches'];
            return $product;
        }, $ranked);
        return [
            'status' => $eligibleCount > 0 ? 'candidates' : 'no_match',
            'products' => $products,
            'count' => count($products),
            'eligible_count' => $eligibleCount,
            'catalog_scan_complete' => true,
        ];
    }

    /**
     * Radi kandidaty od nejvyssi shody a pri remize stabilne podle nizsiho ID.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private static function compareRankedProducts(array $left, array $right): int
    {
        return [
            !$right['was_displayed'],
            $right['preference_matches'],
            -$right['negative_preference_matches'],
            $right['relevance'],
            $right['attribute_richness'],
            -(int) ($right['product']['id'] ?? 0),
        ] <=> [
            !$left['was_displayed'],
            $left['preference_matches'],
            -$left['negative_preference_matches'],
            $left['relevance'],
            $left['attribute_richness'],
            -(int) ($left['product']['id'] ?? 0),
        ];
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
        $category = $this->limitedText($arguments, 'category', 100);
        $maxPrice = $this->optionalPositiveFloat($arguments, 'max_price');
        $requiredAttributes = $this->textList(
            $arguments,
            'required_attributes',
            self::MAX_SEARCH_ATTRIBUTES,
            self::MAX_SEARCH_ATTRIBUTE_LENGTH,
        );
        $excludedAttributes = $this->textList(
            $arguments,
            'excluded_attributes',
            self::MAX_SEARCH_ATTRIBUTES,
            self::MAX_SEARCH_ATTRIBUTE_LENGTH,
        );
        foreach ($this->catalog->publishedProducts() as $product) {
            if ((int) ($product['id'] ?? 0) === $productId) {
                $searchableText = $this->searchableText($product);
                $matchedRequired = $this->matchedAttributes($searchableText, $requiredAttributes);
                $matchedExclusions = $this->matchedAttributes($searchableText, $excludedAttributes);
                $violations = [];
                if (count($matchedRequired) !== count($requiredAttributes)) {
                    $violations[] = 'required_attributes_unverified';
                }
                if ($matchedExclusions !== []) {
                    $violations[] = 'excluded_attribute_match';
                }
                if ($category !== '' && !$this->contains($this->categoryText($product), $category)) {
                    $violations[] = 'category_mismatch';
                }
                if ($maxPrice !== null && (float) ($product['price_with_vat'] ?? 0) > $maxPrice) {
                    $violations[] = 'max_price_exceeded';
                }
                if (!array_key_exists('stock_quantity', $product)) {
                    $violations[] = 'availability_unknown';
                } elseif ((int) $product['stock_quantity'] <= 0) {
                    $violations[] = 'out_of_stock';
                }
                if ($violations !== []) {
                    return [
                        'product' => null,
                        'verification' => [
                            'status' => 'ineligible',
                            'violations' => $violations,
                            'matched_required_attributes' => $matchedRequired,
                            'matched_excluded_attributes' => $matchedExclusions,
                        ],
                    ];
                }
                return [
                    'product' => $this->publicProduct($product, true),
                    'verification' => [
                        'status' => 'verified',
                        'violations' => [],
                        'matched_required_attributes' => $matchedRequired,
                        'matched_excluded_attributes' => [],
                    ],
                ];
            }
        }
        return [
            'product' => null,
            'verification' => ['status' => 'not_found', 'violations' => ['product_not_found']],
        ];
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
    private function publicProduct(array $product, bool $detail = false): array
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
        ]);
        if (!$detail) {
            unset($result['alternatives']);
        }
        return $result;
    }

    /**
     * Vrátí kompaktní kandidátní data potřebná pro rozhodnutí Realtime modelu.
     *
     * Detailní prodejní texty a alternativy se do každého hledání neposílají;
     * model dostane popis a normalizované výběrové atributy. Úplný objekt načte
     * až následným ověřovacím voláním `get_product`.
     *
     * @param array<string, mixed> $product Zdrojový produkt z tenantova katalogu.
     * @return array<string, mixed> Omezená data kandidáta bez profilových vazeb.
     */
    private function publicSearchProduct(array $product): array
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
            'categories',
        ]);
        $selectionAttributes = $product['data']['selection_attributes'] ?? null;
        if (is_array($selectionAttributes) && $selectionAttributes !== []) {
            $result['selection_attributes'] = $selectionAttributes;
        }
        return $result;
    }

    /** @param array<string, mixed> $source @param list<string> $keys @return array<string, mixed> */
    private function pick(array $source, array $keys): array
    {
        return array_intersect_key($source, array_fill_keys($keys, true));
    }

    private function relevance(string $haystack, string $query): int
    {
        if ($query === '') {
            return 0;
        }
        $tokens = preg_split('/\s+/u', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return count(array_filter($tokens, fn(string $token): bool => $this->textLength($token) >= 2 && str_contains($haystack, $token)));
    }

    /** @param array<string, mixed> $product */
    private function searchableText(array $product): string
    {
        return $this->normalize(implode(' ', [
            (string) ($product['sku'] ?? ''),
            (string) ($product['name'] ?? ''),
            (string) ($product['description'] ?? ''),
            (string) ($product['kind'] ?? ''),
            (string) ($product['color'] ?? ''),
            (string) ($product['variant'] ?? ''),
            $this->categoryText($product),
            json_encode($product['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));
    }

    /** @param list<string> $attributes @return list<string> */
    private function matchedAttributes(string $haystack, array $attributes): array
    {
        $matched = [];
        foreach ($attributes as $attribute) {
            $normalized = $this->normalize($attribute);
            $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $significantTokens = array_values(array_filter(
                $tokens,
                fn(string $token): bool => $this->textLength($token) >= 3,
            ));
            if (str_contains($haystack, $normalized) || ($significantTokens !== [] && count(array_filter(
                $significantTokens,
                static fn(string $token): bool => str_contains($haystack, $token),
            )) === count($significantTokens))) {
                $matched[] = $attribute;
            }
        }
        return $matched;
    }

    /** @param array<string, mixed> $product */
    private function attributeRichness(array $product): int
    {
        $attributes = $product['data']['selection_attributes'] ?? [];
        if (!is_array($attributes)) {
            return 0;
        }
        $count = 0;
        array_walk_recursive($attributes, static function (mixed $value) use (&$count): void {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $count++;
            }
        });
        return $count;
    }

    /** @param array<string, mixed> $product */
    private function categoryText(array $product): string
    {
        $categories = array_map(
            static fn(mixed $category): string => is_array($category) ? (string) ($category['name'] ?? '') : '',
            is_array($product['categories'] ?? null) ? $product['categories'] : [],
        );
        $selection = $product['data']['selection_attributes'] ?? [];
        $declaredCategories = is_array($selection) && is_array($selection['category'] ?? null)
            ? $selection['category']
            : [];
        return implode(' ', array_merge(
            $categories,
            [(string) ($product['kind'] ?? '')],
            array_map(static fn(mixed $value): string => is_scalar($value) ? (string) $value : '', $declaredCategories),
        ));
    }

    private function contains(string $haystack, string $needle): bool
    {
        return str_contains($this->normalize($haystack), $this->normalize($needle));
    }

    private function normalize(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($normalized) : strtolower($normalized);
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($ascii !== false) {
                return strtolower($ascii);
            }
        }
        return $normalized;
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

    /**
     * Validuje omezeny seznam kladnych ID a odstrani duplicity.
     *
     * @param array<string, mixed> $arguments Nedůveryhodne argumenty modelu.
     * @return list<int>
     */
    private function positiveIntList(array $arguments, string $key, int $maxItems): array
    {
        $raw = $arguments[$key] ?? [];
        if (!is_array($raw) || count($raw) > $maxItems) {
            throw new \InvalidArgumentException("{$key} must be an array with at most {$maxItems} items.");
        }
        $result = [];
        foreach ($raw as $item) {
            $value = filter_var($item, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1) {
                throw new \InvalidArgumentException("{$key} must contain positive integers.");
            }
            $result[$value] = $value;
        }
        return array_values($result);
    }

    /**
     * Validuje krátké textové atributy od modelu a odstraní duplicity.
     *
     * @param array<string, mixed> $arguments Nedůvěryhodné argumenty modelu.
     * @return list<string>
     */
    private function textList(array $arguments, string $key, int $maxItems, int $maxLength): array
    {
        $raw = $arguments[$key] ?? [];
        if (!is_array($raw) || count($raw) > $maxItems) {
            throw new \InvalidArgumentException("{$key} must be an array with at most {$maxItems} items.");
        }
        $result = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException("{$key} must contain strings.");
            }
            $value = trim($item);
            if ($value === '' || $this->textLength($value) > $maxLength) {
                throw new \InvalidArgumentException("{$key} contains an invalid value.");
            }
            $result[$this->normalize($value)] = $value;
        }
        return array_values($result);
    }

    /**
     * Sloučí dva validované seznamy textů bez překročení veřejného limitu.
     *
     * @param list<string> $first
     * @param list<string> $second
     * @return list<string>
     */
    private function mergeTextLists(array $first, array $second): array
    {
        $merged = [];
        foreach (array_merge($first, $second) as $value) {
            $merged[$this->normalize($value)] = $value;
        }
        if (count($merged) > self::MAX_SEARCH_ATTRIBUTES) {
            throw new \InvalidArgumentException('Combined preferred attributes exceed the allowed limit.');
        }
        return array_values($merged);
    }

    /**
     * Sloučí nová odmítnutá ID se starším kompatibilním polem bez duplicit.
     *
     * @param list<int> $first
     * @param list<int> $second
     * @return list<int>
     */
    private function mergePositiveIntLists(array $first, array $second): array
    {
        $merged = array_values(array_unique(array_merge($first, $second)));
        if (count($merged) > self::MAX_EXCLUDED_PRODUCTS) {
            throw new \InvalidArgumentException('Combined rejected product IDs exceed the allowed limit.');
        }
        return $merged;
    }

    /** Vrati Unicode delku s bezpecnym fallbackem pro instalaci bez mbstring. */
    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
