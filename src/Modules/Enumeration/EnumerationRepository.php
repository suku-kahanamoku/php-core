<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Enumeration (codebook) – DB entity layer.
 */
class EnumerationRepository extends BaseRepository
{
    /**
     * EnumerationRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'enumeration';
        $this->alias = 'e';
        $this->own   = ['type', 'syscode', 'label', 'value', 'position', 'is_active'];
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
     *     is_active: int
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
        $params = [$this->code];

        $f = SQL_FILTER($filter, 'e');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);
        $select   = $this->buildSelect($proj);
        $sys      = $this->sys;

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM enumeration e WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM enumeration e
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys);
        }
        unset($item);

        return $this->paginationResult($items, $total, $page, $limit);
    }

    /**
     * Vrati seznam vsech unikatnich typu ciselniku.
     *
     * @return list<string>
     */
    public function getTypes(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT type FROM enumeration
             WHERE franchise_code = ? ORDER BY type ASC',
            [$this->code],
        );

        return array_column($rows, 'type');
    }

    /**
     * Vraci true, pokud kombinace type + syscode jiz existuje.
     *
     * @param  string   $type
     * @param  string   $code
     * @param  int|null $excludeId
     * @return bool
     */
    public function codeExists(string $type, string $code, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM enumeration
                 WHERE franchise_code = ? AND type = ? AND syscode = ? AND id != ?',
                [$this->code, $type, $code, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM enumeration
                 WHERE franchise_code = ? AND type = ? AND syscode = ?',
                [$this->code, $type, $code],
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
     *   is_active: int
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('enumeration', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
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
     *   is_active: int
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'enumeration',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze ciselnikovou polozku.
     *
     * @param  int $id
     * @return int  Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->delete(
            'enumeration',
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }
}
