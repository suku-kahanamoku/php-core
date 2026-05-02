<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;

class RoleApi
{
    private RoleService $service;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->service = new RoleService($db, $franchiseCode);
    }

    /** GET /roles */
    public function list(Request $request): void
    {
        $isActive = $request->get('is_active');
        $items    = $this->service->list(
            $isActive !== null ? (bool)(int) $isActive : null,
            (string) $request->get('sort_by', 'sort_order'),
            (string) $request->get('sort_dir', 'ASC'),
        );
        Response::success($items);
    }

    /** GET /roles/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /roles */
    public function create(Request $request): void
    {
        $id = $this->service->create(
            trim(strtolower((string) $request->get('name', ''))),
            trim((string) $request->get('label', '')),
            (int) $request->get('sort_order', 0),
            (int) $request->get('is_active', 1),
        );
        Response::created(['id' => $id], 'Role created');
    }

    /** PATCH /roles/:id */
    public function update(Request $request, array $params): void
    {
        $fields     = [];
        $roleFields = ['name', 'label', 'sort_order', 'is_active'];

        foreach ($roleFields as $f) {
            if ($request->get($f) !== null) {
                $fields[$f] = $request->get($f);
            }
        }
        $this->service->update((int) $params['id'], $fields);
        Response::success(null, 'Role updated');
    }

    /** PUT /roles/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace(
            (int) $params['id'],
            trim(strtolower((string) $request->get('name', ''))),
            trim((string) $request->get('label', '')),
            (int) $request->get('sort_order', 0),
            (int) $request->get('is_active', 1),
        );
        Response::success(null, 'Role replaced');
    }

    /** DELETE /roles/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Role deleted');
    }
}
