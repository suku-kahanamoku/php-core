<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Role\RoleRepository;
use App\Modules\Router\Response;

class UserService
{
    private UserRepository $user;
    private RoleRepository $role;
    private Auth $auth;

    /**
     * UserService constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->user = new UserRepository($db, $franchiseCode);
        $this->role = new RoleRepository($db, $franchiseCode);
        $this->auth = $auth;
    }

    /**
     * Vrati strankovany seznam uzivatelu. Vyzaduje roli admin.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string|null $search
     * @param  string|null $role
     * @param  string      $sort
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(
        int $page,
        int $limit,
        ?string $search,
        ?string $role,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        $this->auth->requireRole('admin');
        return $this->user->findAll(
            $page,
            $limit,
            $search,
            $role,
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
        $this->auth->require();

        if (!$this->auth->hasRole('admin') && $this->auth->id() !== $id) {
            Response::forbidden();
        }

        $user = $this->user->findById($id, $projection);
        if (!$user) {
            Response::notFound('User not found');
        }

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
        $this->auth->requireRole('admin');

        VALIDATOR($input)
            ->required(['first_name', 'last_name', 'email', 'password'])
            ->email('email')
            ->minLength('password', 8)
            ->validate();

        if ($this->user->emailExists($input['email'])) {
            Response::error('Email already registered', 409);
        }

        $roleName = $input['role'] ?? null;
        if ($roleName !== null) {
            $roleId = $this->role->findIdByName($roleName);
            if ($roleId === null) {
                Response::validationError(['role' => 'Unknown role']);
            }
        } else {
            $roleId = $this->role->findIdByName('user');
        }

        return $this->user->create([
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
        $this->auth->require();

        if (!$this->auth->hasRole('admin') && $this->auth->id() !== $id) {
            Response::forbidden();
        }

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        $set        = [];
        $textFields = ['first_name', 'last_name', 'phone'];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }

        if ($this->auth->hasRole('admin')) {
            if (array_key_exists('role', $input) && $input['role'] !== null) {
                $roleId = $this->role->findIdByName((string) $input['role']);
                if ($roleId === null) {
                    Response::validationError(['role' => 'Unknown role']);
                }
                $set['role_id'] = $roleId;
            }
        }

        return !empty($set)
            ? $this->user->update($id, $set, $projection)
            : ($this->user->findById($id, $projection) ?? ['id' => $id]);
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
        $this->auth->require();

        if (!$this->auth->hasRole('admin') && $this->auth->id() !== $id) {
            Response::forbidden();
        }

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        VALIDATOR($input)
            ->required(['first_name', 'last_name'])
            ->validate();

        $set = [
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'phone'      => $input['phone'] ?? null,
        ];

        if ($this->auth->hasRole('admin')) {
            $roleName = $input['role'] ?? 'user';
            $roleId   = $this->role->findIdByName($roleName);
            if ($roleId === null) {
                Response::validationError(['role' => 'Unknown role']);
            }
            $set['role_id'] = $roleId;
        }

        return $this->user->update($id, $set, $projection);
    }

    /**
     * Smaze uzivatele. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        return $this->user->delete($id);
    }
}
