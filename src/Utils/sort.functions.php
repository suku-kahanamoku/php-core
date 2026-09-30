<?php

declare(strict_types=1);

/**
 * Parsuje query parametr `sort` na SQL fragment `ORDER BY`.
 *
 * Akceptované formáty:
 *   JSON pole:  [{"col":1},{"other":-1}]   1 = ASC, -1 = DESC
 *   Starší:     col ASC | col DESC         (zpětná kompatibilita)
 *
 * Názvy sloupců se ověřují vzorem /^[a-zA-Z_][a-zA-Z0-9_]*$/, aby šlo zabránit
 * SQL injekci přes parametr `sort`.
 *
 * @param string $sort    Surová hodnota query parametru.
 * @param string $default Výchozí výraz `ORDER BY` pro prázdný/neplatný vstup.
 *                        Musí již obsahovat alias tabulky, pokud je potřeba (např. "u.created_at DESC").
 * @param string $prefix  Volitelný alias tabulky předřazený každému sloupci (např. "u").
 * @return string         Připravený výraz pro vložení do SQL (bez klíčového slova ORDER BY).
 */
function SQL_SORT(string $sort, string $default, string $prefix = ''): string
{
    $sort = trim($sort);

    if ($sort === '') {
        return $default;
    }

    // ── JSON array format ────────────────────────────────────
    if (str_starts_with($sort, '[')) {
        $decoded = json_decode($sort, true);

        if (is_array($decoded) && !empty($decoded)) {
            $parts = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                foreach ($item as $col => $dir) {
                    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $col)) {
                        continue; // reject unsafe column names
                    }
                    $sqlDir  = (int) $dir === 1 ? 'ASC' : 'DESC';
                    $parts[] = ($prefix !== '' ? $prefix . '.' : '') . $col . ' ' . $sqlDir;
                }
            }
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }
    }

    // ── Legacy single-column format: "col" or "col ASC/DESC" ─
    $parts  = preg_split('/\s+/', $sort, 2);
    $col    = $parts[0] ?? '';
    $dir    = strtoupper($parts[1] ?? 'ASC');
    $sqlDir = $dir === 'DESC' ? 'DESC' : 'ASC';

    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
        return ($prefix !== '' ? $prefix . '.' : '') . $col . ' ' . $sqlDir;
    }

    return $default;
}
