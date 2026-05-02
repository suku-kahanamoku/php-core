<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Router\Request;
use App\Modules\Router\Response;

class AuthApi
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    /** POST /auth/login */
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

    /** POST /auth/logout */
    public function logout(Request $request): void
    {
        $this->service->logout();
        Response::success(null, 'Logged out');
    }

    /** GET /auth/me */
    public function me(Request $request): void
    {
        Response::success($this->service->me());
    }

    /** POST /auth/register */
    public function register(Request $request): void
    {
        $id = $this->service->register(
            trim((string) $request->get('first_name', '')),
            trim((string) $request->get('last_name',  '')),
            trim((string) $request->get('email',      '')),
            (string) $request->get('password', '')
        );

        Response::created(['id' => $id], 'Registration successful');
    }

    /** POST /auth/change-password */
    public function changePassword(Request $request): void
    {
        $this->service->changePassword(
            (string) $request->get('current_password', ''),
            (string) $request->get('new_password', '')
        );

        Response::success(null, 'Password changed successfully');
    }
}
