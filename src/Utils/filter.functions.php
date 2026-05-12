<?php

declare(strict_types=1);

/**
 * Parses the `filter` query parameter into a SQL WHERE fragment + bound params.
 *
 * Accepted format – JSON object where each key is a column name:
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
 * Supported operators: eq, neq, lt, lte, gt, gte, range, regex, start, end, in, null, notnull
 *
 * Column names are validated to prevent SQL injection. Simple columns must match
 * /^[a-zA-Z_][a-zA-Z0-9_]*$/. JSON sub-fields use dot-notation: "data.year"
 * is translated to JSON_UNQUOTE(JSON_EXTRACT(alias.data, '$.year')).
 *
 * @param string   $filter    Raw query parameter (JSON string).
 * @param string   $prefix    Optional table alias (e.g. "u") prepended as "u.col".
 * @param string[] $jsonCols  List of column names whose dot-notation means JSON sub-field
 *                            (e.g. ["data"]). Any other "table.column" dot-notation is
 *                            treated as a direct alias.column reference (related table).
 * @return array{sql: string, params: array<mixed>}
 *   sql    – Ready-to-embed AND-joined SQL fragment (empty string when nothing parsed).
 *   params – Positional bound parameter values.
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
        // Support scalar shorthand: {"col": "val"} → implicit eq filter.
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
 * Build a single WHERE condition for one column + spec pair.
 *
 * @internal
 * @param string   $col       Bare column name (validated here).
 * @param array    $spec      {'value': mixed, 'operator': string}
 * @param string   $prefix    Table alias prefix for the main table.
 * @param string[] $jsonCols  Column names treated as JSON (dot = JSON_EXTRACT).
 *                            All other dot-notation is treated as tableAlias.column.
 * @return array{sql: string, params: array<mixed>}|null  null when invalid input.
 */
function _sql_filter_condition(
    string $col,
    array $spec,
    string $prefix,
    array $jsonCols = ['data']
): ?array {
    // dot-notation: "data.field" → JSON_EXTRACT  |  "category.syscode" → category.syscode
    if (str_contains($col, '.')) {
        [$left, $right] = explode('.', $col, 2);
        if (
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $left) ||
            !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $right)
        ) {
            return null;
        }
        if (in_array($left, $jsonCols, true)) {
            // JSON sub-field: data.year → JSON_UNQUOTE(JSON_EXTRACT(p.data, '$.year'))
            $qualified = $prefix !== ''
                ? "JSON_UNQUOTE(JSON_EXTRACT({$prefix}.{$left}, '\$.{$right}'))"
                : "JSON_UNQUOTE(JSON_EXTRACT({$left}, '\$.{$right}'))";
        } else {
            // Related table column: category.syscode → category.syscode
            $qualified = "{$left}.{$right}";
        }
    } else {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
            return null;
        }
        $qualified = $prefix !== '' ? $prefix . '.' . $col : $col;
    }

    $rawOperator = $spec['operator'] ?? null;
    // Frontend sends operator as {"value": "$regex"} object; accept both formats.
    if (is_array($rawOperator)) {
        $rawOperator = $rawOperator['value'] ?? null;
    }

    // ── MongoDB-style spec: {"$regex":"val","$options":"i"} or {"$eq":"val"} ──
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
    // Strip leading $ sign used by frontend convention (e.g. "$regex" → "regex").
    $operator  = strtolower(trim(ltrim((string) ($rawOperator ?? 'eq'), '$')));
    $value     = $spec['value'] ?? null;

    return _sql_filter_operator($qualified, $operator, $value);
}

/**
 * Map a validated SQL operator + value to a fragment + params.
 *
 * @internal
 * @param string $col      Fully-qualified column (e.g. "u.created_at").
 * @param string $operator One of the supported operator names.
 * @param mixed  $value    Scalar, array (for range/in), or null (for null/notnull).
 * @return array{sql: string, params: array<mixed>}|null  null when operator/value invalid.
 */
function _sql_filter_operator(string $col, string $operator, mixed $value): ?array
{
    // ── Simple comparison operators ──────────────────────────────────────────
    $comparison = match ($operator) {
        'eq'    => '=',
        'neq'   => '!=',
        'lt'    => '<',
        'lte'   => '<=',
        'gt'    => '>',
        'gte'   => '>=',
        default => null,
    };

    if ($comparison !== null) {
        if ($value === null) {
            return null;
        }
        return ['sql' => "{$col} {$comparison} ?", 'params' => [$value]];
    }

    // ── All other operators ──────────────────────────────────────────────────
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
