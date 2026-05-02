<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Core\Franchise;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class UserService
{
    private User $user;

    public function __construct()
    {
        $this->user = new User(Database::getInstance(), Franchise::code());
    }

    public function list(
        int $page, int $limit,
        ?string $search, ?string $role, ?string $status,
        string $sortBy, string $sortDir
    ): array {
        Auth::requireRole('admin');
        return $this->user->findAll($page, $limit, $search, $role, $status, $sortBy, $sortDir);
    }

    public function get(int $id): array
    {
        Auth::require();

        if (!Auth::hasRole('admin') && Auth::id() !== $id) {
            Response::forbidden();
        }

        $user = $this->user->findById($id);
        if (!$user) {
            Response::notFound('User not found');
        }

        return $user;
    }

    public function create(array $input): int
    {
        Auth::requireRole('admin');

        Validator::make($input)
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
            'password'   => password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id'    => $roleId,
            'status'     => $input['status'] ?? 'active',
        ]);
    }

    public function update(int $id, array $input): void
    {
        Auth::require();

        if (!Auth::hasRole('admin') && Auth::id() !== $id) {
            Response::forbidden();
        }

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        $set = [];
        $textFields = ['first_name', 'last_name', 'phone'];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }

        if (Auth::hasRole('admin')) {
            if (array_key_exists('role', $input) && $input['role'] !== null) {
                $roleId = $this->user->resolveRoleId((string) $input['role']);
                if ($roleId === null) {
                    Response::validationError(['role' => 'Unknown role']);
                }
                $set['role_id'] = $roleId;
            }
            if (array_key_exists('status', $input) && $input['status'] !== null) {
                $set['status'] = (string) $input['status'];
            }
        }

        if (!empty($set)) {
            $this->user->update($id, $set);
        }
    }

    public function replace(int $id, array $input): void
    {
        Auth::require();

        if (!Auth::hasRole('admin') && Auth::id() !== $id) {
            Response::forbidden();
        }

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        Validator::make($input)
            ->required(['first_name', 'last_name'])
            ->validate();

        $set = [
            'first_name' => $input['first_name'],
            'last_name'  => $input['last_name'],
            'phone'      => $input['phone'] ?? null,
        ];

        if (Auth::hasRole('admin')) {
            $roleName = $input['role'] ?? 'user';
            $roleId   = $this->user->resolveRoleId($roleName);
            if ($roleId === null) {
                Response::validationError(['role' => 'Unknown role']);
            }
            $set['role_id'] = $roleId;
            $set['status']  = $input['status'] ?? 'active';
        }

        $this->user->update($id, $set);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');

        if (!$this->user->findById($id)) {
            Response::notFound('User not found');
        }

        $this->user->softDelete($id);
    }

}
