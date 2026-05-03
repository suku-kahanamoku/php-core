<?php

declare(strict_types=1);

/**
 * Parses the `sort` query parameter into a SQL ORDER BY fragment.
 *
 * Accepted formats:
 *   JSON array:  [{"col":1},{"other":-1}]   1 = ASC, -1 = DESC
 *   Legacy:      col ASC | col DESC           (backwards-compatible)
 *
 * Column names are validated with /^[a-zA-Z_][a-zA-Z0-9_]*$/ to prevent SQL injection.
 *
 * @param string $sort    Raw query parameter value.
 * @param string $default Default ORDER BY expression used when sort is empty/invalid.
 *                        Must already contain the table prefix if needed (e.g. "u.created_at DESC").
 * @param string $prefix  Optional table alias prepended to every column (e.g. "u").
 * @return string         Ready-to-embed SQL ORDER BY clause (without the ORDER BY keyword).
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
