<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

class AuthMiddleware
{
    public function __invoke(Request $request): void
    {
        Auth::require();
    }
}
