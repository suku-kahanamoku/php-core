<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;

class Auth
{
    private const TOKEN_BYTES    = 32;
    private const TOKEN_LIFETIME = 86400; // 24 hours default

    private UserTokenRepository $userToken;
    private ?array    $currentUser = null;

    public function __construct(Database $db)
    {
        $this->userToken = new UserTokenRepository($db);
    }

    /** Create a Bearer token for the given user, persist it, and return it. */
    public function login(array $user): string
    {
        $token     = bin2hex(random_bytes(self::TOKEN_BYTES));
        $lifetime  = (int) ($_ENV['TOKEN_LIFETIME'] ?? self::TOKEN_LIFETIME);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        $this->userToken->create($user['id'], $token, $expiresAt);

        return $token;
    }

    /** Revoke the Bearer token sent with the current request. */
    public function logout(): void
    {
        $token = $this->extractToken();
        if ($token !== null) {
            $this->userToken->delete($token);
        }
        $this->currentUser = null;
    }

    /** Return true when a valid, non-expired Bearer token is present. */
    public function check(): bool
    {
        if ($this->currentUser !== null) {
            return true;
        }

        $token = $this->extractToken();
        if ($token === null) {
            return false;
        }

        $row = $this->userToken->findUserByToken($token, Request::resolveCode());

        if (!$row) {
            return false;
        }

        $this->currentUser = [
            'id'    => (int) $row['id'],
            'email' => $row['email'],
            'role'  => $row['role'],
            'name'  => $row['first_name'] . ' ' . $row['last_name'],
        ];

        return true;
    }

    public function user(): ?array
    {
        return $this->check() ? $this->currentUser : null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function hasRole(string $role): bool
    {
        return $this->role() === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    public function require(): void
    {
        if (!$this->check()) {
            Response::unauthorized('You must be logged in to access this resource.');
        }
    }

    public function requireRole(string $role): void
    {
        $this->require();
        if (!$this->hasRole($role)) {
            Response::forbidden('Insufficient permissions.');
        }
    }

    private function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            return $m[1];
        }

        return null;
    }
}
