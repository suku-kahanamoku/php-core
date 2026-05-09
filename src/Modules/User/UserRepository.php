<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Database\Database;
use App\Utils\Projection;

/**
 * User – DB entity layer.
 */
class UserRepository
{
    private Database $db;
    private string   $code;

    private const SYS = ['id', 'created_at', 'updated_at', 'last_login_at'];
    private const OWN = ['first_name', 'last_name', 'email', 'phone', 'role_id'];
    private const REL = ['role'];

    /**
     * UserRepository constructor.
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
     * Paginated list with optional filters.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string|null $search
     * @param  string|null $role
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
        ?string $search = null,
        ?string $role = null,
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

        if ($search) {
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $s       = '%' . $search . '%';
            array_push($params, $s, $s, $s);
        }
        if ($role) {
            $where[]  = 'r.name = ?';
            $params[] = $role;
        }

        $f = SQL_FILTER($filter, 'u');
        if ($f['sql'] !== '') {
            $where[] = $f['sql'];
            array_push($params, ...$f['params']);
        }

        $whereStr = implode(' AND ', $where);

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'u.' . implode(', u.', $sys);
        $ownSel  = $ownCols ? ', u.' . implode(', u.', $ownCols) : '';

        // JOIN role when filtering by role or when projection requires it
        $needsRoleJoin = $role !== null || $proj->needsJoin('role');
        $joinSql       = $needsRoleJoin ? 'LEFT JOIN role r ON r.id = u.role_id' : '';
        $relSel        = $proj->needsJoin('role') ? ', r.name AS role_name' : '';

        $select = "{$sysSel}{$ownSel}{$relSel}";

        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM user u LEFT JOIN role r ON r.id = u.role_id WHERE {$whereStr}",
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
            $item = $proj->apply($item, $sys, ['role' => ['fk' => 'role_id', 'nest' => ['name' => 'role_name', 'id' => 'role_id']]]);
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

        $sys     = self::SYS;
        $ownCols = $proj->getOwnCols(self::OWN, self::REL);
        $sysSel  = 'u.' . implode(', u.', $sys);
        $ownSel  = $ownCols ? ', u.' . implode(', u.', $ownCols) : '';

        $joinSql = '';
        $relSel  = '';
        if ($proj->needsJoin('role')) {
            $joinSql = 'LEFT JOIN role r ON r.id = u.role_id';
            $relSel  = ', r.name AS role_name';
        }

        $select = "{$sysSel}{$ownSel}{$relSel}";

        $user = $this->db->fetchOne(
            "SELECT {$select} FROM user u {$joinSql}
             WHERE u.id = ? AND u.franchise_code = ?",
            [$id, $this->code],
        );

        if (!$user) {
            return null;
        }

        return $proj->apply($user, $sys, ['role' => ['fk' => 'role_id', 'nest' => ['name' => 'role_name', 'id' => 'role_id']]]);
    }

    /**
     * Find role_id by role name.
     *
     * @param  string   $name
     * @return int|null
     */
    public function resolveRoleId(string $name): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM role WHERE franchise_code = ? AND name = ?',
            [$this->code, $name],
        );

        return $row ? (int) $row['id'] : null;
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
                'SELECT id FROM user WHERE franchise_code = ? AND email = ? AND id != ?',
                [$this->code, $email, $excludeId],
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT id FROM user WHERE franchise_code = ? AND email = ?',
                [$this->code, $email],
            );
        }

        return (bool) $row;
    }

    /**
     * Najde uzivatele dle e-mailu (bez hesla).
     *
     * @param  string $email
     * @return array{id: int, first_name: string, last_name: string, email: string, phone: string|null}|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, phone
             FROM user WHERE franchise_code = ? AND email = ?',
            [$this->code, $email],
        ) ?: null;
    }

    /**
     * Vlozi noveho uzivatele a vrati vytvoreny zaznam.
     *
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{id: int, created_at: string, updated_at: string, last_login_at: string|null, first_name: string, last_name: string, email: string, phone: string|null, role_id: int|null, role?: array{name: string, id: int}}
     */
    public function create(array $data, ?array $projection = null): array
    {
        $id = $this->db->insert('user', array_merge($data, [
            'franchise_code' => $this->code,
            'created_at'     => date('Y-m-d H:i:s'),
        ]));

        return $this->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Aktualizuje uzivatele a vrati aktualizovany zaznam.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data
     * @param  array|null           $projection
     * @return array{id: int, created_at: string, updated_at: string, last_login_at: string|null, first_name: string, last_name: string, email: string, phone: string|null, role_id: int|null, role?: array{name: string, id: int}}
     */
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->db->update(
            'user',
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]),
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
        return $this->db->delete('user', 'id = ? AND franchise_code = ?', [$id, $this->code]);
    }

    /**
     * Najde uzivatele dle e-mailu vcetne hesla a role — pouziva se pouze pro prihlaseni.
     *
     * @param  string $email
     * @return array{id: int, email: string, password: string, role: string, first_name: string, last_name: string, status: string}|null
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
