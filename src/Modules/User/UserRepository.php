<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\BaseRepository;
use App\Modules\CustomerProfile\CustomerProfileRepository;
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
            'role_id',
            'status',
        ];
        $this->_rel = ['role', 'profiles'];
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
        $profileFilter = $filterArr['profile_id'] ?? null;
        if (is_array($profileFilter)) {
            $profileFilter = $profileFilter['value'] ?? null;
        }
        unset($filterArr['profile_id']);
        if ((int) $profileFilter > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM user_customer_profile uf
                WHERE uf.user_id = u.id AND uf.franchise_code = u.franchise_code
                AND uf.customer_profile_id = ?)';
            $params[] = (int) $profileFilter;
        }
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
        $needsRoleJoin = $proj->needsJoin('role') || $needsRoleFilter;
        $joinSql = $needsRoleJoin
            ? ' LEFT JOIN role r ON r.id = u.role_id AND r.deleted = 0'
            : '';
        $relSel = $needsRoleJoin ? ', r.name AS role_name, r.label AS role_label' : '';

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
            $item = $proj->apply(
                $item,
                $sys,
                [
                    'role' => [
                        'fk' => 'role_id',
                        'nest' => ['name' => 'role_name', 'label' => 'role_label', 'id' => 'role_id']
                    ]
                ]
            );
        }
        unset($item);

        if ($proj->needsJoin('profiles')) {
            $this->attachProfiles($items);
        }

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

        $select = "{$baseSelect}{$relSel}";

        $user = $this->_db->fetchOne(
            "SELECT {$select} FROM user u {$joinSql}
             WHERE u.id = ? AND u.franchise_code = ? AND u.deleted = 0",
            [$id, $this->_code],
        );

        if (!$user) {
            return null;
        }

        $user = $proj->apply(
            $user,
            $sys,
            [
                'role' => [
                    'fk' => 'role_id',
                    'nest' => ['name' => 'role_name', 'label' => 'role_label', 'id' => 'role_id']
                ]
            ]
        );
        if ($proj->needsJoin('profiles')) {
            $rows = [$user];
            $this->attachProfiles($rows);
            $user = $rows[0];
        }
        return $user;
    }

    public function syncProfiles(int $userId, array $profiles): void
    {
        $this->_db->delete('user_customer_profile', 'user_id = ? AND franchise_code = ?', [$userId, $this->_code]);
        $priorities = [];
        foreach (array_values($profiles) as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $profileId = (int) ($item['customer_profile_id'] ?? $item['id'] ?? 0);
            $priority = max(1, (int) ($item['priority'] ?? ($i + 1)));
            if ($profileId < 1 || isset($priorities[$priority])) {
                continue;
            }
            $valid = $this->_db->fetchOne(
                'SELECT id FROM customer_profile WHERE id = ? AND franchise_code = ? AND deleted = 0',
                [$profileId, $this->_code],
            );
            if (!$valid) {
                continue;
            }
            $priorities[$priority] = true;
            $this->_db->insert('user_customer_profile', [
                'franchise_code' => $this->_code,
                'user_id' => $userId,
                'customer_profile_id' => $profileId,
                'priority' => $priority,
            ]);
        }
    }

    private function attachProfiles(array &$users): void
    {
        if (!$users) {
            return;
        }
        $ids = array_map('intval', array_column($users, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $links = $this->_db->fetchAll(
            "SELECT up.user_id, up.priority, up.customer_profile_id
             FROM user_customer_profile up
             JOIN customer_profile cp ON cp.id = up.customer_profile_id
                AND cp.franchise_code = up.franchise_code AND cp.deleted = 0
             WHERE up.franchise_code = ? AND up.user_id IN ({$marks})
             ORDER BY up.user_id, up.priority",
            [$this->_code, ...$ids],
        );
        $profileRows = (new CustomerProfileRepository($this->_db, $this->_code))
            ->findByIds(array_column($links, 'customer_profile_id'));
        $profiles = [];
        foreach ($profileRows as $profile) {
            $profiles[(int) $profile['id']] = $profile;
        }
        $map = [];
        foreach ($links as $link) {
            $userId = (int) $link['user_id'];
            $profile = $profiles[(int) $link['customer_profile_id']] ?? null;
            if ($profile) {
                $profile['priority'] = (int) $link['priority'];
                $map[$userId][] = $profile;
            }
        }
        foreach ($users as &$user) {
            $user['profiles'] = $map[(int) $user['id']] ?? [];
        }
        unset($user);
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
             WHERE u.email = ? AND u.franchise_code = ? AND u.deleted = 0
             LIMIT 1',
            [$email, $this->_code],
        ) ?: null;
    }

    /** Vrati autentizacni data uzivatele dle ID. */
    public function findForLoginById(int $id): ?array
    {
        return $this->_db->fetchOne(
            'SELECT u.id, u.email, u.password,
                    r.name AS role, u.first_name, u.last_name, u.status
             FROM `user` u
             JOIN `role` r ON r.id = u.role_id AND r.deleted = 0
             WHERE u.id = ? AND u.franchise_code = ? AND u.deleted = 0
             LIMIT 1',
            [$id, $this->_code],
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
