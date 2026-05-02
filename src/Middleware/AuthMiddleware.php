<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Auth\Auth;
use App\Modules\Router\Request;

class AuthMiddleware
{
    private Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function __invoke(Request $request): void
    {
        $this->auth->require();
    }
}
