<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/**
 * Objevování jmen po klíči (`id > after`) napříč všemi aktivními jmény včetně
 * konceptů; žádný ruční katalog jmen.
 *
 * Duplicitní normalizované podoby se přeskakují, aby se neimportovalo totéž
 * jméno dvakrát. Zdroje takto zpracují jen jména, která v okurku skutečně jsou.
 */
final class EtymologDiscoveryRepository extends BaseRepository
{
    /**
     * Vrátí další kandidátní jméno po zadané pozici.
     *
     * @param  string $kind  Druh jména, nebo prázdný řetězec pro všechny.
     * @param  int    $after ID posledního zpracovaného jména.
     * @return array<string, mixed>|null  `{ id, name, kind }`, nebo null na konci.
     */
    public function next(string $kind, int $after): ?array
    {
        return $this->nextCandidate($kind, $after, false);
    }

    /** Return the next name still missing one of the four published narrative types. */
    public function nextIncomplete(string $kind, int $after): ?array
    {
        return $this->nextCandidate($kind, $after, true);
    }

    /** Return a resumed name only while its published narrative coverage is incomplete. */
    public function findIncomplete(int $id, string $kind): ?array
    {
        $name = $this->find($id, $kind);
        if (!$name) { return null; }
        return $this->_db->fetchOne('SELECT n.id,n.name,n.kind FROM etymolog_name n WHERE n.franchise_code=? AND n.id=? AND '.self::incompleteSql(), [$this->_code, $id]) ?: null;
    }

    /**
     * Four types form the useful core of a dossier. A short proverb is valid;
     * etymology, mythology and tradition each need at least 50 characters.
     * Count only entries visible in the public detail, including reviewed links
     * and active historical spelling duplicates of the same name and kind.
     */
    private static function incompleteSql(): string
    {
        return 'NOT ('.implode(' AND ', [
            self::typeCoverage('etymology', 50),
            self::typeCoverage('mythology', 50),
            self::typeCoverage('tradition', 50),
            self::typeCoverage('proverb', 1),
        ]).')';
    }

    /** A fixed SQL fragment: type and minimum are defined in code, never supplied by callers. */
    private static function typeCoverage(string $type, int $minimum): string
    {
        return "(EXISTS (SELECT 1 FROM etymolog_name m JOIN etymolog_entry e ON e.franchise_code=m.franchise_code AND e.name_id=m.id
                WHERE m.franchise_code=n.franchise_code AND m.kind=n.kind AND m.normalized_name=n.normalized_name AND m.deleted=0
                AND e.deleted=0 AND e.published=1 AND e.type='$type' AND CHAR_LENGTH(TRIM(e.body)) >= $minimum)
            OR EXISTS (SELECT 1 FROM etymolog_name m JOIN etymolog_entry_name l ON l.franchise_code=m.franchise_code AND l.name_id=m.id AND l.deleted=0 AND l.reviewed=1
                JOIN etymolog_entry e ON e.franchise_code=l.franchise_code AND e.id=l.entry_id
                WHERE m.franchise_code=n.franchise_code AND m.kind=n.kind AND m.normalized_name=n.normalized_name AND m.deleted=0
                AND e.deleted=0 AND e.published=1 AND e.type='$type' AND CHAR_LENGTH(TRIM(e.body)) >= $minimum))";
    }

    private function nextCandidate(string $kind, int $after, bool $incompleteOnly): ?array
    {
        $incomplete = $incompleteOnly ? ' AND '.self::incompleteSql() : '';
        return $this->_db->fetchOne("SELECT n.id,n.name,n.kind FROM etymolog_name n WHERE n.franchise_code=? AND n.deleted=0 AND (?='' OR n.kind=?) AND n.kind IN ('given','surname') AND n.id>? AND NOT EXISTS (SELECT 1 FROM etymolog_name earlier WHERE earlier.franchise_code=n.franchise_code AND earlier.kind=n.kind AND earlier.deleted=0 AND earlier.id<n.id AND earlier.normalized_name=n.normalized_name)".$incomplete.' ORDER BY n.id LIMIT 1', [$this->_code,$kind,$kind,$after]) ?: null;
    }

    /**
     * Načte konkrétní jméno pro pokračování rozpracované práce.
     *
     * @param  int    $id   ID jména.
     * @param  string $kind Druh jména, který musí odpovídat, nebo prázdný řetězec pro všechny.
     * @return array<string, mixed>|null `{ id, name, kind }`, nebo null pokud neexistuje nebo je zrušené.
     */
    public function find(int $id, string $kind): ?array
    {
        return $this->_db->fetchOne("SELECT id,name,kind FROM etymolog_name WHERE franchise_code=? AND id=? AND (?='' OR kind=?) AND deleted=0", [$this->_code,$id,$kind,$kind]) ?: null;
    }
}
