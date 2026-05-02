<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Franchise – provides the current franchise identifier for multi-tenant isolation.
 *
 * Currently resolved from the FRANCHISE_CODE environment variable.
 * Future: may be resolved from the request URL, subdomain, or custom header.
 */
class Franchise
{
    private static ?string $code = null;

    /**
     * Return the current franchise code. Never empty — falls back to 'default'.
     */
    public static function code(): string
    {
        if (self::$code === null) {
            $raw        = $_ENV['FRANCHISE_CODE'] ?? 'default';
            self::$code = trim($raw) !== '' ? trim($raw) : 'default';
        }
        return self::$code;
    }

    /**
     * Reset cached value — useful in tests when FRANCHISE_CODE is changed.
     */
    public static function reset(): void
    {
        self::$code = null;
    }
}
