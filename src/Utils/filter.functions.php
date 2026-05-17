<?php

declare(strict_types=1);

/**
 * Parsuje parametr filtru do SQL WHERE fragmentu + vazanych parametru.
 *
 * Prijimany format – JSON objekt kde kazdy klic je nazev sloupce:
 *   {"col": {"value": "foo"}}                             → col = ?
 *   {"col": {"value": "foo", "operator": "neq"}}          → col != ?
 *   {"price": {"value": 100, "operator": "gte"}}          → price >= ?
 *   {"price": {"value": [10,100], "operator": "range"}}   → price BETWEEN ? AND ?
 *   {"name":  {"value": "test", "operator": "regex"}}     → name LIKE '%test%'
 *   {"name":  {"value": "test", "operator": "start"}}     → name LIKE 'test%'
 *   {"name":  {"value": "test", "operator": "end"}}       → name LIKE '%test'
 *   {"ids":   {"value": [1,2,3], "operator": "in"}}       → id IN (?,?,?)
 *   {"col":   {"operator": "null"}}                       → col IS NULL
 *   {"col":   {"operator": "notnull"}}                    → col IS NOT NULL
 *
 * Podporovane operatory: eq, neq, lt, lte, gt, gte, range, regex, start, end, in, null, notnull
 *
 * Nazvy sloupcu jsou validovany kvuli prevenci SQL injection. Jednoduche sloupce musi odpovidat
 * /^[a-zA-Z_][a-zA-Z0-9_]*$/. JSON podpole pouzivaji tecka-notaci: "data.year"
 * je prelozeno na JSON_UNQUOTE(JSON_EXTRACT(alias.data, '$.year')).
 *
 * @param string   $filter    Hodnota parametru (JSON retezec).
 * @param string   $prefix    Volitelny alias tabulky (napr. "u") predrazeny jako "u.col".
 * @param string[] $jsonCols  Seznam nazvu sloupcu, u nichz tecka-notace znamena JSON podpole
 *                            (napr. ["data"]). Jina "tabulka.sloupec" notace je
 *                            povazovana za primo alias.sloupec odkaz (cizi tabulka).
 * @return array{sql: string, params: array<mixed>}
 *   sql    – SQL fragment AND-spojenych podminek (prazdny retezec kdyz nic nenalezeno).
 *   params – Pozicni hodnoty vazanych parametru.
 */
function SQL_FILTER(string $filter, string $prefix = '', array $jsonCols = ['data']): array
{
    $filter = trim($filter);

    if ($filter === '') {
        return ['sql' => '', 'params' => []];
    }

    $decoded = json_decode($filter, true);

    if (!is_array($decoded) || empty($decoded)) {
        return ['sql' => '', 'params' => []];
    }

    $conditions = [];
    $params     = [];

    foreach ($decoded as $col => $spec) {
        // Podpora zkracenych skalarov: {"col": "val"} → implicitni filtr eq.
        if (!is_array($spec)) {
            if ($spec === null || $spec === '') {
                continue;
            }
            $spec = ['value' => $spec];
        }
        $result = _sql_filter_condition(
            (string) $col,
            $spec,
            $prefix,
            $jsonCols
        );
        if ($result !== null) {
            $conditions[] = $result['sql'];
            array_push($params, ...$result['params']);
        }
    }

    if (empty($conditions)) {
        return ['sql' => '', 'params' => []];
    }

    return [
        'sql'    => implode(' AND ', $conditions),
        'params' => $params,
    ];
}

/**
 * Sestavi jednu WHERE podmiku pro par sloupec + spec.
 *
 * @internal
 * @param string   $col       Holy nazev sloupce (validace probiha zde).
 * @param array    $spec      {'value': mixed, 'operator': string}
 * @param string   $prefix    Alias tabulky pro hlavni tabulku.
 * @param string[] $jsonCols  Nazvy sloupcu povazovane za JSON (tecka = JSON_EXTRACT).
 *                            Vsechna ostatni tecka-notace je povazovana za tableAlias.sloupec.
 * @return array{sql: string, params: array<mixed>}|null  null pri neplatnem vstupu.
 */
function _sql_filter_condition(
    string $col,
    array $spec,
    string $prefix,
    array $jsonCols = ['data']
): ?array {
        // tecka-notace: "data.field" → JSON_EXTRACT  |  "category.syscode" → category.syscode
    if (str_contains($col, '.')) {
        [$left, $right] = explode('.', $col, 2);
        if (
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $left) ||
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $right)
        ) {
            return null;
        }
        if (in_array($left, $jsonCols, true)) {
            // JSON podpole: data.year → JSON_UNQUOTE(JSON_EXTRACT(p.data, '$.year'))
            $qualified = $prefix !== ''
                ? "JSON_UNQUOTE(JSON_EXTRACT({$prefix}.{$left}, '\$.{$right}'))"
                : "JSON_UNQUOTE(JSON_EXTRACT({$left}, '\$.{$right}'))";
        } else {
            // Sloupec cizi tabulky: category.syscode → category.syscode
            $qualified = "{$left}.{$right}";
        }
    } else {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
            return null;
        }
        $qualified = $prefix !== '' ? $prefix . '.' . $col : $col;
    }

    $rawOperator = $spec['operator'] ?? null;
    // Frontend posila operator jako {"value": "$regex"}; akceptujeme oba formaty.
    if (is_array($rawOperator)) {
        $rawOperator = $rawOperator['value'] ?? null;
    }

    // ── MongoDB-style spec: {"$regex":"val","$options":"i"} nebo {"$eq":"val"} ──
    $mongoKeys = array_filter(array_keys($spec), static fn($k) => str_starts_with((string) $k, '$'));
    if (!empty($mongoKeys)) {
        if (isset($spec['$regex'])) {
            return _sql_filter_operator($qualified, 'regex', $spec['$regex']);
        }
        if (isset($spec['$in'])) {
            return _sql_filter_operator($qualified, 'in', $spec['$in']);
        }
        $mongoMap = ['$eq' => 'eq', '$ne' => 'neq', '$lt' => 'lt', '$lte' => 'lte', '$gt' => 'gt', '$gte' => 'gte'];
        foreach ($mongoMap as $mongoOp => $ourOp) {
            if (array_key_exists($mongoOp, $spec)) {
                return _sql_filter_operator($qualified, $ourOp, $spec[$mongoOp]);
            }
        }
        return null;
    }

    // ── Our format: {"value": ..., "operator": "regex"|"eq"|...} ─────────────
    // Odeber uvodni znak $ pouzivany frontendovou konvenci (napr. "$regex" → "regex").
    $operator  = strtolower(trim(ltrim((string) ($rawOperator ?? 'eq'), '$')));
    $value     = $spec['value'] ?? null;

    return _sql_filter_operator($qualified, $operator, $value);
}

/**
 * Preklada validovany SQL operator + hodnotu na fragment + parametry.
 *
 * @internal
 * @param string $col      Plne kvalifikovany sloupec (napr. "u.created_at").
 * @param string $operator Jeden z podporovanych nazvu operatoru.
 * @param mixed  $value    Skalar, pole (pro range/in) nebo null (pro null/notnull).
 * @return array{sql: string, params: array<mixed>}|null  null pri neplatnem operatoru/hodnote.
 */
function _sql_filter_operator(string $col, string $operator, mixed $value): ?array
{
    // ── Jednoduche porovnavaci operatory ──────────────────────────────────────────
    $comparison = match ($operator) {
        'eq'       => '=',
        'ne', 'neq' => '!=',
        'lt'       => '<',
        'lte'      => '<=',
        'gt'       => '>',
        'gte'      => '>=',
        default    => null,
    };

    if ($comparison !== null) {
        if ($value === null) {
            return null;
        }
        return ['sql' => "{$col} {$comparison} ?", 'params' => [$value]];
    }

    // ── Vsechny ostatni operatory ──────────────────────────────────────────────────
    switch ($operator) {
        case 'range':
            if (!is_array($value) || count($value) !== 2) {
                return null;
            }
            [$min, $max] = array_values($value);
            if ($min === null || $max === null) {
                return null;
            }
            return ['sql' => "{$col} BETWEEN ? AND ?", 'params' => [$min, $max]];

        case 'regex':
            if ($value === null) {
                return null;
            }
            return ['sql' => "{$col} LIKE ?", 'params' => ['%' . $value . '%']];

        case 'start':
            if ($value === null) {
                return null;
            }
            return ['sql' => "{$col} LIKE ?", 'params' => [$value . '%']];

        case 'end':
            if ($value === null) {
                return null;
            }
            return ['sql' => "{$col} LIKE ?", 'params' => ['%' . $value]];

        case 'in':
            if (!is_array($value) || empty($value)) {
                return null;
            }
            $placeholders = implode(', ', array_fill(0, count($value), '?'));
            return [
                'sql' => "{$col} IN ({$placeholders})",
                'params' => array_values($value)
            ];

        case 'null':
            return ['sql' => "{$col} IS NULL", 'params' => []];

        case 'notnull':
            return ['sql' => "{$col} IS NOT NULL", 'params' => []];

        default:
            return null;
    }
}
