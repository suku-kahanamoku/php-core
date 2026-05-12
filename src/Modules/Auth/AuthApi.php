<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class AuthApi
{
    private AuthService $service;

    /**
     * AuthApi constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->service = new AuthService($db, $franchiseCode, $auth);
    }

    /**
     * POST /auth/login — Prihlaseni uzivatele. Verejne dostupne.
     *
     * @param Request $request  body: email (required), password (required)
     * @return void
     */
    public function login(Request $request): void
    {
        $email    = trim((string) $request->get('email', ''));
        $password = (string) $request->get('password', '');

        if ($email === '' || $password === '') {
            Response::validationError(['message' => 'Email and password are required']);
        }

        $result = $this->service->login($email, $password);
        Response::success($result, 'Login successful');
    }

    /**
     * POST /auth/logout — Odhlaseni (zrusi Bearer token). Vyzaduje prihlaseni.
     *
     * @param Request $request
     * @return void
     */
    public function logout(Request $request): void
    {
        $this->service->logout();
        Response::success(null, 'Logged out');
    }

    /**
     * GET /auth/me — Vrati data aktualne prihlaseneho uzivatele. Vyzaduje prihlaseni.
     *
     * @param Request $request
     * @return void
     */
    public function me(Request $request): void
    {
        Response::success($this->service->me());
    }

    /**
     * POST /auth/register — Registrace noveho uzivatele. Verejne dostupne.
     *
     * @param Request $request  body: first_name, last_name, email, password (vse required)
     * @return void
     */
    public function register(Request $request): void
    {
        $id = $this->service->register(
            trim((string) $request->get('first_name', '')),
            trim((string) $request->get('last_name', '')),
            trim((string) $request->get('email', '')),
            (string) $request->get('password', ''),
        );
        // confirm_password a terms nejsou ukladany na backend – ignorujeme je

        Response::created(['id' => $id], 'Registration successful');
    }

    /**
     * POST /auth/change-password — Zmena vlastniho hesla. Vyzaduje prihlaseni.
     *
     * @param Request $request  body: current_password (required), new_password (required, min 8 znaku)
     * @return void
     */
    public function changePassword(Request $request): void
    {
        $this->service->changePassword(
            (string) $request->get('current_password', ''),
            (string) $request->get('new_password', ''),
        );

        Response::success(null, 'Password changed successfully');
    }

    /**
     * POST /auth/reset-password — Resetuje heslo uzivatele dle emailu. Verejne dostupne.
     *
     * @param Request $request  body: email (required)
     * @return void
     */
    public function resetPassword(Request $request): void
    {
        $email = trim((string) $request->get('email', ''));

        if ($email === '') {
            Response::validationError(['email' => 'Email is required']);
        }

        $result = $this->service->resetPassword($email);
        Response::success($result, 'Password reset successful');
    }

    /**
     * POST /auth/oauth — OAuth login: najde nebo vytvori uzivatele dle emailu. Verejne dostupne.
     *
     * @param Request $request  body: email (required), first_name, last_name
     * @return void
     */
    public function oauth(Request $request): void
    {
        $email     = trim((string) $request->get('email', ''));
        $firstName = trim((string) $request->get('first_name', ''));
        $lastName  = trim((string) $request->get('last_name', ''));

        if ($email === '') {
            Response::validationError(['email' => 'Email is required']);
        }

        $result = $this->service->oauthLogin($email, $firstName, $lastName);
        Response::success($result, 'OAuth login successful');
    }

    /**
     * Zaregistruje vsechny routy tohoto modulu do routeru.
     *
     * @param  Router $router
     * @return void
     */
    public function registerRoutes(Router $router): void
    {
        $router->post('/login', [$this, 'login']);
        $router->post('/logout', [$this, 'logout']);
        $router->get('/me', [$this, 'me']);
        $router->post('/register', [$this, 'register']);
        $router->post('/change-password', [$this, 'changePassword']);
        $router->post('/reset-password', [$this, 'resetPassword']);
        $router->post('/oauth', [$this, 'oauth']);
    }
}
