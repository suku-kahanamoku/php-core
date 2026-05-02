<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class UserController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * GET /users
     * Admin only
     */
    public function index(Request $request): void
    {
        Auth::requireRole('admin');

        $page     = max(1, (int) $request->get('page', 1));
        $limit    = min(100, max(1, (int) $request->get('limit', 20)));
        $offset   = ($page - 1) * $limit;
        $search   = $request->get('search');
        $role     = $request->get('role');
        $status   = $request->get('status');

        $where  = ['1=1'];
        $params = [];

        if ($search) {
            $where[]  = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
            $s = '%' . $search . '%';
            $params = [...$params, $s, $s, $s];
        }
        if ($role) {
            $where[]  = 'role = ?';
            $params[] = $role;
        }
        if ($status) {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM user WHERE {$whereStr}", $params)['cnt'] ?? 0;
        $users = $this->db->fetchAll(
            "SELECT id, first_name, last_name, email, role, status, phone, created_at, last_login_at
             FROM user WHERE {$whereStr} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        Response::success([
            'items'      => $users,
            'total'      => (int) $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * GET /users/:id
     */
    public function show(Request $request, array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];

        // Non-admins can only see themselves
        if (!Auth::hasRole('admin') && Auth::id() !== $id) {
            Response::forbidden();
        }

        $user = $this->db->fetchOne(
            'SELECT id, first_name, last_name, email, role, status, phone, address_id, created_at, updated_at, last_login_at
             FROM user WHERE id = ?',
            [$id]
        );

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::success($user);
    }

    /**
     * POST /users
     * Admin only
     */
    public function store(Request $request): void
    {
        Auth::requireRole('admin');

        $data   = $this->validate($request);
        $errors = $data['errors'] ?? [];
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $exists = $this->db->fetchOne('SELECT id FROM user WHERE email = ?', [$data['email']]);
        if ($exists) {
            Response::error('Email already registered', 409);
        }

        $id = $this->db->insert('user', [
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'] ?? null,
            'password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'role'          => $data['role'] ?? 'user',
            'status'        => $data['status'] ?? 'active',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'User created');
    }

    /**
     * PUT /users/:id
     */
    public function update(Request $request, array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];

        if (!Auth::hasRole('admin') && Auth::id() !== $id) {
            Response::forbidden();
        }

        $user = $this->db->fetchOne('SELECT id FROM user WHERE id = ?', [$id]);
        if (!$user) {
            Response::notFound('User not found');
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];

        foreach (['first_name', 'last_name', 'phone'] as $field) {
            $val = $request->get($field);
            if ($val !== null) {
                $set[$field] = trim((string) $val);
            }
        }

        // Only admin can change role/status
        if (Auth::hasRole('admin')) {
            foreach (['role', 'status'] as $field) {
                $val = $request->get($field);
                if ($val !== null) {
                    $set[$field] = (string) $val;
                }
            }
        }

        if (count($set) > 1) {
            $this->db->update('user', $set, 'id = ?', [$id]);
        }

        Response::success(null, 'User updated');
    }

    /**
     * DELETE /users/:id
     * Admin only – soft delete
     */
    public function destroy(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $user = $this->db->fetchOne('SELECT id FROM user WHERE id = ?', [$id]);
        if (!$user) {
            Response::notFound('User not found');
        }

        $this->db->update('user', [
            'status'     => 'deleted',
            'deleted_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        Response::success(null, 'User deleted');
    }

    private function validate(Request $request): array
    {
        $errors = [];
        $first  = trim((string) $request->get('first_name', ''));
        $last   = trim((string) $request->get('last_name', ''));
        $email  = trim((string) $request->get('email', ''));
        $pass   = (string) $request->get('password', '');

        if ($first === '') $errors['first_name'] = 'Required';
        if ($last  === '') $errors['last_name']  = 'Required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
        if (strlen($pass) < 8) $errors['password'] = 'Minimum 8 characters';

        return ['errors' => $errors, 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => $pass,
                'phone' => $request->get('phone'), 'role' => $request->get('role'), 'status' => $request->get('status')];
    }
}
