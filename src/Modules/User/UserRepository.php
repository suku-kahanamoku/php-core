<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * User – DB entity layer.
 */
class UserRepository extends BaseRepository
{
    /**
     * UserRepository constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     */
    public function __construct(Database $db, string $franchiseCode)
    {
        parent::__construct($db, $franchiseCode);
        $this->table = 'user';
        $this->alias = 'u';
        $this->sys   = ['id', 'created_at', 'updated_at', 'last_login_at', 'deleted'];
        $this->own   = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'role_id',
            'status',
        ];
        $this->rel = ['role'];
    }

    /**
     * Paginated list with optional filters.
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
        $params = [$this->code];

        // Extract 'deleted' from filter (default 0 = active only).
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

        $sys         = $this->sys;
        $baseSelect  = $this->buildSelect($proj);

        // JOIN role when projection requires it OR filter references role.* columns.
        $decodedFilter  = $filter !== '' ? (json_decode($filter, true) ?? []) : [];
        $needsRoleFilter = !empty(array_filter(
            array_keys($decodedFilter),
            static fn($k) => str_starts_with((string) $k, 'role.')
        ));
        $needsRoleJoin = $proj->needsJoin('role') || $needsRoleFilter;
        $joinSql       = $needsRoleJoin ? 'LEFT JOIN role r ON r.id = u.role_id AND r.deleted = 0' : '';
        $relSel        = $needsRoleJoin ? ', r.name AS role_name' : '';

        $select = "{$baseSelect}{$relSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM user u {$joinSql} WHERE {$whereStr}",
            $params,
        )['cnt'];

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM user u {$joinSql}
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params,
        );

        foreach ($items as &$item) {
            $item = $proj->apply(
                $item,
                $sys,
                ['role' => [
                    'fk' => 'role_id',
                    'nest' => ['name' => 'role_name', 'id' => 'role_id']
                ]]
            );
        }
        unset($item);

        return $this->paginationResult($items, $total, $page, $limit);
    }

    /**
     * Find single user by ID.
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

        $sys        = $this->sys;
        $baseSelect = $this->buildSelect($proj);

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('role')) {
            $joinSql = 'LEFT JOIN role r ON r.id = u.role_id AND r.deleted = 0';
            $relSel  = ', r.name AS role_name';
        }

        $select = "{$baseSelect}{$relSel}";

        $user = $this->db->fetchOne(
            "SELECT {$select} FROM user u {$joinSql}
             WHERE u.id = ? AND u.franchise_code = ? AND u.deleted = 0",
            [$id, $this->code],
        );

        if (!$user) {
            return null;
        }

        return $proj->apply(
            $user,
            $sys,
            ['role' => [
                'fk' => 'role_id',
                'nest' => ['name' => 'role_name', 'id' => 'role_id']
            ]]
        );
    }

    /**
     * Check if email is taken.
     *
     * @param  string   $email
     * @param  int|null $excludeId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ? AND id != ? AND deleted = 0',
                [$this->code, $email, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ? AND deleted = 0',
                [$this->code, $email],
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
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM user
             WHERE role_id = ? AND franchise_code = ? AND deleted = 0',
            [$roleId, $this->code],
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
        return $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, phone
             FROM user WHERE franchise_code = ? AND email = ? AND deleted = 0',
            [$this->code, $email],
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
        $id = $this->db->insert('user', array_merge($data, [
            'franchise_code' => $this->code,
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
        $this->db->update(
            'user',
            $data,
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze uzivatele.
     *
     * @param  int $id
     * @return int  Pocet smazanych radku (0 nebo 1)
     */
    public function delete(int $id): int
    {
        return $this->db->update(
            'user',
            ['deleted' => 1],
            'id = ? AND franchise_code = ?',
            [$id, $this->code],
        );
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
        return $this->db->fetchOne(
            'SELECT u.id, u.email, u.password,
                    r.name AS role, u.first_name, u.last_name, u.status
             FROM `user` u
             JOIN `role` r ON r.id = u.role_id
             WHERE u.email = ? AND u.franchise_code = ?
             LIMIT 1',
            [$email, $this->code],
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
        $this->db->update(
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
        $row = $this->db->fetchOne(
            'SELECT password FROM `user` WHERE id = ? AND franchise_code = ?',
            [$id, $this->code],
        );

        return $row['password'] ?? null;
    }
}
