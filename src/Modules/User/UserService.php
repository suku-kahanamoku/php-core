<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Enumeration\EnumerationRepository;
use App\Modules\Role\RoleRepository;
use App\Modules\Router\Response;
use App\Utils\QueryPolicy;

class UserService extends BaseService
{
    private const SELF_FIELDS = [
        'id', 'first_name', 'last_name', 'email', 'phone', 'profile',
        'client_type_id', 'client_type', 'role',
    ];
    private UserRepository $_user;
    private RoleRepository $_role;
    private EnumerationRepository $_enumeration;

    /**
     * Konstruktor tridy UserService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_user = new UserRepository($db, $franchiseCode);
        $this->_role = new RoleRepository($db, $franchiseCode);
        $this->_enumeration = new EnumerationRepository($db, $franchiseCode);
        $this->_auth = $auth;
    }

    /**
     * Vrati strankovany seznam uzivatelu. Vyzaduje roli admin.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   limit: int,
     *   totalPages: int
     * }
     */
    public function list(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $this->_auth->requireRole('admin');
        return $this->_user->findAll(
            $page,
            $limit,
            $sort,
            $filter,
            $projection,
        );
    }

    /**
     * Vrati uzivatele dle ID.
     * Vyzaduje prihlaseni; uzivatel vidi pouze sebe, admin vidi vsechny.
     * Pokud uzivatel neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $this->_auth->require();

        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin && $this->_auth->id() !== $id) {
            Response::notFound('User not found');
        }

        if (!$isAdmin) {
            $projection = QueryPolicy::projection($projection, self::SELF_FIELDS);
        }

        $user = $this->_user->findById($id, $projection);
        $this->_requireEntity($user, 'User not found');

        return $isAdmin ? $user : QueryPolicy::fields($user, self::SELF_FIELDS);
    }

    /**
     * Vytvori noveho uzivatele. Vyzaduje roli admin.
     * Povinna pole: first_name, last_name, email, password (min 8 znaku).
     * Email musi byt unikatni. Heslo je ulozeno jako bcrypt hash.
     *
     * @param  array<string, mixed> $input
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        if ($this->_user->emailExists($input['email'])) {
            Response::error('Email already registered', 409);
        }

        $roleId = $input['role_id'] ?? null;
        if ($roleId !== null) {
            VALIDATOR(['role_id' => $this->_role->findById((int) $roleId) ? 'ok' : ''])
                ->required('role_id')
                ->validate();
            $roleId = (int) $roleId;
        } else {
            $roleId = $this->_role->findIdByName('user');
        }

        $clientTypeId = $this->_validatedClientTypeId($input['client_type_id'] ?? null);

        return $this->_user->create([
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'email'      => $input['email'],
            'phone'      => $input['phone'] ?? null,
            'client_type_id' => $clientTypeId,
            'profile'    => is_array($input['profile'] ?? null) ? $input['profile'] : null,
            'password'   => password_hash(
                $input['password'],
                PASSWORD_BCRYPT,
                ['cost' => 12],
            ),
            'role_id' => $roleId,
            'status'  => 'active',
        ], $projection);
    }

    /**
     * Castecna aktualizace uzivatele (PATCH).
     * Vyzaduje prihlaseni; uzivatel muze menit vlastni profil, admin muze menit kohokoliv a take roli.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input  first_name, last_name, phone, email/status/role_id (admin only)
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->require();

        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin && $this->_auth->id() !== $id) {
            Response::notFound('User not found');
        }

        $user = $this->_user->findById($id);
        $this->_requireEntity($user, 'User not found');

        $set        = [];
        $textFields = ['first_name', 'last_name', 'phone'];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }

        if ($this->_auth->hasRole('admin')) {
            if (array_key_exists('email', $input) && $input['email'] !== null) {
                $email = trim((string) $input['email']);
                if ($this->_user->emailExists($email, $id)) {
                    Response::error('Email already registered', 409);
                }
                $set['email'] = $email;
            }

            if (array_key_exists('status', $input) && $input['status'] !== null) {
                $set['status'] = (string) $input['status'];
            }

            if (array_key_exists('role_id', $input) && $input['role_id'] !== null) {
                VALIDATOR(
                    [
                        'role_id' => $this->_role->findById((int) $input['role_id'])
                            ? 'ok' : ''
                    ]
                )
                    ->required('role_id')
                    ->validate();
                $set['role_id'] = (int) $input['role_id'];
            }

            if (array_key_exists('client_type_id', $input)) {
                $set['client_type_id'] = $this->_validatedClientTypeId($input['client_type_id']);
            }

            if (array_key_exists('profile', $input) && is_array($input['profile'])) {
                $set['profile'] = $input['profile'];
            }
        }

        $result = !empty($set)
            ? $this->_user->update($id, $set, $projection)
            : ($this->_user->findById($id, $projection) ?? ['id' => $id]);
        return $isAdmin ? $result : QueryPolicy::fields($result, self::SELF_FIELDS);
    }

    /**
     * Uplna nahrada uzivatele (PUT). Vyzaduje prihlaseni; uzivatel nebo admin.
     * Povinna pole: first_name, last_name.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function replace(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->require();

        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin && $this->_auth->id() !== $id) {
            Response::notFound('User not found');
        }

        $user = $this->_user->findById($id);
        $this->_requireEntity($user, 'User not found');

        $set = [
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'phone'      => $input['phone'] ?? null,
        ];

        if ($this->_auth->hasRole('admin')) {
            if (array_key_exists('role_id', $input) && $input['role_id'] !== null) {
                VALIDATOR(
                    [
                        'role_id' => $this->_role->findById((int) $input['role_id'])
                            ? 'ok' : ''
                    ]
                )
                    ->required('role_id')
                    ->validate();
                $set['role_id'] = (int) $input['role_id'];
            }
        }

        $result = $this->_user->update($id, $set, $projection);
        return $isAdmin ? $result : QueryPolicy::fields($result, self::SELF_FIELDS);
    }

    /**
     * Smaze uzivatele. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->_auth->requireRole('admin');

        $user = $this->_user->findById($id);
        $this->_requireEntity($user, 'User not found');

        return $this->_user->hardDelete($id);
    }

    /**
     * Soft-smazani uzivatele (oznaci jako smazaneho, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $user = $this->_user->findById($id);
        $this->_requireEntity($user, 'User not found');

        return $this->_user->softDelete($id);
    }

    /**
     * Overi, ze ID ukazuje na aktivni polozku ciselnika client_type ve stejne franchise.
     */
    private function _validatedClientTypeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $item = $this->_enumeration->findById((int) $value);
        if (!$item || ($item['type'] ?? null) !== 'client_type') {
            Response::error('Invalid client type', 422);
        }

        return (int) $value;
    }
}
