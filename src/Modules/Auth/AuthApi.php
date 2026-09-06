<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;
use App\Utils\InternalAuth;
use App\Utils\RateLimiter;

class AuthApi
{
    private AuthService $_service;
    private RateLimiter $_rateLimiter;

    /**
     * Konstruktor tridy AuthApi.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_service = new AuthService($db, $franchiseCode, $auth);
        $this->_rateLimiter = new RateLimiter($db, $franchiseCode);
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
        $this->_rateLimiter->hit('login', $this->_subject($email), 10, 900);

        VALIDATOR(['email' => $email, 'password' => $password])
            ->required(['email', 'password'])
            ->validate();

        $result = $this->_service->login($email, $password);
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
        $this->_service->logout();
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
        Response::success($this->_service->me());
    }

    /**
     * POST /auth/register — Registrace noveho uzivatele. Verejne dostupne.
     *
     * @param Request $request  body: first_name, last_name, email, password (vse required)
     * @return void
     */
    public function register(Request $request): void
    {
        $this->_rateLimiter->hit(
            'register',
            $this->_subject((string) $request->get('email', '')),
            5,
            3600,
        );
        $id = $this->_service->register(
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
        $this->_service->changePassword(
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

        VALIDATOR(['email' => $email])->required('email')->email('email')->validate();

        $this->_rateLimiter->hit('password-reset', $this->_subject($email), 5, 3600);

        $result = $this->_service->resetPassword($email);
        Response::success($result, 'Password reset successful');
    }

    public function completePasswordReset(Request $request): void
    {
        $this->_rateLimiter->hit('password-reset-complete', $this->_subject('complete'), 10, 3600);
        $this->_service->completePasswordReset(
            trim((string) $request->get('token', '')),
            (string) $request->get('new_password', ''),
        );
        Response::success(null, 'Password changed successfully');
    }

    /**
     * POST /auth/oauth — OAuth login: najde nebo vytvori uzivatele dle emailu. Verejne dostupne.
     *
     * @param Request $request  body: email (required), first_name, last_name
     * @return void
     */
    public function oauth(Request $request): void
    {
        InternalAuth::require($request);
        $provider  = strtolower(trim((string) $request->get('provider', '')));
        $subject   = trim((string) $request->get('subject', ''));
        $email     = trim((string) $request->get('email', ''));
        $firstName = trim((string) $request->get('first_name', ''));
        $lastName  = trim((string) $request->get('last_name', ''));

        VALIDATOR(['email' => $email])->required('email')->email('email')->validate();

        $result = $this->_service->oauthLogin($provider, $subject, $email, $firstName, $lastName);
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
        $router->post('/complete-reset', [$this, 'completePasswordReset']);
        $router->post('/oauth', [$this, 'oauth']);
    }

    private function _subject(string $value): string
    {
        return strtolower(trim($value)) . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
