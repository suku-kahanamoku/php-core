<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Vytvari deterministicky vyhledavaci dokument jednoho produktu. */
final class OpenAiProductDocumentBuilder
{
    /** @param array<string, mixed> $product */
    public function build(array $product, string $franchiseCode): string
    {
        $productData = is_array($product['data'] ?? null) ? $product['data'] : [];
        $priceWithVat = (float) ($product['price_with_vat'] ?? 0);
        $currency = strtoupper(trim((string) ($productData['currency'] ?? 'CZK')));
        $categories = array_values(array_filter(array_map(
            static fn(mixed $category): string => is_array($category) ? trim((string) ($category['name'] ?? '')) : '',
            is_array($product['categories'] ?? null) ? $product['categories'] : [],
        )));
        $document = [
            'record_type' => 'product',
            'franchise_code' => $franchiseCode,
            'product_id' => (int) ($product['id'] ?? 0),
            'sku' => (string) ($product['sku'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'description' => (string) ($product['description'] ?? ''),
            'brand' => (string) ($productData['brand'] ?? ''),
            'variant' => (string) ($product['variant'] ?? $productData['variant_label'] ?? ''),
            'categories' => $categories,
            'price' => [
                'amount_with_vat' => $priceWithVat,
                'currency' => $currency !== '' ? $currency : 'CZK',
            ],
            'price_with_vat' => $priceWithVat,
            'stock_quantity' => (int) ($product['stock_quantity'] ?? 0),
            'selection_attributes' => $productData['selection_attributes'] ?? [],
            'composition' => $productData['composition'] ?? [],
            'ingredients' => $productData['ingredients'] ?? null,
            'source' => $productData['catalog_source'] ?? null,
        ];
        return json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
