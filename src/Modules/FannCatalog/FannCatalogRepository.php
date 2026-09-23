<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

use App\Modules\Database\Database;
use RuntimeException;

/** Uklada importovane kategorie a produkty idempotentne pro jeden tenant. */
final class FannCatalogRepository
{
    /** Anglicke klice a zobrazovane nazvy pouzivane napric backendem a klienty. */
    private const CATEGORY_DEFINITIONS = [
        'plet' => ['syscode' => 'skincare', 'name' => 'Skin Care'],
        'vune' => ['syscode' => 'perfumes', 'name' => 'Fragrances'],
        'liceni' => ['syscode' => 'makeup', 'name' => 'Makeup'],
        'telo' => ['syscode' => 'body-care', 'name' => 'Body Care'],
        'doplnky' => ['syscode' => 'accessories', 'name' => 'Accessories'],
        'darkove-karty-a-poukazy' => [
            'syscode' => 'gift-cards-and-vouchers',
            'name' => 'Gift Cards and Vouchers',
        ],
    ];

    public function __construct(
        private readonly Database $database,
        private readonly string $franchiseCode = 'fun',
    ) {
    }

    /**
     * @param array{name: string, slug: string, url: string, position: int} $category
     * @return array{id: int, name: string}
     */
    public function upsertCategory(array $category): array
    {
        $definition = self::CATEGORY_DEFINITIONS[$category['slug']] ?? null;
        if ($definition === null) {
            throw new RuntimeException('Missing English syscode mapping for FAnn category: ' . $category['slug']);
        }
        $syscode = $definition['syscode'];
        $this->migrateLegacyCategory('fann-' . $category['slug'], $syscode);
        $this->database->query(
            'INSERT INTO category (franchise_code, parent_id, syscode, name, description, position, published, deleted)
             VALUES (?, NULL, ?, ?, ?, ?, 1, 0)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                 position = VALUES(position), published = 1, deleted = 0',
            [
                $this->franchiseCode,
                $syscode,
                $definition['name'],
                'Imported from ' . $category['url'],
                $category['position'],
            ],
        );
        $row = $this->database->fetchOne(
            'SELECT id FROM category WHERE franchise_code = ? AND syscode = ?',
            [$this->franchiseCode, $syscode],
        );
        return ['id' => (int) ($row['id'] ?? 0), 'name' => $definition['name']];
    }

    /**
     * Prejmenuje starsi importovanou kategorii, nebo ji slouci s existujicim
     * anglickym klicem a zachova vsechny produktove vazby.
     */
    private function migrateLegacyCategory(string $legacySyscode, string $targetSyscode): void
    {
        $legacy = $this->database->fetchOne(
            'SELECT id FROM category WHERE franchise_code = ? AND syscode = ?',
            [$this->franchiseCode, $legacySyscode],
        );
        if ($legacy === false) {
            return;
        }
        $target = $this->database->fetchOne(
            'SELECT id FROM category WHERE franchise_code = ? AND syscode = ?',
            [$this->franchiseCode, $targetSyscode],
        );
        $pdo = $this->database->getPdo();
        $pdo->beginTransaction();
        try {
            if ($target === false) {
                $this->database->query(
                    'UPDATE category SET syscode = ? WHERE id = ?',
                    [$targetSyscode, (int) $legacy['id']],
                );
            } else {
                $this->database->query(
                    'INSERT IGNORE INTO product_category (product_id, category_id)
                     SELECT product_id, ? FROM product_category WHERE category_id = ?',
                    [(int) $target['id'], (int) $legacy['id']],
                );
                $this->database->query('DELETE FROM category WHERE id = ?', [(int) $legacy['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Vlozi nebo aktualizuje produkt a zachova data, ktera nespravuje scraper.
     * @param array<string, mixed> $product
     * @param list<int> $categoryIds
     */
    public function upsertProduct(array $product, array $categoryIds): int
    {
        $current = $this->database->fetchOne(
            'SELECT id, data FROM product WHERE franchise_code = ? AND sku = ?',
            [$this->franchiseCode, $product['sku']],
        );
        $existingData = [];
        if (is_array($current) && is_string($current['data'] ?? null)) {
            $existingData = json_decode($current['data'], true, 512, JSON_THROW_ON_ERROR);
        }
        $data = array_replace_recursive(is_array($existingData) ? $existingData : [], $product['data']);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $variant = $product['variant'] !== null ? mb_substr((string) $product['variant'], 0, 64) : null;

        $this->database->query(
            'INSERT INTO product
                (franchise_code, sku, name, description, price, stock_quantity, published, deleted, kind, color, variant, data)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), price = VALUES(price),
                 stock_quantity = VALUES(stock_quantity), published = VALUES(published), deleted = 0,
                 variant = VALUES(variant), data = VALUES(data)',
            [
                $this->franchiseCode, $product['sku'], $product['name'], $product['description'],
                $product['price'], $product['stock_quantity'], $product['published'], $variant, $encoded,
            ],
        );
        $row = $this->database->fetchOne(
            'SELECT id FROM product WHERE franchise_code = ? AND sku = ?',
            [$this->franchiseCode, $product['sku']],
        );
        $productId = (int) ($row['id'] ?? 0);
        foreach (array_unique($categoryIds) as $categoryId) {
            $this->database->query(
                'INSERT IGNORE INTO product_category (product_id, category_id) VALUES (?, ?)',
                [$productId, $categoryId],
            );
        }
        return $productId;
    }
}
