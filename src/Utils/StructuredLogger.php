<?php

declare(strict_types=1);

namespace App\Utils;

final class StructuredLogger
{
    /** @param array<string, mixed> $context */
    public static function error(string $event, array $context = []): void
    {
        $payload = [
            'level'      => 'error',
            'event'      => $event,
            'request_id' => RequestContext::id(),
            'timestamp'  => gmdate('c'),
        ] + self::redact($context);

        error_log((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/(?:authorization|cookie|password|secret|token|api[_-]?key)/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }
}
