<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Address – DB entity layer.
 */
class AddressRepository extends BaseRepository
{
    /**
     * AddressRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'address';
        $this->alias = 'a';
        $this->rel   = ['user'];
        $this->own   = [
            'user_id',
            'type',
            'company',
            'name',
            'street',
            'city',
            'zip',
            'country',
            'is_default',
        ];
    }

    /**
     * Vrati strankovany seznam vsech adres.
     *
     * @param  int        $page
     * @param  int        $limit
     * @param  string     $sort
     * @param  string     $filter
     * @param  array|null $projection
     * @return array{items: list<array<string,mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function findAll(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'a.created_at DESC', 'a');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['a.franchise_code = ?'];
        $params = [$this->code];

        // Extract 'deleted' from filter (default 0 = active only).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'a.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'a');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);
        $sys      = $this->sys;
        $baseSelect = $this->buildSelect($proj);

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('user')) {
            $joinSql = 'LEFT JOIN user u ON u.id = a.user_id AND u.deleted = 0';
            $relSel  = ', u.id AS user_id_join, u.email AS user_email, u.first_name AS user_first_name, u.last_name AS user_last_name';
        }
        $select = "{$baseSelect}{$relSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM address a {$joinSql} WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM address a {$joinSql} WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply($item, $sys, ['user' => [
                'fk'   => 'user_id',
                'nest' => ['id' => 'user_id_join', 'email' => 'user_email', 'first_name' => 'user_first_name', 'last_name' => 'user_last_name'],
            ]]);
        }
        unset($item);

        return $this->paginationResult($items, $total, $page, $limit);
    }

    /**
     * Najde adresu dle ID, volitelne s user JOINem.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj       = new Projection($projection);
        $sys        = $this->sys;
        $baseSelect = $this->buildSelect($proj);

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('user')) {
            $joinSql = 'LEFT JOIN user u ON u.id = a.user_id AND u.deleted = 0';
            $relSel  = ', u.id AS user_id_join, u.email AS user_email, u.first_name AS user_first_name, u.last_name AS user_last_name';
        }
        $select = "{$baseSelect}{$relSel}";

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM address a {$joinSql} WHERE a.id = ? AND a.franchise_code = ? AND a.deleted = 0",
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        return $proj->apply($row, $sys, ['user' => [
            'fk'   => 'user_id',
            'nest' => ['id' => 'user_id_join', 'email' => 'user_email', 'first_name' => 'user_first_name', 'last_name' => 'user_last_name'],
        ]]);
    }

    /**
     * Vlozi novou adresu a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   user_id: int,
     *   type: string,
     *   company: string|null,
     *   name: string,
     *   street: string,
     *   city: string,
     *   zip: string,
     *   country: string,
     *   is_default: int
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('address', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje adresu a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   user_id: int,
     *   type: string,
     *   company: string|null,
     *   name: string,
     *   street: string,
     *   city: string,
     *   zip: string,
     *   country: string,
     *   is_default: int
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'address',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze adresu.
     *
     * @param int $id
     * @return int Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->update(
            'address',
            ['deleted' => 1],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
    }

    /**
     * Oznaci vsechny adresy daneho uzivatele a typu jako nevychozi (is_default = 0).
     *
     * @param int $userId
     * @param string $type
     * @return int Pocet aktualizovanych radku
     */
    public function clearDefault(int $userId, string $type): int
    {
        return $this->db->update(
            'address',
            ['is_default' => 0],
            'franchise_code = ? AND user_id = ? AND type = ?',
            [$this->code, $userId, $type],
        );
    }
}
