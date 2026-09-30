<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Allowlisty pro standardní JSON/MongoDB-kompatibilní dotazový kontrakt.
 *
 * Třída je čistá (bez stavu) a volá se staticky z repozitářů a služeb. Zajišťuje,
 * že klient může filtrovat, třídit a projektovat pouze přes výslovně povolené
 * sloupce, takže nelze obejít tenant hranici nebo filtrovat nad cizími sloupci.
 */
final class QueryPolicy
{
    /**
     * Normalizuje filtrační JSON objekt proti allowlistu a doplní vynucené podmínky.
     *
     * @param  string[]              $allowed Povolené názvy sloupců.
     * @param  array<string, mixed>  $forced  Podmínky, které se vždy přidají (např. tenant, `deleted = 0`).
     * @return string                          JSON objekt, nebo prázdný řetězec, pokud zůstalo prázdno.
     */
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

    /**
     * Normalizuje řazení proti allowlistu; přijímá buď `sloupec ASC|DESC`, nebo JSON pole.
     *
     * @param  string[] $allowed Povolené názvy sloupců pro řazení.
     * @param  string   $default Výchozí řazení, pokud je vstup prázdný nebo neplatný.
     * @return string            Bezpečné SQL řazení, nebo `$default`.
     */
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

    /**
     * Omezí požadovanou projekci na povolené sloupce.
     *
     * @param  string[]|null $requested Požadované sloupce, nebo null pro "vše povolené".
     * @param  string[]      $allowed   Allowlist sloupců.
     * @return string[]                 Průsečík požadavku a allowlistu.
     */
    public static function projection(?array $requested, array $allowed): array
    {
        return $requested === null
            ? $allowed
            : array_values(array_intersect($requested, $allowed));
    }

    /**
     * Omezí jeden záznam na allowlist polí.
     *
     * @param  array<string, mixed> $item    Záznam k omezení.
     * @param  string[]             $allowed Allowlist názvů polí.
     * @return array<string, mixed>         Pole obsahující pouze povolené klíče.
     */
    public static function fields(array $item, array $allowed): array
    {
        return array_intersect_key($item, array_flip($allowed));
    }

    /**
     * Omezí všechny záznamy stránkovací odpovědi na allowlist polí, metadata ponechá.
     *
     * @param  array<string, mixed> $result  Stránkovací odpověď s polem `data`.
     * @param  string[]             $allowed Allowlist názvů polí.
     * @return array<string, mixed>         Odpověď, jejíž řádky obsahují pouze povolené klíče.
     */
    public static function listFields(array $result, array $allowed): array
    {
        $result['data'] = array_map(
            static fn(array $item): array => self::fields($item, $allowed),
            $result['data'] ?? [],
        );
        return $result;
    }
}
