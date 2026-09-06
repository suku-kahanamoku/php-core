<?php

declare(strict_types=1);

namespace App\Utils;

final class QueryPolicy
{
    /** @param string[] $allowed @param array<string, mixed> $forced */
    public static function filter(string $raw, array $allowed, array $forced = []): string
    {
        $decoded = json_decode($raw, true);
        $safe = [];
        if (is_array($decoded)) {
            foreach ($decoded as $column => $value) {
                if (in_array((string) $column, $allowed, true)) {
                    $safe[(string) $column] = $value;
                }
            }
        }
        foreach ($forced as $column => $value) {
            $safe[$column] = $value;
        }
        return $safe === [] ? '' : (string) json_encode($safe);
    }

    /** @param string[] $allowed */
    public static function sort(string $raw, array $allowed, string $default = ''): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $default;
        }
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            $safe = [];
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    foreach ($item as $column => $direction) {
                        if (in_array((string) $column, $allowed, true)) {
                            $safe[] = [(string) $column => (int) $direction === 1 ? 1 : -1];
                        }
                    }
                }
            }
            return $safe === [] ? $default : (string) json_encode($safe);
        }
        $parts = preg_split('/\s+/', $raw, 2) ?: [];
        $column = (string) ($parts[0] ?? '');
        if (!in_array($column, $allowed, true)) {
            return $default;
        }
        $direction = strtoupper((string) ($parts[1] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        return $column . ' ' . $direction;
    }

    /** @param string[]|null $requested @param string[] $allowed @return string[] */
    public static function projection(?array $requested, array $allowed): array
    {
        return $requested === null
            ? $allowed
            : array_values(array_intersect($requested, $allowed));
    }

    /** @param array<string, mixed> $item @param string[] $allowed @return array<string, mixed> */
    public static function fields(array $item, array $allowed): array
    {
        return array_intersect_key($item, array_flip($allowed));
    }

    /** @param array<string, mixed> $result @param string[] $allowed @return array<string, mixed> */
    public static function listFields(array $result, array $allowed): array
    {
        $result['data'] = array_map(
            static fn(array $item): array => self::fields($item, $allowed),
            $result['data'] ?? [],
        );
        return $result;
    }
}
