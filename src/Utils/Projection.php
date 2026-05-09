<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Projection – controls which columns are returned by repository queries.
 *
 * Rules
 * -----
 *   null             → no projection set; return ALL columns (backward-compatible)
 *   []               → empty; return system columns only
 *   ['col']          → system cols + col
 *   ['rel_id']       → FK column only, no JOIN
 *   ['rel']          → JOIN relation, return all its columns
 *   ['rel.col']      → JOIN relation, return only that column
 *
 * Usage in a repository
 * ---------------------
 *   private const SYS = ['id', 'created_at', 'updated_at'];
 *   private const OWN = ['name', 'email', 'role_id', ...];  // excl. SYS, excl. password
 *   private const REL = ['role'];                            // known relation names
 *
 *   $proj = new Projection($projection);
 *
 *   // 1. Get own columns to SELECT
 *   $ownCols = $proj->getOwnCols(self::OWN, self::REL);
 *
 *   // 2. Whether to add a JOIN
 *   if ($proj->needsJoin('role')) { ... }
 *
 *   // 3. Which relation columns to SELECT (null=skip, []=all, [...]=specific)
 *   $relCols = $proj->getRelationCols('role');
 *
 *   // 4. Filter result row
 *   $row = $proj->apply($row, self::SYS, ['role' => ['role', 'role_id']]);
 */
class Projection
{
    /** Own-table columns explicitly requested (no dot). */
    private array $ownCols = [];

    /** Dot-notation relation columns: ['user' => ['first_name', 'email'], ...] */
    private array $dotRels = [];

    /**
     * @param array<string>|null $fields  null = all (no restriction); [] = system only; [...] = specified.
     */
    public function __construct(private readonly ?array $fields = null)
    {
        foreach ($fields ?? [] as $f) {
            $f = trim($f);
            if ($f === '') {
                continue;
            }
            if (str_contains($f, '.')) {
                [$rel, $col]           = explode('.', $f, 2);
                $this->dotRels[$rel][] = $col;
            } else {
                $this->ownCols[] = $f;
            }
        }
    }

    /**
     * true → no projection supplied → return everything.
     *
     * @return bool
     */
    public function isAll(): bool
    {
        return $this->fields === null;
    }

    /**
     * true → projection is explicitly [] → system columns only.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->fields !== null && $this->fields === [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SQL helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Return which OWN table columns should be selected (system cols are added by the repo separately).
     *
     * @param string[] $all       All selectable own columns (no system cols, no password, etc.)
     * @param string[] $relNames  Known logical relation names ('role', 'user', …)
     * @return string[]
     */
    public function getOwnCols(array $all, array $relNames = []): array
    {
        if ($this->isAll()) {
            return $all;
        }

        if ($this->isEmpty()) {
            return [];
        }

        $cols = [];

        foreach ($this->ownCols as $col) {
            if (in_array($col, $relNames, true)) {
                // Relation name → include its FK (e.g. 'user' → 'user_id')
                $fk = "{$col}_id";
                if (in_array($fk, $all, true) && !in_array($fk, $cols, true)) {
                    $cols[] = $fk;
                }
            } elseif (in_array($col, $all, true) && !in_array($col, $cols, true)) {
                $cols[] = $col;
            }
        }

        // For dot-notation relations, also include the FK
        foreach (array_keys($this->dotRels) as $rel) {
            $fk = "{$rel}_id";
            if (in_array($fk, $all, true) && !in_array($fk, $cols, true)) {
                $cols[] = $fk;
            }
        }

        return $cols;
    }

    /**
     * Whether a named relation should be JOINed/fetched.
     *
     * @param  string $name  Logicky nazev relace (napr. 'role', 'user')
     * @return bool
     */
    public function needsJoin(string $name): bool
    {
        if ($this->isAll()) {
            return true;
        }

        if ($this->isEmpty()) {
            return false;
        }

        return in_array($name, $this->ownCols, true) || isset($this->dotRels[$name]);
    }

    /**
     * Which columns of the relation to SELECT.
     *
     * @param  string       $name  Logicky nazev relace
     * @return null          → relation not needed (don't JOIN)
     * @return array{}       → all columns
     * @return string[]      → specific column names
     */
    public function getRelationCols(string $name): ?array
    {
        if (!$this->needsJoin($name)) {
            return null;
        }

        // Plain relation name ('user') → all columns
        if ($this->isAll() || in_array($name, $this->ownCols, true)) {
            return [];
        }

        // Dot-notation only
        return $this->dotRels[$name] ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Post-fetch filter + nesting
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Filter a fetched row according to the projection and nest relation columns into sub-objects.
     *
     * @param array    $row       The raw row from the database.
     * @param string[] $sys       System columns that are always kept.
     * @param array    $relMap    Relation map. Two formats are supported:
     *
     *   Old format (flat, no nesting):
     *     ['categories' => ['category_ids', 'category_names']]
     *
     *   New format (nested sub-object):
     *     ['user' => [
     *         'fk'   => 'user_id',                              // FK stays at root
     *         'nest' => ['first_name', 'last_name', 'email'],   // flat list OR
     *                   ['name' => 'role_name', 'id' => 'role_id'], // assoc: outputKey => rowKey
     *     ]]
     *
     *   New format always produces a nested sub-object, e.g.:
     *     { "user_id": 5, "user": { "first_name": "Jan", ... } }
     */
    public function apply(array $row, array $sys, array $relMap = []): array
    {
        if ($this->isAll()) {
            // No filtering – just apply nesting for new-format relations.
            return $this->nestRelations($row, $relMap);
        }

        // Collect row-keys that belong to relations (FK excluded from allRelKeys so it can
        // still be requested as a plain own-column).
        $allRelKeys = [];
        foreach ($relMap as $def) {
            if (is_array($def) && isset($def['nest'])) {
                $fk = $def['fk'] ?? null;
                foreach (array_values($def['nest']) as $v) {
                    if ($v !== $fk) {
                        $allRelKeys[] = $v;
                    }
                }
            } elseif (is_array($def)) {
                array_push($allRelKeys, ...array_values($def));
            }
        }
        $allRelKeys = array_unique($allRelKeys);

        $keep = $sys;

        if (!$this->isEmpty()) {
            foreach ($this->ownCols as $col) {
                if (isset($relMap[$col])) {
                    $def = $relMap[$col];
                    if (is_array($def) && isset($def['nest'])) {
                        // New format: whole relation requested → keep FK + all nest row-keys.
                        if (isset($def['fk'])) {
                            $keep[] = $def['fk'];
                        }
                        array_push($keep, ...array_values($def['nest']));
                    } else {
                        // Old format.
                        array_push($keep, ...array_values($def));
                    }
                } elseif (!in_array($col, $allRelKeys, true)) {
                    $keep[] = $col;
                }
            }

            foreach ($this->dotRels as $rel => $reqCols) {
                if (!isset($relMap[$rel])) {
                    continue;
                }
                $def = $relMap[$rel];
                if (is_array($def) && isset($def['nest'])) {
                    // New format: always keep FK, then resolve each requested column.
                    if (isset($def['fk'])) {
                        $keep[] = $def['fk'];
                    }
                    foreach ($reqCols as $c) {
                        // 1. Assoc nest: 'outputKey' => 'rowKey'
                        if (isset($def['nest'][$c])) {
                            $keep[] = $def['nest'][$c];
                            continue;
                        }
                        // 2. Flat: col appears as a nest value
                        if (in_array($c, array_values($def['nest']), true)) {
                            $keep[] = $c;
                            continue;
                        }
                        // 3. Aliased as {rel}_{col}
                        $aliased = "{$rel}_{$c}";
                        if (in_array($aliased, array_values($def['nest']), true)) {
                            $keep[] = $aliased;
                        }
                    }
                } else {
                    // Old format.
                    $available = $def;
                    foreach ($reqCols as $c) {
                        if (isset($available[$c])) {
                            $keep[] = $available[$c];
                            continue;
                        }
                        if (in_array($c, array_values($available), true)) {
                            $keep[] = $c;
                            continue;
                        }
                        $aliased = "{$rel}_{$c}";
                        if (in_array($aliased, array_values($available), true)) {
                            $keep[] = $aliased;
                        }
                    }
                }
            }
        }

        $filtered = array_intersect_key($row, array_flip(array_unique($keep)));

        return $this->nestRelations($filtered, $relMap);
    }

    /**
     * Move new-format relation columns from flat row into a nested sub-object.
     * Old-format relations (flat array) are left untouched.
     */
    private function nestRelations(array $row, array $relMap): array
    {
        foreach ($relMap as $rel => $def) {
            if (!is_array($def) || !isset($def['nest'])) {
                continue; // Old format – no nesting.
            }
            $nested = [];
            $fk     = $def['fk'] ?? null;
            foreach ($def['nest'] as $outKey => $rowKey) {
                if (is_int($outKey)) {
                    $outKey = $rowKey; // Flat list: use row-key as output key.
                }
                if (array_key_exists($rowKey, $row)) {
                    $nested[$outKey] = $row[$rowKey];
                    if ($rowKey !== $fk) {
                        unset($row[$rowKey]); // Remove from root (FK stays at root).
                    }
                }
            }
            if ($nested !== []) {
                $row[$rel] = $nested;
            }
        }

        return $row;
    }
}
