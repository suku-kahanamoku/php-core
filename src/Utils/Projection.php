<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Projection – rizeni toho, ktere sloupce vracejí repozitare.
 *
 * Pravidla
 * --------
 *   null             → projekce neni nastavena; vrat VSECHNY sloupce (zpetna kompatibilita)
 *   []               → prazdna; vrat jen systemove sloupce
 *   ['col']          → systemove sloupce + col
 *   ['rel_id']       → jen FK sloupec, bez JOINu
 *   ['rel']          → JOIN relace, vrat vsechny jeji sloupce
 *   ['rel.col']      → JOIN relace, vrat jen tento sloupec
 *   ['json.key']     → JSON sloupec + filtrovat jen tento podklic (pokud je 'json' v OWN, ne REL)
 *
 * Pouziti v repozitari
 * --------------------
 *   private const SYS = ['id', 'created_at', 'updated_at'];
 *   private const OWN = ['name', 'email', 'role_id', ...];  // bez SYS, bez password
 *   private const REL = ['role'];                            // znama jmena relaci
 *
 *   $proj = new Projection($projection);
 *
 *   // 1. Ziskej vlastni sloupce pro SELECT
 *   $ownCols = $proj->getOwnCols(self::OWN, self::REL);
 *
 *   // 2. Zda pridat JOIN (false pro JSON sloupce)
 *   if ($proj->needsJoin('role')) { ... }
 *
 *   // 3. Ktere sloupce relace SELECTovat (null=preskoc, []=vsechny, [...]=specificke)
 *   $relCols = $proj->getRelationCols('role');
 *
 *   // 4. Filtruj radek vysledku (podklice JSON sloupcu jsou filtrovany automaticky)
 *   $row = $proj->apply($row, self::SYS, ['role' => ['role', 'role_id']]);
 */
class Projection
{
    /** Vlastni sloupce tabulky explicitne pozadovane (bez tecky). */
    private array $ownCols = [];

    /**
     * Zaznamy s teckovym zapisem rozdelene dle prefixu.
     * Pro relace: ['user' => ['first_name', 'email']]
     * Pro JSON sloupce: ['data' => ['quality', 'volume']]
     */
    private array $dotRels = [];

    /**
     * @param array<string>|null $fields  null = vsechny (bez omezeni); [] = jen systemove; [...] = specificke.
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
     * true → projekce neni nastavena → vrat vse.
     *
     * @return bool
     */
    public function isAll(): bool
    {
        return $this->fields === null;
    }

    /**
     * true → projekce je explicitne [] → jen systemove sloupce.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->fields !== null && $this->fields === [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // SQL pomocnici
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Vrati ktere vlastni sloupce tabulky maji byt selectovany (systemove sloupce prida repozitar samostatne).
     *
     * @param string[] $all       Vsechny selektovatelne vlastni sloupce (bez systemovych, bez hesla atd.)
     * @param string[] $relNames  Znama logicka jmena relaci ('role', 'user', ...)
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
                // Jmeno relace → zahr jeho FK (napr. 'user' → 'user_id')
                $fk = "{$col}_id";
                if (in_array($fk, $all, true) && !in_array($fk, $cols, true)) {
                    $cols[] = $fk;
                }
            } elseif (in_array($col, $all, true) && !in_array($col, $cols, true)) {
                $cols[] = $col;
            }
        }

        // Pro zaznamy s teckovym zapisem: zahr FK pro relace NEBO samotny sloupec pro JSON.
        foreach (array_keys($this->dotRels) as $rel) {
            if (in_array($rel, $relNames, true)) {
                // Znama relace: zahr jeho FK
                $fk = "{$rel}_id";
                if (in_array($fk, $all, true) && !in_array($fk, $cols, true)) {
                    $cols[] = $fk;
                }
            } elseif (in_array($rel, $all, true) && !in_array($rel, $cols, true)) {
                // JSON sloupec (podpole s teckovym zapisem): zahr samotny sloupec
                $cols[] = $rel;
            }
        }

        return $cols;
    }

    /**
     * Urcuje zda ma byt pojmenovana relace JOINovana.
     * Vrati false pro JSON sloupce (ty jsou OWN sloupce, nikoli relace).
     *
     * @param  string   $name       Logicky nazev relace (napr. 'role', 'user')
     * @param  string[] $relNames   Znama jmena relaci — pokud jsou zadana, prefix teckoveho zapisu
     *                              odpovidajici OWN sloupci (nikoli relaci) vraci false.
     * @return bool
     */
    public function needsJoin(string $name, array $relNames = []): bool
    {
        if ($this->isAll()) {
            return true;
        }

        if ($this->isEmpty()) {
            return false;
        }

        if (!in_array($name, $this->ownCols, true) && !isset($this->dotRels[$name])) {
            return false;
        }

        // Pokud volajici zadal znama jmena relaci a prefix teckoveho zapisu NENI mezi nimi,
        // jde o JSON sloupec — JOIN neni potreba.
        if (
            $relNames !== [] &&
            isset($this->dotRels[$name]) &&
            !in_array($name, $relNames, true)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Vrati pozadovana jmena podpoli JSON sloupce pristupovaneho pres teckovy zapis.
     * Vrati null  → sloupec neni pristupovan pres teckovy zapis (zahr plne nebo vubec).
     * Vrati []    → pozadovany vsechny podklice (sloupec byl uveden bez teckoveho zapisu).
     * Vrati [...] → jen tyto specificke podklice.
     *
     * @param  string $col  Nazev sloupce (napr. 'data')
     * @return string[]|null
     */
    public function getDotSubfields(string $col): ?array
    {
        if ($this->isAll()) {
            return null;
        }
        return $this->dotRels[$col] ?? null;
    }

    /**
     * Ktere sloupce relace SELECTovat.
     *
     * @param  string       $name  Logicky nazev relace
     * @return null          → relace neni potreba (neprovadej JOIN)
     * @return array{}       → vsechny sloupce
     * @return string[]      → specificka jmena sloupcu
     */
    public function getRelationCols(string $name): ?array
    {
        if (!$this->needsJoin($name)) {
            return null;
        }

        // Jednoduche jmeno relace ('user') → vsechny sloupce
        if ($this->isAll() || in_array($name, $this->ownCols, true)) {
            return [];
        }

        // Jen teckovy zapis
        return $this->dotRels[$name] ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Filtrovani a vnoreni po nacteni
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Filtruje nacteny radek dle projekce a vnori sloupce relaci do podobjektu.
     *
     * @param array    $row       Surovy radek z databaze.
     * @param string[] $sys       Systemove sloupce, ktere jsou vzdy zachovany.
     * @param array    $relMap    Mapa relaci. Jsou podporovany dva formaty:
     *
     *   Stary format (ploche, bez vnoreni):
    *     ['categories' => ['category_ids']]
     *
     *   Novy format (vnoreny podobjekt):
     *     ['user' => [
     *         'fk'   => 'user_id',                              // FK zustava v koreni
     *         'nest' => ['first_name', 'last_name', 'email'],   // plany seznam NEBO
     *                   ['name' => 'role_name', 'id' => 'role_id'], // assoc: vystupniKlic => radkovyKlic
     *     ]]
     *
     *   Novy format vzdy vytvari vnoreny podobjekt, napr.:
     *     { "user_id": 5, "user": { "first_name": "Jan", ... } }
     */
    public function apply(array $row, array $sys, array $relMap = []): array
    {
        if ($this->isAll()) {
            // Bez filtrovani – jen aplikuj vnoreni pro relace noveho formatu.
            return $this->nestRelations($row, $relMap);
        }

        // Shromazdi radkove klice patrici relacim (FK je vyloucen z allRelKeys, aby mohl
        // byt pozadovan jako bezny vlastni sloupec).
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
                        // Novy format: cela relace pozadovana → zachovej FK + vsechny nest radkove klice.
                        if (isset($def['fk'])) {
                            $keep[] = $def['fk'];
                        }
                        array_push($keep, ...array_values($def['nest']));
                    } else {
                        // Stary format.
                        array_push($keep, ...array_values($def));
                    }
                } elseif (!in_array($col, $allRelKeys, true)) {
                    $keep[] = $col;
                }
            }

            foreach ($this->dotRels as $rel => $reqCols) {
                if (!isset($relMap[$rel])) {
                    // JSON sloupec pristupovany pres teckovy zapis — zahr ho, aby apply() mohl filtrovat podklice.
                    $keep[] = $rel;
                    continue;
                }
                $def = $relMap[$rel];
                if (is_array($def) && isset($def['nest'])) {
                    // Novy format: vzdy zachovej FK, pak resolvi kazdy pozadovany sloupec.
                    if (isset($def['fk'])) {
                        $keep[] = $def['fk'];
                    }
                    foreach ($reqCols as $c) {
                        // 1. Asociativni nest: 'vystupniKlic' => 'radkovyKlic'
                        if (isset($def['nest'][$c])) {
                            $keep[] = $def['nest'][$c];
                            continue;
                        }
                        // 2. Plany seznam: sloupec je uvedeny jako nest hodnota
                        if (in_array($c, array_values($def['nest']), true)) {
                            $keep[] = $c;
                            continue;
                        }
                        // 3. Alias jako {rel}_{col}
                        $aliased = "{$rel}_{$c}";
                        if (in_array($aliased, array_values($def['nest']), true)) {
                            $keep[] = $aliased;
                        }
                    }
                } else {
                    // Stary format.
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

        // Filtruj podklice JSON sloupcu pristupovanych pres teckovy zapis.
        foreach ($this->dotRels as $rel => $reqCols) {
            if (
                !isset($relMap[$rel]) &&
                isset($filtered[$rel]) &&
                is_array($filtered[$rel]) &&
                $reqCols !== []
            ) {
                $filtered[$rel] = array_intersect_key(
                    $filtered[$rel],
                    array_flip($reqCols)
                );
            }
        }

        return $this->nestRelations($filtered, $relMap);
    }

    /**
     * Presune sloupce relaci noveho formatu z planeho radku do vnoreneho podobjektu.
     * Relace stareho formatu (plany seznam) zustavaji beze zmeny.
     */
    private function nestRelations(array $row, array $relMap): array
    {
        foreach ($relMap as $rel => $def) {
            if (!is_array($def) || !isset($def['nest'])) {
                continue; // Stary format – bez vnoreni.
            }
            $nested = [];
            $fk     = $def['fk'] ?? null;
            foreach ($def['nest'] as $outKey => $rowKey) {
                if (is_int($outKey)) {
                    $outKey = $rowKey; // Plany seznam: pouzij radkovy klic jako vystupni klic.
                }
                if (array_key_exists($rowKey, $row)) {
                    $nested[$outKey] = $row[$rowKey];
                    if ($rowKey !== $fk) {
                        unset($row[$rowKey]); // Odeber z korene (FK zustava v koreni).
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
