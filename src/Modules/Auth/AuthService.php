<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;
use App\Core\Franchise;
use App\Modules\Router\Response;

class AuthService
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db   = Database::getInstance();
        $this->code = Franchise::code();
    }

    public function login(string $email, string $password): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'Invalid email']);
        }

        $user = $this->db->fetchOne(
            'SELECT u.id, u.email, u.password, r.name AS role, u.first_name, u.last_name, u.status
             FROM user u
             JOIN role r ON r.id = u.role_id
             WHERE u.email = ? AND u.franchise_code = ?
             LIMIT 1',
            [$email, $this->code]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            Response::error('Invalid credentials', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error('Account is not active', 403);
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->db->insert('user_token', [
            'user_id'    => $user['id'],
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->update('user', ['last_login_at' => date('Y-m-d H:i:s')],
            'id = ?', [$user['id']]);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
            'id'         => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
        ];
    }

    public function logout(): void
    {
        Auth::require();
        Auth::logout();
    }

    public function me(): array
    {
        Auth::require();
        return Auth::user();
    }

    public function register(string $firstName, string $lastName, string $email, string $password): int
    {
        $errors = [];
        if ($firstName === '') $errors['first_name'] = 'Required';
        if ($lastName  === '') $errors['last_name']  = 'Required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
        if (strlen($password) < 8) $errors['password'] = 'Minimum 8 characters';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM user WHERE franchise_code = ? AND email = ?',
            [$this->code, $email]
        );
        if ($exists) {
            Response::error('Email already registered', 409);
        }

        $roleRow = $this->db->fetchOne(
            'SELECT id FROM role WHERE franchise_code = ? AND name = ?',
            [$this->code, 'user']
        );
        if (!$roleRow) {
            Response::error('Default role not configured', 500);
        }

        return $this->db->insert('user', [
            'franchise_code' => $this->code,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'password'       => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id'        => $roleRow['id'],
            'status'         => 'active',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function changePassword(string $currentPassword, string $newPassword): void
    {
        Auth::require();

        if ($currentPassword === '' || $newPassword === '') {
            Response::validationError(['message' => 'Both current_password and new_password are required']);
        }
        if (strlen($newPassword) < 8) {
            Response::validationError(['new_password' => 'Minimum 8 characters']);
        }

        $userId = Auth::id();
        $user   = $this->db->fetchOne(
            'SELECT password FROM user WHERE id = ? AND franchise_code = ?',
            [$userId, $this->code]
        );

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            Response::error('Current password is incorrect', 401);
        }

        $this->db->update('user', [
            'password'   => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$userId, $this->code]);
    }
}
