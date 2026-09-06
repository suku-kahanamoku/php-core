<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * User – DB vrstva entity.
 */
class UserRepository extends BaseRepository
{
    /**
     * Konstruktor tridy UserRepository.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->_table = 'user';
        $this->_alias = 'u';
        $this->_sys   = ['id', 'created_at', 'updated_at', 'last_login_at', 'deleted'];
        $this->_own   = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'client_type_id',
            'profile',
            'role_id',
            'status',
        ];
        $this->_rel = ['role', 'client_type'];
        $this->_jsonCols = ['profile'];
    }

    /**
     * Strankovany seznam s volitelnymi filtry.
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
     *     last_login_at: string|null,
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string|null,
     *     role_id: int|null,
     *     role?: array{name: string, id: int}
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
        $orderBy = SQL_SORT($sort, 'u.created_at DESC', 'u');

        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $where  = ['u.franchise_code = ?'];
        $params = [$this->_code];

        // Extrahuj 'deleted' z filtru (vychozi 0 = pouze aktivni).
        $filterArr  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $deletedVal = isset($filterArr['deleted']) ? (int) $filterArr['deleted'] : 0;
        unset($filterArr['deleted']);
        $filter = count($filterArr) > 0 ? json_encode($filterArr) : '';
        $where[]  = 'u.deleted = ?';
        $params[] = $deletedVal;

        $f = SQL_FILTER($filter, 'u');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys         = $this->_sys;
        $baseSelect  = $this->_buildSelect($proj);

        // JOIN role kdyz projekce vyzaduje nebo filtr odkazuje na sloupce role.*.
        $decodedFilter  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $needsRoleFilter = !empty(array_filter(
            array_keys($decodedFilter),
            static fn($k) => str_starts_with((string) $k, 'role.')
        ));
        $needsClientTypeFilter = !empty(array_filter(
            array_keys($decodedFilter),
            static fn($k) => str_starts_with((string) $k, 'client_type.')
        ));
        $needsRoleJoin = $proj->needsJoin('role') || $needsRoleFilter;
        $needsClientTypeJoin = $proj->needsJoin('client_type') || $needsClientTypeFilter;
        $joinSql = $needsRoleJoin
            ? ' LEFT JOIN role r ON r.id = u.role_id AND r.deleted = 0'
            : '';
        if ($needsClientTypeJoin) {
            $joinSql .= " LEFT JOIN enumeration ct ON ct.id = u.client_type_id
                AND ct.franchise_code = u.franchise_code
                AND ct.type = 'client_type' AND ct.deleted = 0";
        }
        $relSel = $needsRoleJoin ? ', r.name AS role_name, r.label AS role_label' : '';
        if ($needsClientTypeJoin) {
            $relSel .= ', ct.syscode AS client_type_syscode, ct.label AS client_type_label';
        }

        $select = "{$baseSelect}{$relSel}";

        $total = (int) $this->_db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM user u {$joinSql} WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->_db->fetchAll(
            "SELECT {$select} FROM user u {$joinSql}
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            if (isset($item['profile'])) {
                $item['profile'] = $item['profile'] ? json_decode($item['profile'], true) : null;
            }
            $item = $proj->apply(
                $item,
                $sys,
                [
                    'role' => [
                        'fk' => 'role_id',
                        'nest' => ['name' => 'role_name', 'label' => 'role_label', 'id' => 'role_id']
                    ],
                    'client_type' => [
                        'fk' => 'client_type_id',
                        'nest' => [
                            'syscode' => 'client_type_syscode',
                            'label' => 'client_type_label',
                            'id' => 'client_type_id'
                        ]
                    ]
                ]
            );
        }
        unset($item);

        return $this->_resultList($items, $total, $page, $limit);
    }

    /**
     * Najde jednoho uzivatele dle ID.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   last_login_at: string|null,
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   phone: string|null,
     *   role_id: int|null,
     *   role?: array{name: string, id: int}
     * }|null
     */
    public function findById(int $id, ?array $projection = null): ?array
    {
        $proj = new Projection($projection);

        $sys        = $this->_sys;
        $baseSelect = $this->_buildSelect($proj);

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('role')) {
            $joinSql = 'LEFT JOIN role r ON r.id = u.role_id AND r.deleted = 0';
            $relSel  = ', r.name AS role_name, r.label AS role_label';
        }
        if ($proj->needsJoin('client_type')) {
            $joinSql .= " LEFT JOIN enumeration ct ON ct.id = u.client_type_id
                AND ct.franchise_code = u.franchise_code
                AND ct.type = 'client_type' AND ct.deleted = 0";
            $relSel .= ', ct.syscode AS client_type_syscode, ct.label AS client_type_label';
        }

        $select = "{$baseSelect}{$relSel}";

        $user = $this->_db->fetchOne(
            "SELECT {$select} FROM user u {$joinSql}
             WHERE u.id = ? AND u.franchise_code = ? AND u.deleted = 0",
            [$id, $this->_code],
        );

        if (!$user) {
            return null;
        }

        if (isset($user['profile'])) {
            $user['profile'] = $user['profile'] ? json_decode($user['profile'], true) : null;
        }

        return $proj->apply(
            $user,
            $sys,
            [
                'role' => [
                    'fk' => 'role_id',
                    'nest' => ['name' => 'role_name', 'label' => 'role_label', 'id' => 'role_id']
                ],
                'client_type' => [
                    'fk' => 'client_type_id',
                    'nest' => [
                        'syscode' => 'client_type_syscode',
                        'label' => 'client_type_label',
                        'id' => 'client_type_id'
                    ]
                ]
            ]
        );
    }

    /**
     * Overi, zda je email jiz pouzit.
     *
     * @param  string   $email
     * @param  int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->_db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ? AND id != ? AND deleted = 0',
                [$this->_code, $email, $excludeId],
            );
        } else {
            $row = $this->_db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ? AND deleted = 0',
                [$this->_code, $email],
            );
        }

        return (bool) $row;
    }

    /**
     * Vrati pocet uzivatelu prirazanych k dane roli.
     *
     * @param  int $roleId
     * @return int
     */
    public function countByRoleId(int $roleId): int
    {
        return (int) $this->_db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM user
             WHERE role_id = ? AND franchise_code = ? AND deleted = 0',
            [$roleId, $this->_code],
        )['cnt'];
    }

    /**
     * Najde uzivatele dle e-mailu (bez hesla).
     *
     * @param  string $email
     * @return array{
     *   id: int,
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   phone: string|null
     * }|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->_db->fetchOne(
            'SELECT id, first_name, last_name, email, phone
             FROM user WHERE franchise_code = ? AND email = ? AND deleted = 0',
            [$this->_code, $email],
        ) ?: null;
    }

    /**
     * Vlozi noveho uzivatele a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   last_login_at: string|null,
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   phone: string|null,
     *   role_id: int|null,
     *   role?: array{name: string, id: int}
     * }
     */
    public function create(array $data, ?array $projection = null): array
    {
        if (isset($data['profile']) && is_array($data['profile'])) {
            $data['profile'] = json_encode($data['profile'], JSON_UNESCAPED_UNICODE);
        }

        $id = $this->_db->insert('user', array_merge($data, [
            'franchise_code' => $this->_code,
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje uzivatele a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   created_at: string,
     *   updated_at: string,
     *   last_login_at: string|null,
     *   first_name: string,
     *   last_name: string,
     *   email: string,
     *   phone: string|null,
     *   role_id: int|null,
     *   role?: array{name: string, id: int}
     * }
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $data = $this->_patchJsonCols($id, $data);

        $this->_db->update(
            'user',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->_code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Najde uzivatele dle e-mailu vcetne hesla a role — pouziva se pouze pro prihlaseni.
     *
     * @param  string $email
     * @return array{
     *   id: int,
     *   email: string,
     *   password: string,
     *   role: string,
     *   first_name: string,
     *   last_name: string,
     *   status: string
     * }|null
     */
    public function findForLogin(string $email): ?array
    {
        return $this->_db->fetchOne(
            'SELECT u.id, u.email, u.password,
                    r.name AS role, u.first_name, u.last_name, u.status
             FROM `user` u
             JOIN `role` r ON r.id = u.role_id AND r.deleted = 0
             WHERE u.email = ? AND u.franchise_code = ?
             LIMIT 1',
            [$email, $this->_code],
        ) ?: null;
    }

    /**
     * Aktualizuje cas posledniho prihlaseni uzivatele.
     *
     * @param  int $id
     * @return void
     */
    public function touchLastLogin(int $id): void
    {
        $this->_db->update(
            'user',
            ['last_login_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id],
        );
    }

    /**
     * Vrati hash hesla uzivatele (pouziva se pro zmenu hesla).
     *
     * @param  int      $id
     * @return string|null
     */
    public function findPasswordHash(int $id): ?string
    {
        $row = $this->_db->fetchOne(
            'SELECT password FROM `user` WHERE id = ? AND franchise_code = ?',
            [$id, $this->_code],
        );

        return $row['password'] ?? null;
    }
}
