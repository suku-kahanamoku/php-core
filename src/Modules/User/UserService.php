<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Role\RoleRepository;
use App\Modules\Router\Response;

class UserService extends BaseService
{
    private UserRepository $_user;
    private RoleRepository $_role;

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

        if (!$this->_auth->hasRole('admin') && $this->_auth->id() !== $id) {
            Response::forbidden();
        }

        $user = $this->_user->findById($id, $projection);
        $this->_requireEntity($user, 'User not found');

        return $user;
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

        return $this->_user->create([
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'email'      => $input['email'],
            'phone'      => $input['phone'] ?? null,
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
     * @param  array<string, mixed> $input  first_name, last_name, phone, role (admin only)
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->require();

        if (!$this->_auth->hasRole('admin') && $this->_auth->id() !== $id) {
            Response::forbidden();
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

        return !empty($set)
            ? $this->_user->update($id, $set, $projection)
            : ($this->_user->findById($id, $projection) ?? ['id' => $id]);
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

        if (!$this->_auth->hasRole('admin') && $this->_auth->id() !== $id) {
            Response::forbidden();
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

        return $this->_user->update($id, $set, $projection);
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
}
