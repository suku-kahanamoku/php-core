<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class UserService
{
    private UserRepository $user;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->user = new UserRepository($db, $franchiseCode);
        $this->auth = $auth;
    }

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
            $roleId = $this->user->resolveRoleId($roleName);
            if ($roleId === null) {
                Response::validationError(['role' => 'Unknown role']);
            }
        } else {
            $roleId = $this->user->resolveRoleId('user');
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
                $roleId = $this->user->resolveRoleId((string) $input['role']);
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
            $roleId   = $this->user->resolveRoleId($roleName);
            if ($roleId === null) {
                Response::validationError(['role' => 'Unknown role']);
            }
            $set['role_id'] = $roleId;
        }

        return $this->user->update($id, $set, $projection);
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        $this->user->delete($id);
    }

}
