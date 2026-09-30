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
        return $this->_db->fetchOne("SELECT n.id,n.name,n.kind FROM etymolog_name n WHERE n.franchise_code=? AND n.deleted=0 AND (?='' OR n.kind=?) AND n.kind IN ('given','surname') AND n.id>? AND NOT EXISTS (SELECT 1 FROM etymolog_name earlier WHERE earlier.franchise_code=n.franchise_code AND earlier.kind=n.kind AND earlier.deleted=0 AND earlier.id<n.id AND earlier.normalized_name=n.normalized_name) ORDER BY n.id LIMIT 1", [$this->_code,$kind,$kind,$after]) ?: null;
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
