<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Enumeration (codebook) – DB entity layer.
 */
class EnumerationRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = ['type', 'syscode', 'label', 'value', 'position', 'is_active'];
    private const REL = [];

    /**
     * EnumerationRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /**
     * Vrati strankovany seznam ciselnikovych polozek.
     *
     * @param  string|null $type
     * @param  bool|null   $isActive
     * @param  string      $sort
     * @param  int         $page
     * @param  int         $limit
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
        ?string $type = null,
        ?bool $isActive = null,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'type ASC, position ASC, label ASC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['franchise_code = ?'];
        $params = [$this->code];

        if ($type !== null) {
            $where[]  = 'type = ?';
            $params[] = $type;
        }
        if ($isActive !== null) {
            $where[]  = 'is_active = ?';
            $params[] = (int) $isActive;
        }

        $f = SQL_FILTER($filter);
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $cols    = array_merge($sys, $ownCols);
        $select  = implode(', ', $cols);

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM enumeration WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM enumeration
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys);
        }
        unset($item);

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Najde ciselnikovou polozku dle ID.
     *
     * @param  int        $id
     * @param  array|null $projection
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
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj    = new Projection($projection);
        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $cols    = array_merge($sys, $ownCols);
        $select  = implode(', ', $cols);

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM enumeration WHERE id = ? AND franchise_code = ?",
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        return $proj->apply($row, $sys);
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
