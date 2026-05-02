<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class AuthController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * POST /auth/login
     */
    public function login(Request $request): void
    {
        $email    = trim((string) $request->get('email', ''));
        $password = (string) $request->get('password', '');

        if ($email === '' || $password === '') {
            Response::validationError(['email' => 'Required', 'password' => 'Required']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'Invalid email format']);
        }

        $user = $this->db->fetchOne(
            'SELECT id, email, password, role, first_name, last_name, status FROM user WHERE email = ? LIMIT 1',
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            Response::error('Invalid credentials', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error('Account is not active', 403);
        }

        // Update last login
        $this->db->update('user', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        $token = Auth::login([
            'id'         => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ]);

        Response::success([
            'token' => $token,
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'name'  => $user['first_name'] . ' ' . $user['last_name'],
        ], 'Login successful');
    }

    /**
     * POST /auth/logout
     */
    public function logout(Request $request): void
    {
        Auth::logout();
        Response::success(null, 'Logged out successfully');
    }

    /**
     * GET /auth/me
     */
    public function me(Request $request): void
    {
        Auth::require();
        $user = Auth::user();
        Response::success($user);
    }

    /**
     * POST /auth/register
     */
    public function register(Request $request): void
    {
        $data = [
            'first_name' => trim((string) $request->get('first_name', '')),
            'last_name'  => trim((string) $request->get('last_name', '')),
            'email'      => trim((string) $request->get('email', '')),
            'password'   => (string) $request->get('password', ''),
        ];

        $errors = [];
        if ($data['first_name'] === '') $errors['first_name'] = 'Required';
        if ($data['last_name'] === '')  $errors['last_name']  = 'Required';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
        if (strlen($data['password']) < 8) $errors['password'] = 'Minimum 8 characters';

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
            'password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'role'     => 'user',
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Registration successful');
    }

    /**
     * POST /auth/change-password
     */
    public function changePassword(Request $request): void
    {
        Auth::require();

        $currentPassword = (string) $request->get('current_password', '');
        $newPassword     = (string) $request->get('new_password', '');

        if ($currentPassword === '' || $newPassword === '') {
            Response::validationError(['message' => 'Both current_password and new_password are required']);
        }

        if (strlen($newPassword) < 8) {
            Response::validationError(['new_password' => 'Minimum 8 characters']);
        }

        $userId = Auth::id();
        $user   = $this->db->fetchOne('SELECT password FROM user WHERE id = ?', [$userId]);

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            Response::error('Current password is incorrect', 401);
        }

        $this->db->update('user', [
            'password'   => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$userId]);

        Response::success(null, 'Password changed successfully');
    }
}
