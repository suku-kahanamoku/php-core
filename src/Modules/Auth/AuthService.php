<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Core\Franchise;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

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
        Validator::make(['email' => $email])->email('email')->validate();

        $user = $this->db->fetchOne(
            'SELECT u.id, u.email, u.password,
                    r.name AS role, u.first_name, u.last_name, u.status
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

        $this->db->update(
            'user',
            ['last_login_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );

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

    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
    ): int {
        Validator::make([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => $password,
        ])
            ->required(['first_name', 'last_name', 'email', 'password'])
            ->email('email')
            ->minLength('password', 8)
            ->validate();

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

        Validator::make([
            'current_password' => $currentPassword,
            'new_password'     => $newPassword,
        ])
            ->required(['current_password', 'new_password'])
            ->minLength('new_password', 8)
            ->validate();

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
