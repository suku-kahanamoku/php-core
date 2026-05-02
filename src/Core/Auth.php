<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private const TOKEN_BYTES    = 32;    // → 64-char hex token
    private const TOKEN_LIFETIME = 86400; // 24 hours default

    /** Resolved user for the current request (cached after first check). */
    private static ?array $currentUser = null;

    /**
     * Create a Bearer token for the given user, persist it, and return it.
     * Returns the raw token string to be sent back to the client.
     */
    public static function login(array $user): string
    {
        $token     = bin2hex(random_bytes(self::TOKEN_BYTES));
        $lifetime  = (int) ($_ENV['TOKEN_LIFETIME'] ?? self::TOKEN_LIFETIME);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        Database::getInstance()->insert('user_token', [
            'user_id'    => $user['id'],
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /** Revoke the Bearer token sent with the current request. */
    public static function logout(): void
    {
        $token = self::extractToken();
        if ($token !== null) {
            Database::getInstance()->query(
                'DELETE FROM user_token WHERE token = ?',
                [$token]
            );
        }
        self::$currentUser = null;
    }

    /** Return true when a valid, non-expired Bearer token is present. */
    public static function check(): bool
    {
        if (self::$currentUser !== null) {
            return true;
        }

        $token = self::extractToken();
        if ($token === null) {
            return false;
        }

        $row = Database::getInstance()->fetchOne(
            'SELECT u.id, u.email, u.role, u.first_name, u.last_name
             FROM user_token t
             JOIN `user` u ON u.id = t.user_id
             WHERE t.token = ? AND t.expires_at > NOW() AND u.status = "active"
             LIMIT 1',
            [$token]
        );

        if (!$row) {
            return false;
        }

        self::$currentUser = [
            'id'    => (int) $row['id'],
            'email' => $row['email'],
            'role'  => $row['role'],
            'name'  => $row['first_name'] . ' ' . $row['last_name'],
        ];

        return true;
    }

    public static function user(): ?array
    {
        return self::check() ? self::$currentUser : null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function hasRole(string $role): bool
    {
        return self::role() === $role;
    }

    public static function hasAnyRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function require(): void
    {
        if (!self::check()) {
            Response::unauthorized('You must be logged in to access this resource.');
        }
    }

    public static function requireRole(string $role): void
    {
        self::require();
        if (!self::hasRole($role)) {
            Response::forbidden('Insufficient permissions.');
        }
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /** Extract the raw token string from the Authorization: Bearer header. */
    private static function extractToken(): ?string
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
