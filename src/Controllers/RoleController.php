<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Request;
use App\Core\Response;

class RoleController
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db   = Database::getInstance();
        $this->code = Franchise::code();
    }

    /** GET /roles */
    public function list(Request $request): void
    {
        $items = $this->db->fetchAll(
            'SELECT id, name, label, sort_order, is_active, created_at, updated_at
             FROM role WHERE franchise_code = ? ORDER BY sort_order ASC, name ASC',
            [$this->code]
        );

        Response::success($items);
    }

    /** GET /roles/:id */
    public function get(Request $request, array $params): void
    {
        $id   = (int) $params['id'];
        $role = $this->db->fetchOne(
            'SELECT id, name, label, sort_order, is_active, created_at, updated_at
             FROM role WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );

        if (!$role) {
            Response::notFound('Role not found');
        }

        $role['user_count'] = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM user WHERE role_id = ? AND franchise_code = ? AND status != "deleted"',
            [$id, $this->code]
        )['cnt'];

        Response::success($role);
    }

    /** POST /roles */
    public function create(Request $request): void
    {
        Auth::requireRole('admin');

        $name  = trim(strtolower((string) $request->get('name', '')));
        $label = trim((string) $request->get('label', ''));

        $errors = [];
        if ($name  === '') $errors['name']  = 'Required';
        if ($label === '') $errors['label'] = 'Required';
        if (!preg_match('/^[a-z0-9_]+$/', $name)) $errors['name'] = 'Only lowercase letters, digits and underscores';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM role WHERE franchise_code = ? AND name = ?',
            [$this->code, $name]
        );
        if ($exists) {
            Response::error('Role with this name already exists', 409);
        }

        $id = $this->db->insert('role', [
            'franchise_code' => $this->code,
            'name'           => $name,
            'label'          => $label,
            'sort_order'     => (int) $request->get('sort_order', 0),
            'is_active'      => (int) ($request->get('is_active', 1)),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Role created');
    }

    /** PATCH /roles/:id */
    public function update(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id   = (int) $params['id'];
        $role = $this->db->fetchOne(
            'SELECT id, name FROM role WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$role) {
            Response::notFound('Role not found');
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];

        if (($v = $request->get('label')) !== null)      $set['label']      = trim((string) $v);
        if (($v = $request->get('sort_order')) !== null) $set['sort_order'] = (int) $v;
        if (($v = $request->get('is_active')) !== null)  $set['is_active']  = (int) $v;

        if (($v = $request->get('name')) !== null) {
            $newName = trim(strtolower((string) $v));
            if (!preg_match('/^[a-z0-9_]+$/', $newName)) {
                Response::validationError(['name' => 'Only lowercase letters, digits and underscores']);
            }
            $dup = $this->db->fetchOne(
                'SELECT id FROM role WHERE franchise_code = ? AND name = ? AND id != ?',
                [$this->code, $newName, $id]
            );
            if ($dup) {
                Response::error('Role with this name already exists', 409);
            }
            $set['name'] = $newName;
        }

        $this->db->update('role', $set, 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Role updated');
    }

    /** PUT /roles/:id */
    public function replace(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id   = (int) $params['id'];
        $role = $this->db->fetchOne(
            'SELECT id FROM role WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$role) {
            Response::notFound('Role not found');
        }

        $name  = trim(strtolower((string) $request->get('name', '')));
        $label = trim((string) $request->get('label', ''));

        $errors = [];
        if ($name  === '') $errors['name']  = 'Required';
        if ($label === '') $errors['label'] = 'Required';
        if (!preg_match('/^[a-z0-9_]+$/', $name)) $errors['name'] = 'Only lowercase letters, digits and underscores';
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $dup = $this->db->fetchOne(
            'SELECT id FROM role WHERE franchise_code = ? AND name = ? AND id != ?',
            [$this->code, $name, $id]
        );
        if ($dup) {
            Response::error('Role with this name already exists', 409);
        }

        $this->db->update('role', [
            'name'       => $name,
            'label'      => $label,
            'sort_order' => (int) ($request->get('sort_order') ?? 0),
            'is_active'  => (int) ($request->get('is_active')  ?? 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        Response::success(null, 'Role replaced');
    }

    /** DELETE /roles/:id */
    public function delete(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id   = (int) $params['id'];
        $role = $this->db->fetchOne(
            'SELECT id, name FROM role WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$role) {
            Response::notFound('Role not found');
        }

        // Protect built-in roles
        if (in_array($role['name'], ['admin', 'user'], true)) {
            Response::error("Built-in role '{$role['name']}' cannot be deleted", 409);
        }

        // Protect if users are assigned
        $userCount = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM user WHERE role_id = ? AND franchise_code = ?',
            [$id, $this->code]
        )['cnt'];

        if ($userCount > 0) {
            Response::error("Cannot delete role: {$userCount} user(s) are assigned to it", 409);
        }

        $this->db->delete('role', 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Role deleted');
    }
}
