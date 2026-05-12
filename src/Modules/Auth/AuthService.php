<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;
use App\Modules\Role\RoleRepository;
use App\Modules\Router\Response;
use App\Modules\User\UserRepository;


class AuthService
{
    private UserRepository      $users;
    private UserTokenRepository $tokens;
    private RoleRepository      $roles;
    private Auth                $auth;

    /**
     * AuthService constructor.
     *
     * @param Database $db
     * @param string $franchiseCode
     * @param Auth $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->users  = new UserRepository($db, $franchiseCode);
        $this->tokens = new UserTokenRepository($db);
        $this->roles  = new RoleRepository($db, $franchiseCode);
        $this->auth   = $auth;
    }

    /**
     * Prihlasí uzivatele.
     *
     * @param  string $email
     * @param  string $password
     * @return array{
     *   token: string,
     *   expires_at: string,
     *   id: int,
     *   email: string,
     *   role: string,
     *   first_name: string,
     *   last_name: string
     * }
     */
    public function login(string $email, string $password): array
    {
        VALIDATOR(['email' => $email])->email('email')->validate();

        $user = $this->users->findForLogin($email);

        if (!$user || !password_verify($password, $user['password'])) {
            Response::error('Invalid credentials', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error('Account is not active', 403);
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->tokens->create($user['id'], $token, $expiresAt);
        $this->users->touchLastLogin($user['id']);

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

    /**
     * Odhlasi prihlaseneho uzivatele (zrusi token).
     *
     * @return void
     */
    public function logout(): void
    {
        $this->auth->require();
        $this->auth->logout();
    }

    /**
     * Vrati data aktualne prihlaseneho uzivatele. Vyzaduje prihlaseni.
     *
     * @return array{
     *   id: int,
     *   email: string,
     *   role: string,
     *   name: string
     * }
     */
    public function me(): array
    {
        $this->auth->require();
        return $this->auth->user();
    }

    /**
     * Registrace nového uživatele.
     *
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $password
     * @return int
     */
    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
    ): int {
        VALIDATOR([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => $password,
        ])
            ->required(['first_name', 'last_name', 'email', 'password'])
            ->email('email')
            ->minLength('password', 8)
            ->validate();

        if ($this->users->emailExists($email)) {
            Response::error('Email already registered', 409);
        }

        $roleId = $this->roles->findIdByName('user');
        if (!$roleId) {
            Response::error('Default role not configured', 500);
        }

        return $this->users->create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id'    => $roleId,
            'status'     => 'active',
        ])['id'];
    }

    /**
     * Zmena hesla aktualne prihlaseneho uzivatele.
     *
     * @param  string $currentPassword  Aktualni heslo pro overeni
     * @param  string $newPassword      Nove heslo (min. 8 znaku)
     * @return void
     */
    public function changePassword(string $currentPassword, string $newPassword): void
    {
        $this->auth->require();

        VALIDATOR([
            'current_password' => $currentPassword,
            'new_password'     => $newPassword,
        ])
            ->required(['current_password', 'new_password'])
            ->minLength('new_password', 8)
            ->validate();

        $userId = $this->auth->id();
        $hash   = $this->users->findPasswordHash($userId);

        if (!$hash || !password_verify($currentPassword, $hash)) {
            Response::error('Current password is incorrect', 401);
        }

        $this->users->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
    }

    /**
     * OAuth login – najde uzivatele dle emailu, nebo ho vytvori (bez hesla).
     * Vyzadovano pri prihlaseni pres externi poskytovatele (Google, LinkedIn, apod.).
     *
     * @param  string $email
     * @param  string $firstName
     * @param  string $lastName
     * @return array{
     *   token: string,
     *   expires_at: string,
     *   id: int,
     *   email: string,
     *   role: string,
     *   first_name: string,
     *   last_name: string
     * }
     */
    public function oauthLogin(
        string $email,
        string $firstName,
        string $lastName
    ): array {
        VALIDATOR(['email' => $email])->email('email')->validate();

        $user = $this->users->findForLogin($email);

        if (!$user) {
            $roleId = $this->roles->findIdByName('user');
            if (!$roleId) {
                Response::error('Default role not configured', 500);
            }

            $this->users->create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'password'   => password_hash(
                    bin2hex(random_bytes(16)),
                    PASSWORD_BCRYPT,
                    ['cost' => 12]
                ),
                'role_id'    => $roleId,
                'status'     => 'active',
            ]);

            $user = $this->users->findForLogin($email);
            if (!$user) {
                Response::error('Failed to create user', 500);
            }
        }

        if ($user['status'] !== 'active') {
            Response::error('Account is not active', 403);
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->tokens->create($user['id'], $token, $expiresAt);
        $this->users->touchLastLogin($user['id']);

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
}
