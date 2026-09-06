<?php

declare(strict_types=1);

namespace App\Utils;

use App\Modules\Router\Request;
use App\Modules\Router\Response;

final class InternalAuth
{
    public static function check(Request $request): bool
    {
        $configured = trim((string) ($_ENV['INTERNAL_API_KEY'] ?? ''));
        $provided   = trim((string) $request->header('X-Internal-Key', ''));

        return $configured !== ''
            && $provided !== ''
            && hash_equals($configured, $provided);
    }

    public static function require(Request $request): void
    {
        if (!self::check($request)) {
            Response::unauthorized('Internal authentication required.');
        }
    }
}
