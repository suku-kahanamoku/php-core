<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Auth\Auth;
use App\Modules\Router\Request;

class AuthMiddleware
{
    public function __invoke(Request $request): void
    {
        Auth::require();
    }
}
