<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Core\Franchise;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class RoleService
{
    private Role $role;

    public function __construct()
    {
        $this->role = new Role(Database::getInstance(), Franchise::code());
    }

    public function list(?bool $isActive, string $sortBy, string $sortDir): array
    {
        return $this->role->findAll($isActive, $sortBy, $sortDir);
    }

    public function get(int $id): array
    {
        $role = $this->role->findById($id);
        if (!$role) {
            Response::notFound('Role not found');
        }

        $role['user_count'] = $this->role->countUsers($id);
        return $role;
    }

    public function create(
        string $name,
        string $label,
        int $sortOrder,
        int $isActive,
    ): int
    {
        Auth::requireRole('admin');

        Validator::make(['name' => $name, 'label' => $label])
            ->required(['name', 'label'])
            ->pattern(
                'name',
                '/^[a-z0-9_]+$/',
                'Only lowercase letters, digits and underscores',
            )
            ->validate();

        if ($this->role->nameExists($name)) {
            Response::error('Role with this name already exists', 409);
        }

        return $this->role->create([
            'name'       => $name,
            'label'      => $label,
            'sort_order' => $sortOrder,
            'is_active'  => $isActive,
        ]);
    }

    public function update(int $id, array $fields): void
    {
        Auth::requireRole('admin');

        $existing = $this->role->findById($id);
        if (!$existing) {
            Response::notFound('Role not found');
        }

        $set = [];

        if (array_key_exists('name', $fields)) {
            $newName = trim(strtolower((string) $fields['name']));
            if (!preg_match('/^[a-z0-9_]+$/', $newName)) {
                Response::validationError([
                    'name' => 'Only lowercase letters, digits and underscores',
                ]);
            }
            if ($this->role->nameExists($newName, $id)) {
                Response::error('Role with this name already exists', 409);
            }
            $set['name'] = $newName;
        }

        if (array_key_exists('label', $fields)) {
            $set['label'] = trim((string) $fields['label']);
        }
        if (array_key_exists('sort_order', $fields)) {
            $set['sort_order'] = (int) $fields['sort_order'];
        }
        if (array_key_exists('is_active', $fields)) {
            $set['is_active'] = (int) $fields['is_active'];
        }

        if (!empty($set)) {
            $this->role->update($id, $set);
        }
    }

    public function replace(
        int $id,
        string $name,
        string $label,
        int $sortOrder,
        int $isActive,
    ): void
    {
        Auth::requireRole('admin');

        if (!$this->role->findById($id)) {
            Response::notFound('Role not found');
        }

        Validator::make(['name' => $name, 'label' => $label])
            ->required(['name', 'label'])
            ->pattern(
                'name',
                '/^[a-z0-9_]+$/',
                'Only lowercase letters, digits and underscores',
            )
            ->validate();

        if ($this->role->nameExists($name, $id)) {
            Response::error('Role with this name already exists', 409);
        }

        $this->role->update($id, [
            'name'       => $name,
            'label'      => $label,
            'sort_order' => $sortOrder,
            'is_active'  => $isActive,
        ]);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');

        $role = $this->role->findById($id);
        if (!$role) {
            Response::notFound('Role not found');
        }

        $builtInRoles = ['admin', 'user'];

        if (in_array($role['name'], $builtInRoles, true)) {
            Response::error("Built-in role '{$role['name']}' cannot be deleted", 409);
        }

        $userCount = $this->role->countUsers($id);
        if ($userCount > 0) {
            Response::error(
                "Cannot delete role: {$userCount} user(s) are assigned to it",
                409,
            );
        }

        $this->role->delete($id);
    }
}
