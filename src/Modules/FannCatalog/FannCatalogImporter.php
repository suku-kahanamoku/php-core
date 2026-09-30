<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

/**
 * Koordinuje čtení katalogu, deduplikaci variant a jejich uložení.
 *
 * Import je omezen na `limitPerCategory` variant v každé kategorii a na 200
 * procházených stránek; stejná varianta nalezená v více kategoriích se stáhne
 * jen jednou, ale vztahy ke kategoriím se uloží všechny. Zápis do databáze je
 * idempotentní (`upsert`), takže opakovaný import nevytváří duplicity.
 */
final class FannCatalogImporter
{
    /** Vstupní stránka katalogu. */
    public const CATALOG_URL = 'https://www.fann.cz/produkty';

    /**
     * @param  FannCatalogProvider   $http       Stahování stránek (síť přes `HttpModule`).
     * @param  FannCatalogParser     $parser     Parsování HTML katalogu.
     * @param  FannCatalogRepository $repository Ukládání kategorií a produktů.
     * @return void
     */
    public function __construct(
        private readonly FannCatalogProvider $http,
        private readonly FannCatalogParser $parser,
        private readonly FannCatalogRepository $repository,
    ) {
    }

    /**
     * Synchronizuje hlavní kategorie a nejvýše zadaný počet variant v každé z nich.
     *
     * @param  int                          $limitPerCategory Limit variant na kategorii (1–200).
     * @param  int                          $concurrency      Počet souběžných stahování (1–6).
     * @param  callable(string): void|null  $progress         Volitelný zpětný volat pro průběžný výpis.
     * @return array{categories: int, unique_products: int, relations: int, per_category: array<string, int>}
     *         Počet kategorií, unikátních variant, vztahů a variant podle kategorie.
     * @throws RuntimeException             Při chybě stahování, parsování nebo uložení.
     */
    public function import(int $limitPerCategory = 50, int $concurrency = 4, ?callable $progress = null): array
    {
        $limitPerCategory = max(1, min(200, $limitPerCategory));
        $categories = $this->parser->parseCategories($this->http->get(self::CATALOG_URL));
        $categoryIds = [];
        $categoryNames = [];
        $productCategories = [];
        $perCategory = [];
        foreach ($categories as $category) {
            $storedCategory = $this->repository->upsertCategory($category);
            $categoryIds[$category['slug']] = $storedCategory['id'];
            $categoryNames[$category['slug']] = $storedCategory['name'];
            $urls = [];
            $pageUrl = $category['url'];
            $visitedPages = [];
            while (count($urls) < $limitPerCategory && count($visitedPages) < 200) {
                if (isset($visitedPages[$pageUrl])) {
                    break;
                }
                $visitedPages[$pageUrl] = true;
                $html = $this->http->get($pageUrl);
                $pageUrls = $this->parser->parseProductUrls($html);
                foreach ($pageUrls as $productUrl) {
                    $urls[$productUrl] = true;
                    if (count($urls) >= $limitPerCategory) {
                        break;
                    }
                }
                $nextPageUrl = $this->parser->parseNextPageUrl($html);
                if ($nextPageUrl === null) {
                    break;
                }
                $pageUrl = $nextPageUrl;
            }
            foreach (array_keys($urls) as $productUrl) {
                $productCategories[$productUrl][$category['slug']] = true;
            }
            $perCategory[$categoryNames[$category['slug']]] = count($urls);
            if ($progress !== null) {
                $progress(sprintf('%s: nalezeno %d variant', $categoryNames[$category['slug']], count($urls)));
            }
        }

        $allUrls = array_keys($productCategories);
        $imported = 0;
        foreach (array_chunk($allUrls, 24) as $chunk) {
            foreach ($this->http->getMany($chunk, $concurrency) as $url => $html) {
                $ids = [];
                foreach (array_keys($productCategories[$url]) as $slug) {
                    $ids[] = $categoryIds[$slug];
                }
                $this->repository->upsertProduct($this->parser->parseProduct($html, $url), $ids);
                $imported++;
            }
            if ($progress !== null) {
                $progress(sprintf('Uloženo %d/%d unikátních variant', $imported, count($allUrls)));
            }
        }
        $relations = array_sum(array_map('count', $productCategories));
        return [
            'categories' => count($categories),
            'unique_products' => $imported,
            'relations' => $relations,
            'per_category' => $perCategory,
        ];
    }
}
