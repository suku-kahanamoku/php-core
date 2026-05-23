<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Invoice – DB vrstva entity.
 */
class InvoiceRepository extends BaseRepository
{
    /**
     * Konstruktor tridy InvoiceRepository.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->_table = 'invoice';
        $this->_alias = 'i';
        $this->_own   = [
            'invoice_number',
            'order_id',
            'order_number',
            'status',
            'currency',
            'payment',
            'shipping',
            'total_price',
            'total_price_with_vat',
            'total_price_all',
            'total_price_all_with_vat',
            'user',
            'billing_address',
            'shipping_address',
            'note',
            'issued_at',
            'due_at',
            'paid_at',
        ];
        $this->_rel = ['files'];
    }

    /**
     * Vrati strankovany seznam faktur.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  int|null    $userId
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array{
     *     id: int,
     *     created_at: string,
     *     updated_at: string,
     *     invoice_number: string,
     *     status: string,
     *     total_amount: float,
     *     currency: string,
     *     issued_at: string,
     *     due_at: string|null,
     *     paid_at: string|null,
     *     order_id: int|null,
     *     user_id: int|null,
     *     billing_address_id: int|null,
     *     user?: array{
     *       first_name: string, 
     *       last_name: string, 
     *       email: string
     *     }
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
        ?int $userId = null,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'i.issued_at DESC', 'i');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['i.franchise_code = ?'];
        $params = [$this->_code];

        if ($userId !== null) {
            $where[]  = 'i.user_id = ?';
            $params[] = $userId;
        }

        // Extrahuj 'deleted' z filtru (vychozi 0 = pouze aktivni).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'i.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'i');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys        = $this->_sys;
        $baseSelect = $this->_buildSelect($proj);

        // user je JSON sloupec — neni potreba JOIN
        $joinSql = '';
        $relSel  = '';

        // Auto-JOIN user byl odstranen — user.* filtry matchuji JSON sloupec
        $select = "{$baseSelect}{$relSel}";

        $total = (int) $this->_db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM invoice i {$joinSql} WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->_db->fetchAll(
            "SELECT {$select} FROM invoice i {$joinSql}
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        $needsFileIds = $proj->needsJoin('files');
        $fileIdsMap   = [];
        if ($needsFileIds && !empty($items)) {
            $ids          = array_column($items, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $fileRows     = $this->_db->fetchAll(
                "SELECT invoice_id, file_id FROM invoice_file WHERE invoice_id IN ({$placeholders})",
                $ids,
            );
            foreach ($fileRows as $fr) {
                $fileIdsMap[(int) $fr['invoice_id']][] = (int) $fr['file_id'];
            }
        }

        foreach ($items as &$item) {
            // Dekoduj JSON sloupce
            foreach (
                [
                    'user',
                    'billing_address',
                    'shipping_address',
                    'payment',
                    'shipping'
                ] as $col
            ) {
                if (isset($item[$col]) && is_string($item[$col])) {
                    $item[$col] = json_decode($item[$col], true);
                }
            }
            if ($needsFileIds) {
                $item['file_ids'] = $fileIdsMap[(int) $item['id']] ?? [];
            }
            $item = $proj->apply($item, $sys, ['files' => ['file_ids']]);
        }
        unset($item);

        return $this->_resultList($items, $total, $page, $limit);
    }

    /**
     * Najde fakturu dle ID vcetne polozek.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   invoice_number: string,
     *   status: string,
     *   total_amount: float,
     *   currency: string,
     *   issued_at: string,
     *   due_at: string|null,
     *   paid_at: string|null,
     *   order_id: int|null,
     *   user_id: int|null,
     *   billing_address_id: int|null,
     *   items: list<array{
     *     id: int, 
     *     invoice_id: int, 
     *     product_id: int, 
     *     quantity: int, 
     *     unit_price: float, 
     *     vat_rate: float, 
     *     product_name: string|null, 
     *     sku: string|null
     *   }>,
     *   user?: array{
     *     first_name: string,
     *     last_name: string,
     *     email: string
     *   }
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj = new Projection($projection);

        $sys        = $this->_sys;
        $baseSelect = $this->_buildSelect($proj);
        $select     = $baseSelect;

        $invoice = $this->_db->fetchOne(
            "SELECT {$select} FROM invoice i
             WHERE i.id = ? AND i.franchise_code = ? AND i.deleted = 0",
            [$id, $this->_code],
        );

        if (!$invoice) {
            return null;
        }

        // Dekoduj JSON sloupce
        foreach (
            [
                'user',
                'billing_address',
                'shipping_address',
                'payment',
                'shipping'
            ] as $col
        ) {
            if (isset($invoice[$col]) && is_string($invoice[$col])) {
                $invoice[$col] = json_decode($invoice[$col], true);
            }
        }

        $invoice['items'] = $this->_db->fetchAll(
            'SELECT ii.id, ii.product_name, ii.sku, ii.quantity,
                    ii.price, ii.price_with_vat, ii.vat_rate,
                    ii.total_price, ii.total_price_with_vat
             FROM invoice_item ii
             WHERE ii.invoice_id = ? AND ii.deleted = 0',
            [$id],
        );

        if ($proj->needsJoin('files')) {
            $fileRows            = $this->_db->fetchAll(
                'SELECT file_id FROM invoice_file WHERE invoice_id = ?',
                [$id]
            );
            $invoice['file_ids'] = array_map('intval', array_column($fileRows, 'file_id'));
        }

        return $proj->apply($invoice, $sys, ['files' => ['file_ids']]);
    }

    /**
     * Vraci ID zaznamu faktury pro danou objednavku, nebo null pokud neexistuje.
     *
     * @param  int $orderId
     * @return array{id: int}|null
     */
    public function findByOrder(int $orderId): ?array
    {
        $row = $this->_db->fetchOne(
            'SELECT id FROM invoice
             WHERE franchise_code = ? AND order_id = ? AND deleted = 0',
            [$this->_code, $orderId],
        );

        return $row ?: null;
    }

    /**
     * Vlozi novou fakturu a vrati vytvoreny zaznam vcetne polozek.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   invoice_number: string,
     *   status: string,
     *   total_amount: float,
     *   user_id: int|null,
     *   items: list<array<string, mixed>>
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->_db->insert('invoice', array_merge($data, [
            'franchise_code' => $this->_code,
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Vlozi polozku faktury a vrati jeji ID.
     *
     * @param  array<string, mixed> $data
     * @return int
     */
    public function createItem(array $data): int
    {
        return $this->_db->insert('invoice_item', array_merge($data, []));
    }

    /**
     * Synchronizuje soubory faktury — smaze stare a vlozi nove.
     *
     * @param  int        $invoiceId
     * @param  list<int>  $fileIds
     * @return void
     */
    public function syncFiles(int $invoiceId, array $fileIds): void
    {
        $this->_db->delete('invoice_file', 'invoice_id = ?', [$invoiceId]);

        foreach ($fileIds as $fileId) {
            $this->_db->insert('invoice_file', [
                'invoice_id' => $invoiceId,
                'file_id'    => (int) $fileId,
            ]);
        }
    }

    /**
     * Vrati aktualni ID souboru linkovanych k fakture.
     *
     * @param  int $invoiceId
     * @return list<int>
     */
    public function getFileIds(int $invoiceId): array
    {
        $rows = $this->_db->fetchAll(
            'SELECT file_id FROM invoice_file WHERE invoice_id = ?',
            [$invoiceId],
        );
        return array_map(static fn($r) => (int) $r['file_id'], $rows);
    }

    /** (a nastavi paid_at pro status 'paid') a vrati aktualizovany zaznam.
     *
     * @param  int        $id
     * @param  string     $status
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   invoice_number: string,
     *   status: string,
     *   total_amount: float,
     *   user_id: int|null,
     *   items: list<array<string, mixed>>
     * }
     */
    public function updateStatus(
        int $id,
        string $status,
        ?array $projection = null
    ): array {
        $set = ['status' => $status];
        if ($status === 'paid') {
            $set['paid_at'] = date('Y-m-d H:i:s');
        }
        $this->_db->update(
            'invoice',
            $set,
            'id = ? AND franchise_code = ?',
            [$id, $this->_code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Vygeneruje unikatni cislo faktury ve formatu YYYY-NNNNN.
     *
     * @return string
     */
    public function generateNumber(): string
    {
        $year = date('Y');
        $last = $this->_db->fetchOne(
            'SELECT invoice_number FROM invoice
             WHERE franchise_code = ? AND invoice_number LIKE ?
             ORDER BY id DESC LIMIT 1',
            [$this->_code, $year . '%'],
        );

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last['invoice_number']);
            $seq   = ((int) end($parts)) + 1;
        }

        return $year . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
