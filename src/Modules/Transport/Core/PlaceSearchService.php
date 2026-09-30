<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

/** Literal, accent-insensitive matching and deterministic ranking across online sources. */
final class PlaceSearchService
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, ['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z','ä'=>'a','ö'=>'o','ü'=>'u','ľ'=>'l','ĺ'=>'l','ŕ'=>'r','ô'=>'o']);
        return trim(preg_replace('/[\p{M}]+/u', '', preg_replace('/\s+/u', ' ', $value)));
    }

    /** @param list<array<string,mixed>> $places @return list<array<string,mixed>> */
    public static function rank(array $places, string $query, int $limit): array
    {
        $query = self::normalize($query);
        $ranked = [];
        foreach ($places as $place) {
            $name = self::normalize((string)($place['name'] ?? ''));
            if ($query === '' || !str_contains(str_replace(',', '', $name), str_replace(',', '', $query))) {
                continue;
            }
            $parts = explode(',', $name);
            $local = trim(end($parts));
            $score = $name === $query || $local === $query ? 0
                : (count($parts) > 1 && str_starts_with($local, $query) ? 1
                : (str_starts_with($name, $query) ? 2 : 3));
            $ranked[] = [$score, $name, (string)$place['id'], $place];
        }
        usort($ranked, static fn ($a, $b) => [$a[0],$a[1],$a[2]] <=> [$b[0],$b[1],$b[2]]);
        return array_map(static fn ($row) => $row[3], array_slice($ranked, 0, $limit));
    }
}
