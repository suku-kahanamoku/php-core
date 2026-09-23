<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

/** Koordinuje cteni katalogu, deduplikaci variant a jejich ulozeni. */
final class FannCatalogImporter
{
    public const CATALOG_URL = 'https://www.fann.cz/produkty';

    public function __construct(
        private readonly FannCatalogHttpClient $http,
        private readonly FannCatalogParser $parser,
        private readonly FannCatalogRepository $repository,
    ) {
    }

    /**
     * Synchronizuje hlavni kategorie a nejvyse zadany pocet variant v kazde z nich.
     * @param callable(string): void|null $progress
     * @return array{categories: int, unique_products: int, relations: int, per_category: array<string, int>}
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
