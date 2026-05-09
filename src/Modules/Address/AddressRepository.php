<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * Address – DB entity layer.
 */
class AddressRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at'];
    private const OWN = [
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
    private const REL = [];

    /**
     * AddressRepository constructor.
     *
     * @param Database $db
     * @param string $franchiseCode
     * @return void
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        $this->db   = $db;
        $this->code = $franchiseCode;
    }

    /**
     * Vrati adresy dle uzivatele a typu (billing/shipping) s podporou strankovani,
     * hledani, filtru a projekce.
     *
     * @param int $userId
     * @param string|null $type
     * @param string $sort
     * @param int $page
     * @param int $limit
     * @param string $filter
     * @param array|null $projection
     * @return array{items: array, limit: int, page: int, total: int, totalPages: int}
     */
    public function findByUser(
        int $userId,
        ?string $type = null,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
        string $filter = '',
        ?array $projection = null,
    ): array {
        $proj    = new Projection($projection);
        $orderBy = SQL_SORT($sort, 'is_default DESC');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['franchise_code = ?', 'user_id = ?'];
        $params = [$this->code, $userId];

        if ($type !== null) {
            $where[]  = 'type = ?';
            $params[] = $type;
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
            "SELECT COUNT(*) AS cnt FROM address WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM address WHERE {$whereStr}
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

    /** Find single address by ID. */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj    = new Projection($projection);
        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $cols    = array_merge($sys, $ownCols);
        $select  = implode(', ', $cols);

        $row = $this->db->fetchOne(
            "SELECT {$select} FROM address WHERE id = ? AND franchise_code = ?",
            [$id, $this->code],
        );

        if (!$row) {
            return null;
        }

        return $proj->apply($row, $sys);
    }

    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('address', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'address',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    public function delete(int $id): void
    {
        $this->db->delete('address', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    /** Clear is_default flag for all user addresses of a given type. */
    public function clearDefault(int $userId, string $type): void
    {
        $this->db->update(
            'address',
            ['is_default' => 0],
            'franchise_code = ? AND user_id = ? AND type = ?',
            [$this->code, $userId, $type],
        );
    }
}
