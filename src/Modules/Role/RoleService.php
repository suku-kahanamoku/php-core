<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Modules\User\UserRepository;

class RoleService
{
    private RoleRepository $role;
    private UserRepository  $user;
    private Auth $auth;

    /**
     * RoleService constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->role = new RoleRepository($db, $franchiseCode);
        $this->user = new UserRepository($db, $franchiseCode);
        $this->auth = $auth;
    }

    /**
     * Vrati strankovany seznam roli. Verejne dostupne.
     *
     * @param  int        $page
     * @param  int        $limit
     * @param  string     $sort
     * @param  string     $filter
     * @param  array|null $projection
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
        ?array $projection = null
    ): array {
        return $this->role->findAll($page, $limit, $sort, $filter, $projection);
    }

    /**
     * Vrati roli dle ID vcetne poctu prirazanych uzivatel (pole user_count).
     * Verejne dostupne. Pokud role neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   name: string,
     *   label: string,
     *   position: int,
     *   user_count: int
     * }
     */
    public function get(int $id, ?array $projection = null): array
    {
        $role = $this->role->findById($id);
        if (!$role) {
            Response::notFound('Role not found');
        }

        $role['user_count'] = $this->user->countByRoleId($id);
        return $role;
    }

    /**
     * Vytvori novou roli. Vyzaduje roli admin.
     * Name musi byt unikatni a odpovidat vzoru /^[a-z0-9_]+$/.
     *
     * @param  string     $name
     * @param  string     $label
     * @param  int        $sortOrder
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   name: string,
     *   label: string,
     *   position: int
     * }
     */
    public function create(
        string $name,
        string $label,
        int $sortOrder,
        ?array $projection = null,
    ): array {
        $this->auth->requireRole('admin');

        if ($this->role->nameExists($name)) {
            Response::error('Role with this name already exists', 409);
        }

        return $this->role->create([
            'name'     => $name,
            'label'    => $label,
            'position' => $sortOrder,
        ], $projection);
    }

    /**
     * Castecna aktualizace role (PATCH). Vyzaduje roli admin.
     * Pokud je menen name, validuje format a unikatnost.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $fields
     * @param  array|null           $projection
     * @return array{
     *   id: int,
     *   name: string,
     *   label: string,
     *   position: int
     * }
     */
    public function update(int $id, array $fields, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        $existing = $this->role->findById($id);
        if (!$existing) {
            Response::notFound('Role not found');
        }

        $set = [];

        if (array_key_exists('name', $fields)) {
            $newName = trim(strtolower((string) $fields['name']));
            if ($this->role->nameExists($newName, $id)) {
                Response::error('Role with this name already exists', 409);
            }
            if ($this->role->nameExists($newName, $id)) {
                Response::error('Role with this name already exists', 409);
            }
            $set['name'] = $newName;
        }

        if (array_key_exists('label', $fields)) {
            $set['label'] = trim((string) $fields['label']);
        }
        if (array_key_exists('position', $fields)) {
            $set['position'] = (int) $fields['position'];
        }

        if (!empty($set)) {
            $this->role->update($id, $set);
        }

        return $this->role->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada role (PUT). Vyzaduje roli admin.
     * Povinna pole: name (unikatni, /^[a-z0-9_]+$/), label.
     *
     * @param  int        $id
     * @param  string     $name
     * @param  string     $label
     * @param  int        $sortOrder
     * @param  array|null $projection
     * @return array{
     *   id: int,
     *   name: string,
     *   label: string,
     *   position: int
     * }
     */
    public function replace(
        int $id,
        string $name,
        string $label,
        int $sortOrder,
        ?array $projection = null,
    ): array {
        $this->auth->requireRole('admin');

        if (!$this->role->findById($id)) {
            Response::notFound('Role not found');
        }

        if ($this->role->nameExists($name, $id)) {
            Response::error('Role with this name already exists', 409);
        }

        $this->role->update($id, [
            'name'     => $name,
            'label'    => $label,
            'position' => $sortOrder,
        ]);

        return $this->role->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze roli. Vyzaduje roli admin.
     * Blokuje smazani vestavenych roli (admin, user) a roli s prirazanymi uzivateli (409).
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        $role = $this->role->findById($id);
        if (!$role) {
            Response::notFound('Role not found');
        }

        $builtInRoles = ['admin', 'user'];

        if (in_array($role['name'], $builtInRoles, true)) {
            Response::error("Built-in role '{$role['name']}' cannot be deleted", 409);
        }

        $userCount = $this->user->countByRoleId($id);
        if ($userCount > 0) {
            Response::error(
                "Cannot delete role: {$userCount} user(s) are assigned to it",
                409,
            );
        }

        return $this->role->delete($id);
    }
}
