<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Enumeration (ciselniky) – DB vrstva entity.
 */
class EnumerationRepository extends BaseRepository
{
    /**
     * Konstruktor tridy EnumerationRepository.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->_table    = 'enumeration';
        $this->_alias    = 'e';
        $this->_own      = [
            'type',
            'syscode',
            'label',
            'value',
            'position',
            'published',
            'data'
        ];
        $this->_jsonCols = ['data'];
    }

    /**
     * Vrati strankovany seznam ciselnikovych polozek.
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
     *     type: string,
     *     syscode: string,
     *     label: string,
     *     value: string|null,
     *     position: int,
     *     published: int,
     *     data: array<string, mixed>|null
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
        $orderBy = SQL_SORT($sort, 'e.type ASC, e.position ASC, e.label ASC', 'e');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['e.franchise_code = ?'];
        $params = [$this->_code];

        // Extrahuj 'deleted' z filtru (vychozi 0 = jen aktivni; deleted:1 pro kos).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'e.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'e');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);
        $select   = $this->_buildSelect($proj);
        $sys      = $this->_sys;

        $total = (int) $this->_db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM enumeration e WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->_db->fetchAll(
            "SELECT {$select} FROM enumeration e
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            if (isset($item['data'])) {
                $item['data'] = $item['data'] ? json_decode($item['data'], true) : null;
            }
            $item = $proj->apply($item, $sys);
        }
        unset($item);

        return $this->_resultList($items, $total, $page, $limit);
    }

    /**
     * Vrati ciselnikovou polozku dle ID.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $row = parent::findById($id, $projection);
        if ($row !== null && isset($row['data'])) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
        }
        return $row;
    }

    /**
     * Vrati seznam vsech unikatnich typu ciselniku.
     *
     * @return list<string>
     */
    public function getTypes(): array
    {
        $rows = $this->_db->fetchAll(
            'SELECT DISTINCT type FROM enumeration
             WHERE franchise_code = ? AND deleted = 0 ORDER BY type ASC',
            [$this->_code],
        );

        return array_column($rows, 'type');
    }

    /**
     * Vrati jednu polozku ciselníku dle type + syscode, nebo null.
     *
     * @param  string $type
     * @param  string $syscode
     * @return array<string, mixed>|null
     */
    public function findBySyscode(string $type, string $syscode): ?array
    {
        $row = $this->_db->fetchOne(
            'SELECT * FROM enumeration
             WHERE franchise_code = ? AND type = ? AND syscode = ? AND deleted = 0
             LIMIT 1',
            [$this->_code, $type, $syscode],
        ) ?: null;

        if ($row !== null && isset($row['data'])) {
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
        }

        return $row;
    }

    /**
     *
     * @param  string   $type
     * @param  string   $code
     * @param  int|null $excludeId
     * @return bool
     */
    public function codeExists(string $type, string $code, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->_db->fetchOne(
                'SELECT id FROM enumeration
                 WHERE franchise_code = ? AND type = ? AND syscode = ? AND id != ? AND deleted = 0',
                [$this->_code, $type, $code, $excludeId],
            );
        } else {
            $row = $this->_db->fetchOne(
                'SELECT id FROM enumeration
                 WHERE franchise_code = ? AND type = ? AND syscode = ? AND deleted = 0',
                [$this->_code, $type, $code],
            );
        }

        return (bool) $row;
    }

    /**
     * Vlozi novou ciselnikovou polozku a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int, 
     *   created_at: string, 
     *   updated_at: string, 
     *   type: string, 
     *   syscode: string, 
     *   label: string, 
     *   value: string|null, 
     *   position: int, 
     *   published: int
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data'], JSON_UNESCAPED_UNICODE);
        }

        $id = $this->_db->insert('enumeration', array_merge($data, [
            'franchise_code' => $this->_code,
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje ciselnikovou polozku a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int, 
     *   created_at: string, 
     *   updated_at: string, 
     *   type: string, 
     *   syscode: string, 
     *   label: string, 
     *   value: string|null, 
     *   position: int, 
     *   published: int
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $data = $this->_patchJsonCols($id, $data);

        $this->_db->update(
            'enumeration',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->_code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }
}
