<?php

declare(strict_types=1);

namespace App\Utils;

final class RequestContext
{
    private static ?string $requestId = null;

    public static function begin(): string
    {
        self::$requestId = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            header('X-Request-ID: ' . self::$requestId);
        }

        return self::$requestId;
    }

    public static function id(): ?string
    {
        return self::$requestId;
    }
}
