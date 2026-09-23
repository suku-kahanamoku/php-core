<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Vytvari deterministicky vyhledavaci dokument jednoho produktu. */
final class OpenAiProductDocumentBuilder
{
    /** @param array<string, mixed> $product */
    public function build(array $product, string $franchiseCode): string
    {
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
            'brand' => (string) ($product['data']['brand'] ?? ''),
            'variant' => (string) ($product['variant'] ?? $product['data']['variant_label'] ?? ''),
            'categories' => $categories,
            'price_with_vat' => (float) ($product['price_with_vat'] ?? 0),
            'stock_quantity' => (int) ($product['stock_quantity'] ?? 0),
            'selection_attributes' => $product['data']['selection_attributes'] ?? [],
            'composition' => $product['data']['composition'] ?? [],
            'ingredients' => $product['data']['ingredients'] ?? null,
            'source' => $product['data']['catalog_source'] ?? null,
        ];
        return json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
