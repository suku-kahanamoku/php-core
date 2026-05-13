<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Product – DB entity layer.
 */
class ProductRepository extends BaseRepository
{
    /**
     * ProductRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'product';
        $this->alias = 'p';
        $this->own   = [
            'sku',
            'name',
            'description',
            'price',
            'vat_rate',
            'stock_quantity',
            'published',
            'kind',
            'color',
            'variant',
            'data',
        ];
        $this->rel = ['categories'];
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

        // Extract 'deleted' from filter (default 0 = active only).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'p.deleted = ?';
        $params[] = $deletedVal;

        // Detect whether the filter references category.* columns (e.g. category.syscode).
        // If so, we need to JOIN the category table so SQL_FILTER can resolve category.col.
        $decodedFilter  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $needsCatFilter = !empty(array_filter(
            array_keys($decodedFilter),
            static fn($k) => str_starts_with((string) $k, 'category.')
        ));

        $needsCatJoin = $proj->needsJoin('categories') || $needsCatFilter;

        // Build JOINs — no ? placeholders so param order stays simple.
        $catJoin = $needsCatJoin
            ? 'LEFT JOIN product_category pc ON pc.product_id = p.id
               LEFT JOIN category ON category.id = pc.category_id AND category.deleted = 0'
            : '';

        // When joining category, restrict it to the same franchise to avoid cross-tenant leaks.
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

        $sys        = $this->sys;
        $baseSelect = $this->buildSelect($proj);
        $catSel     = $proj->needsJoin('categories')
            ? ', GROUP_CONCAT(pc.category_id ORDER BY pc.category_id) AS category_ids'
            : '';

        $select = "{$baseSelect}{$catSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT p.id) AS cnt FROM product p {$catJoin} WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM product p {$catJoin}
             WHERE {$whereStr}
             GROUP BY p.id
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            if (isset($item['category_ids'])) {
                $item['category_ids'] = $item['category_ids']
                    ? array_map('intval', explode(',', $item['category_ids']))
                    : [];
            }
            if (isset($item['data'])) {
                $item['data'] = $item['data'] ? json_decode($item['data'], true) : null;
            }
            $item = $proj->apply($item, $sys, ['categories' => ['category_ids']]);
        }
        unset($item);

        return $this->paginationResult($items, $total, $page, $limit);
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
     *   category_ids?: list<int>,
     *   category_names?: list<string>
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj = new Projection($projection);

        $sys     = $this->sys;
        $ownCols = $proj->getOwnCols($this->own, $this->rel);
        $cols    = array_merge($sys, $ownCols);
        $quoted  = array_map(fn($c) => "`{$c}`", $cols);
        $select  = implode(', ', $quoted);

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM product WHERE id = ? AND franchise_code = ? AND deleted = 0",
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        if (isset($row['data'])) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
        }

        if ($proj->needsJoin('categories')) {
            $categoryRows = $this->db->fetchAll(
                'SELECT pc.category_id, c.name AS category_name
                 FROM product_category pc
                 LEFT JOIN category c ON c.id = pc.category_id AND c.deleted = 0
                 WHERE pc.product_id = ?',
                [$id],
            );
            $row['category_ids']   = array_map(
                'intval',
                array_column($categoryRows, 'category_id')
            );
            $row['category_names'] = array_column($categoryRows, 'category_name');
        }

        return $proj->apply(
            $row,
            $sys,
            ['categories' => ['category_ids', 'category_names']]
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
     * Smaze produkt vcetne vazeb na kategorie.
     *
     * @param  int $id
     * @return int  Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->update(
            'product',
            ['deleted' => 1],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
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
