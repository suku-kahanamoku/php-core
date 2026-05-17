<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\BaseRepository;
use App\Modules\Category\CategoryRepository;
use App\Modules\Database\Database;
use App\Modules\File\FileRepository;
use App\Utils\Projection;

/**
 * Product – DB vrstva entity.
 */
class ProductRepository extends BaseRepository
{
    private FileRepository $fileRepo;
    private CategoryRepository $categoryRepo;

    /**
     * Konstruktor tridy ProductRepository.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->fileRepo     = new FileRepository($db, $franchiseCode);
        $this->categoryRepo = new CategoryRepository($db, $franchiseCode);
        $this->table = 'product';
        $this->alias = 'p';
        $this->own   = [
            'sku',
            'name',
            'description',
            'price',
            'stock_quantity',
            'published',
            'kind',
            'color',
            'variant',
            'data',
        ];
        $this->rel = ['categories', 'files'];
    }

    /**
     * Vrati strankovany seznam produktu.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     sku: string,
     *     name: string,
     *     description: string|null,
     *     price: float,
     *     vat_rate: float,
     *     stock_quantity: int,
     *     published: int,
     *     kind: string|null,
     *     color: string|null,
     *     variant: string|null,
     *     data: array<string, mixed>|null,
     *     category_ids?: list<int>
     *   }>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'p.created_at DESC', 'p');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['p.franchise_code = ?'];
        $params = [$this->code];

        // Extrahuj 'deleted' z filtru (vychozi 0 = pouze aktivni).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);

        // Vypoctene sloupce (SELECT aliasy) nelze pouzit v WHERE — extrahuj je pro HAVING.
        $computedCols  = ['price_with_vat', 'vat_rate'];
        $havingFilters = [];
        foreach ($computedCols as $col) {
            if (isset($filterArr[$col])) {
                $havingFilters[$col] = $filterArr[$col];
                unset($filterArr[$col]);
            }
        }

        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'p.deleted = ?';
        $params[] = $deletedVal;

        // Zjisti, zda filtr odkazuje na sloupce category.* (napr. category.syscode).
        // Pokud ano, potrebujeme JOIN tabulky category, aby SQL_FILTER mohl resolvovat category.col.
        $decodedFilter  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $needsCatFilter = !empty(array_filter(
            array_keys($decodedFilter),
            static fn($k) => str_starts_with((string) $k, 'category.')
        ));

        // JOIN je potreba jen pro filtrovani dle category.* — data nacitame pres batch
        $needsCatJoin = $needsCatFilter;

        // Sestav JOINy — bez znacky '?' aby zutal poradak parametru jednoduchy.
        $catJoin = $needsCatJoin
            ? 'LEFT JOIN product_category pc ON pc.product_id = p.id
               LEFT JOIN category ON category.id = pc.category_id AND category.deleted = 0'
            : '';

        // Derived table: nacte nejnovejsi sazbu DPH jednou pro cely dotaz (ne per-row).
        $vatJoin = "JOIN (
            SELECT COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '\$.rate')) AS DECIMAL(5,2)), 21) AS rate
            FROM enumeration
            WHERE franchise_code = ? AND type = 'vat_rate' AND deleted = 0
            ORDER BY created_at DESC
            LIMIT 1
        ) vat ON TRUE";

        // Pri joinu category omezit na stejnou franchizu, aby se predchazelo cross-tenant uniku.
        if ($needsCatFilter) {
            $where[] = '(category.franchise_code = ? OR category.id IS NULL)';
            $params[] = $this->code;
        }

        $f = SQL_FILTER($filter, 'p');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        // Sestav HAVING pro vypoctene sloupce (SELECT aliasy).
        $havingStr    = '';
        $havingParams = [];
        if ($havingFilters) {
            $havingH = SQL_FILTER(json_encode($havingFilters));
            if ($havingH['sql'] !== '') {
                $havingStr    = 'HAVING ' . $havingH['sql'];
                $havingParams = $havingH['params'];
            }
        }

        // Params pro hlavni SELECT: vat franchise_code na zacatku (FROM clause), pak WHERE + HAVING params.
        $queryParams = array_merge([$this->code], $params, $havingParams);

        $sys        = $this->sys;
        $baseSelect = $this->buildSelect($proj);
        $vatSel     = ', ANY_VALUE(vat.rate) AS vat_rate'
            . ', ANY_VALUE(ROUND(p.price * (1 + vat.rate / 100), 2)) AS price_with_vat';

        $select = "{$baseSelect}{$vatSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT p.id) AS cnt FROM product p {$catJoin} WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM product p {$catJoin} {$vatJoin}
             WHERE {$whereStr}
             GROUP BY p.id
             {$havingStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $queryParams,
        );

        $ids = array_column($items, 'id');

        $categoriesMap = [];
        if ($proj->needsJoin('categories')) {
            $categoriesMap = $this->categoryRepo->findByJunctionList('product_category', 'product_id', $ids);
        }

        $filesMap = [];
        if ($proj->needsJoin('files')) {
            $filesMap = $this->fileRepo->findByJunctionList('product_file', 'product_id', $ids);
        }

        $vatSys = array_merge($sys, ['vat_rate', 'price_with_vat']);
        foreach ($items as &$item) {
            if (isset($item['data'])) {
                $item['data'] = $item['data'] ? json_decode($item['data'], true) : null;
            }
            if ($proj->needsJoin('categories')) {
                $itemCats = $categoriesMap[(int) $item['id']] ?? [];
                $item['category_ids'] = array_column($itemCats, 'id');
                $item['categories']   = $itemCats;
            }
            if ($proj->needsJoin('files')) {
                $itemFiles = $filesMap[(int) $item['id']] ?? [];
                $item['file_ids'] = array_column($itemFiles, 'id');
                $item['files']    = $itemFiles;
            }
            $item = $proj->apply($item, $vatSys, [
                'categories' => ['category_ids', 'categories'],
                'files'      => ['file_ids', 'files'],
            ]);
        }
        unset($item);

        return $this->resultList($items, $total, $page, $limit);
    }

    /**
     * Najde produkt dle ID vcetne kategorii.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   sku: string,
     *   name: string,
     *   description: string|null,
     *   price: float,
     *   vat_rate: float,
     *   stock_quantity: int,
     *   published: int,
     *   kind: string|null,
     *   color: string|null,
     *   variant: string|null,
     *   data: array<string, mixed>|null,
     *   category_ids?: list<int>
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj   = new Projection($projection);
        $sys    = $this->sys;
        $select = $this->buildSelect($proj);

        $vatJoin = "JOIN (
            SELECT COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '\$.rate')) AS DECIMAL(5,2)), 21) AS rate
            FROM enumeration
            WHERE franchise_code = ? AND type = 'vat_rate' AND deleted = 0
            ORDER BY created_at DESC
            LIMIT 1
        ) vat ON TRUE";

        $row = $this->db->fetchOne(
            "SELECT {$select},
                    vat.rate AS vat_rate,
                    ROUND(p.price * (1 + vat.rate / 100), 2) AS price_with_vat
             FROM product p {$vatJoin}
             WHERE p.id = ? AND p.franchise_code = ? AND p.deleted = 0",
            [$this->code, $id, $this->code],
        );

        if (!$row) {
            return null;
        }

        if (isset($row['data'])) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
        }

        if ($proj->needsJoin('categories')) {
            $categories = $this->categoryRepo->findByJunctionItem('product_category', 'product_id', $id);
            $row['category_ids'] = array_column($categories, 'id');
            $row['categories']   = $categories;
        }

        if ($proj->needsJoin('files')) {
            $files = $this->fileRepo->findByJunctionItem('product_file', 'product_id', $id);
            $row['file_ids'] = array_column($files, 'id');
            $row['files']    = $files;
        }

        $vatSys = array_merge($sys, ['vat_rate', 'price_with_vat']);
        return $proj->apply(
            $row,
            $vatSys,
            [
                'categories' => ['category_ids', 'categories'],
                'files'      => ['file_ids', 'files'],
            ]
        );
    }

    /**
     * Synchronizuje kategorie produktu — smaze stare a vlozi nove.
     *
     * @param  int        $productId
     * @param  list<int>  $categoryIds
     * @return void
     */
    public function syncCategories(int $productId, array $categoryIds): void
    {
        $this->db->delete('product_category', 'product_id = ?', [$productId]);

        foreach ($categoryIds as $catId) {
            $this->db->insert('product_category', [
                'product_id'  => $productId,
                'category_id' => (int) $catId,
            ]);
        }
    }

    /**
     * Synchronizuje soubory produktu — smaze stare a vlozi nove.
     *
     * @param  int        $productId
     * @param  list<int>  $fileIds
     * @return void
     */
    public function syncFiles(int $productId, array $fileIds): void
    {
        $this->db->delete('product_file', 'product_id = ?', [$productId]);

        foreach ($fileIds as $fileId) {
            $this->db->insert('product_file', [
                'product_id' => $productId,
                'file_id'    => (int) $fileId,
            ]);
        }
    }

    /**
     * Vlozi novy produkt a vrati vytvoreny zaznam vcetne kategoriı.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   sku: string,
     *   name: string,
     *   price: float,
     *   stock_quantity: int,
     *   published: int,
     *   category_ids: list<int>
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data'], JSON_UNESCAPED_UNICODE);
        }

        $id = $this->db->insert('product', array_merge($data, [
            'franchise_code' => $this->code,
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje produkt a vrati aktualizovany zaznam vcetne kategoriı.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   sku: string,
     *   name: string,
     *   price: float,
     *   stock_quantity: int,
     *   published: int,
     *   category_ids: list<int>
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data'], JSON_UNESCAPED_UNICODE);
        }

        $this->db->update(
            'product',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Vrati true, pokud ma kategorie prirazeny alespon jeden produkt.
     *
     * @param  int $categoryId
     * @return bool
     */
    public function existsForCategory(int $categoryId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT pc.product_id FROM product_category pc
             INNER JOIN product p ON p.id = pc.product_id
             WHERE p.franchise_code = ? AND pc.category_id = ? AND p.deleted = 0 LIMIT 1',
            [$this->code, $categoryId],
        );

        return (bool) $row;
    }

    /**
     * Vrati produkty prirazene ke kategorii.
     *
     * @param  int $categoryId
     * @return list<array{id: int, sku: string, name: string, price: float}>
     */
    public function findByCategoryId(int $categoryId): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.sku, p.name, p.price FROM product p
             INNER JOIN product_category pc ON pc.product_id = p.id
             WHERE p.franchise_code = ? AND pc.category_id = ? AND p.deleted = 0',
            [$this->code, $categoryId],
        );
    }

    /**
     * Upravi skladove mnozstvi produktu o delta (kladne = pridat, zaporne = odebrat).
     * Vraci nove mnozstvi nebo -1 pokud produkt neexistuje.
     *
     * @param  int $id
     * @param  int $delta
     * @return int
     */
    public function adjustStock(int $id, int $delta): int
    {
        $product = $this->db->fetchOne(
            'SELECT stock_quantity FROM product
             WHERE id = ? AND franchise_code = ? AND deleted = 0',
            [$id, $this->code],
        );

        if (!$product) {
            return -1;
        }

        $newQty = $product['stock_quantity'] + $delta;
        $this->db->update(
            'product',
            ['stock_quantity' => $newQty],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $newQty;
    }

    /**
     * Vygeneruje unikatni SKU ve formatu SKU-XXXXXX.
     *
     * @return string
     */
    public function generateSku(): string
    {
        return 'SKU-' . strtoupper(substr(uniqid(), -6));
    }
}
