<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;

class Auth
{
    private const _TOKEN_BYTES    = 32;
    private const TOKEN_LIFETIME = 86400; // vychozi doba platnosti: 24 hodin

    private UserTokenRepository $_userToken;
    private ?array    $_currentUser = null;

    /**
     * Konstruktor tridy Auth.
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->_userToken = new UserTokenRepository($db);
    }

    /**
     * Vytvori Bearer token pro uzivatele, ulozi ho do DB a vrati jeho hodnotu.
     *
     * @param  array{
     *  id: int, 
     *  email: string, 
     *  role: string, 
     *  first_name: string, 
     *  last_name: string} $user
     * @return string  Vygenerovany token
     */
    public function login(array $user): string
    {
        $token     = bin2hex(random_bytes(self::_TOKEN_BYTES));
        $lifetime  = (int) ($_ENV['TOKEN_LIFETIME'] ?? self::TOKEN_LIFETIME);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

        $this->_userToken->create($user['id'], $token, $expiresAt);

        return $token;
    }

    /**
     * Zrusi Bearer token aktualni pozadavku (odhlaseni).
     *
     * @return void
     */
    public function logout(): void
    {
        $token = $this->_extractToken();
        if ($token !== null) {
            $this->_userToken->delete($token);
        }
        $this->_currentUser = null;
    }

    /**
     * Vrati true, pokud je v pozadavku platny, nevyprsely Bearer token.
     *
     * @return bool
     */
    public function check(): bool
    {
        if ($this->_currentUser !== null) {
            return true;
        }

        $token = $this->_extractToken();
        if ($token === null) {
            return false;
        }

        $row = $this->_userToken->findUserByToken($token, Request::resolveCode());

        if (!$row) {
            return false;
        }

        $this->_currentUser = [
            'id'    => (int) $row['id'],
            'email' => $row['email'],
            'role'  => $row['role'],
            'name'  => $row['first_name'] . ' ' . $row['last_name'],
        ];

        return true;
    }

    /**
     * Vrati data prihlaseneho uzivatele nebo null kdyz neni prihlasen.
     *
     * @return array{
     *   id: int,
     *   email: string,
     *   role: string,
     *   name: string
     * }|null
     */
    public function user(): ?array
    {
        return $this->check() ? $this->_currentUser : null;
    }

    /**
     * Vrati ID prihlaseneho uzivatele nebo null.
     *
     * @return int|null
     */
    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    /**
     * Vrati nazev role prihlaseneho uzivatele nebo null.
     *
     * @return string|null
     */
    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    /**
     * Vrati true, pokud ma prihlaseny uzivatel danou roli.
     *
     * @param  string $role  Ocekavany nazev role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role() === $role;
    }

    /**
     * Vrati true, pokud ma prihlaseny uzivatel alespon jednu z uvedenych roli.
     *
     * @param  string[] $roles
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    /**
     * Vyzaduje prihlaseneho uzivatele; jinak ukonci pozadavek s 401.
     *
     * @return void
     */
    public function require(): void
    {
        if (!$this->check()) {
            Response::unauthorized('You must be logged in to access this resource.');
        }
    }

    /**
     * Vyzaduje prihlaseneho uzivatele s danou roli; jinak ukonci pozadavek s 401/403.
     *
     * @param  string $role  Pozadovany nazev role
     * @return void
     */
    public function requireRole(string $role): void
    {
        $this->require();
        if (!$this->hasRole($role)) {
            Response::forbidden('Insufficient permissions.');
        }
    }

    private function _extractToken(): ?string
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
