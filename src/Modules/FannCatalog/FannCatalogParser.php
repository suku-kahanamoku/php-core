<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

use DOMDocument;
use DOMXPath;
use RuntimeException;

/** Prevadi verejne HTML FAnn na stabilni domenova pole importu. */
final class FannCatalogParser
{
    /** @return list<array{name: string, slug: string, url: string, position: int}> */
    public function parseCategories(string $html): array
    {
        $xpath = $this->xpath($html);
        $nodes = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " nav_produkty ")]/li/h5/a');
        $result = [];
        foreach ($nodes ?: [] as $node) {
            $url = trim($node->getAttribute('href'));
            $parts = explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'));
            if (count($parts) !== 2 || $parts[0] !== 'produkty') {
                continue;
            }
            $slug = $parts[1];
            $result[$slug] = [
                'name' => $this->text($node->textContent),
                'slug' => $slug,
                'url' => $url,
                'position' => count($result) + 1,
            ];
        }
        if ($result === []) {
            throw new RuntimeException('FAnn categories were not found; site markup may have changed.');
        }
        return array_values($result);
    }

    /** @return list<string> */
    public function parseProductUrls(string $html): array
    {
        $xpath = $this->xpath($html);
        $nodes = $xpath->query('//section[@id="produkty"]//a[contains(concat(" ", normalize-space(@class), " "), " produkt ")]');
        $urls = [];
        foreach ($nodes ?: [] as $node) {
            $url = trim($node->getAttribute('href'));
            if (preg_match('~^https://www\.fann\.cz/produkty/[^/?]+/\d+/\d+$~', $url) === 1) {
                $urls[$url] = true;
            }
        }
        return array_keys($urls);
    }

    /**
     * Vrati dalsi katalogovou stranku podle oficialniho paginacniho odkazu.
     * Pocet karet nelze pouzit jako signal konce, protoze nektere karty mohou
     * sdilet URL nebo mit jiny format.
     */
    public function parseNextPageUrl(string $html): ?string
    {
        $xpath = $this->xpath($html);
        $nodes = $xpath->query('//a[@rel="next" and starts-with(@href, "https://www.fann.cz/produkty")]');
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $url = trim($nodes->item(0)?->getAttribute('href') ?? '');
        return $url !== '' ? $url : null;
    }

    /** @return array<string, mixed> */
    public function parseProduct(string $html, string $sourceUrl): array
    {
        $xpath = $this->xpath($html);
        $schema = null;
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $value = json_decode(trim($script->textContent), true);
            if (is_array($value) && ($value['@type'] ?? null) === 'Product') {
                $schema = $value;
                break;
            }
        }
        if (!is_array($schema)) {
            throw new RuntimeException("Product JSON-LD was not found: {$sourceUrl}");
        }
        preg_match('~/([0-9]+)/([0-9]+)$~', (string) parse_url($sourceUrl, PHP_URL_PATH), $ids);
        $availability = basename((string) ($schema['offers']['availability'] ?? ''));
        $variant = $this->firstText($xpath, '//section[contains(concat(" ", normalize-space(@class), " "), " variant ")]/div[contains(concat(" ", normalize-space(@class), " "), " selected ")]');
        $ingredients = $this->firstText($xpath, '//*[@itemprop="ingredients"]');
        $audiences = [];
        foreach ($xpath->query('//section[contains(@class,"gallery")]//*[@title and contains(concat(" ", normalize-space(@class), " "), " ikona ")]') ?: [] as $node) {
            $audiences[] = trim($node->getAttribute('title'));
        }
        $composition = [];
        foreach ($xpath->query('//*[@id="slozeni"]/div[h3]') ?: [] as $node) {
            $heading = $this->firstText($xpath, './/h3', $node);
            $value = $this->firstText($xpath, './/p', $node);
            if ($heading !== '' && $value !== '') {
                $composition[$heading] = $value;
            }
        }
        $brand = is_array($schema['brand'] ?? null) ? (string) ($schema['brand']['name'] ?? '') : (string) ($schema['brand'] ?? '');
        $ean = trim((string) ($schema['gtin13'] ?? $schema['sku'] ?? ''));
        if ($ean === '') {
            $ean = 'VARIANT-' . ($ids[2] ?? substr(hash('sha256', $sourceUrl), 0, 16));
        }
        $schemaName = trim((string) ($schema['name'] ?? ''));
        $name = $schemaName;
        if ($brand !== '' && !str_starts_with(mb_strtolower($schemaName), mb_strtolower($brand))) {
            $name = trim($brand . ' ' . $schemaName);
        }
        $images = array_values(array_filter((array) ($schema['image'] ?? []), 'is_string'));
        return [
            'sku' => 'FANN-' . strtoupper($ean),
            'name' => $name,
            'description' => $this->text((string) ($schema['description'] ?? '')),
            'price' => (float) ($schema['offers']['price'] ?? 0),
            'stock_quantity' => $availability === 'InStock' ? 1 : 0,
            'published' => 1,
            'variant' => $variant !== '' ? $variant : null,
            'data' => [
                'catalog_source' => [
                    'shop' => 'FAnn parfumerie', 'url' => $sourceUrl,
                    'product_id' => isset($ids[1]) ? (int) $ids[1] : null,
                    'variant_id' => isset($ids[2]) ? (int) $ids[2] : null,
                    'ean' => $ean, 'checked_at' => date('Y-m-d'),
                ],
                'brand' => $brand, 'variant_label' => $variant,
                'availability_at_check' => $availability,
                'currency' => (string) ($schema['offers']['priceCurrency'] ?? 'CZK'),
                'images' => $images,
                'target_audience' => array_values(array_unique($audiences)),
                'composition' => $composition,
                'ingredients' => $ingredients !== '' ? $ingredients : null,
            ],
        ];
    }

    /** Vytvori XPath nad tolerantne nactenym HTML. */
    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // DOMDocument jinak HTML bez explicitniho meta charset interpretuje jako ISO-8859-1.
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($document);
    }

    /** Vrati normalizovany text prvniho nalezeneho uzlu. */
    private function firstText(DOMXPath $xpath, string $query, ?\DOMNode $context = null): string
    {
        $nodes = $xpath->query($query, $context);
        return $nodes !== false && $nodes->length > 0 ? $this->text($nodes->item(0)?->textContent ?? '') : '';
    }

    /** Sjednoti mezery a dekoduje HTML entity. */
    private function text(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
