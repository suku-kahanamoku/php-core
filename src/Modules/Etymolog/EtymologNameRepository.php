<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/**
 * Společná identita jmen při importu. Volající drží výhradní zámek okurku a transakci modulu Etymolog.
 *
 * Jméno se identifikuje podle normalizované podoby a druhu, přičemž diakritika
 * zůstává rozlišovaná. Editorské přejmenování a reklasifikace se zachovávají,
 * mění se pouze velikost písmen. Smazané („tombstone“) záznamy se nikdy nevrací
 * do stavu aktivního, místo toho se vrátí null.
 */
final class EtymologNameRepository extends BaseRepository
{
    /**
     * Vyřeší nebo vytvoří jméno a vrátí jeho ID.
     *
     * @param  string      $name      Normalizované jméno.
     * @param  string      $kind      Druh jména ('given' nebo 'surname').
     * @param  string|null $language  Jazyk jako součást důkazu.
     * @param  string|null $country   Země jako součást důkazu.
     * @param  string      $importKey Stabilní klíč importu (poskytovatel a druh zdroje).
     * @param  int|null    $existingId  Již existující ID, pokud má být použita jeho identita.
     * @return int|null               ID jména, nebo null pokud jméno bylo smazáno.
     * @throws SyncException          'missing_import_name', pokud zadané ID neexistuje.
     */
    public function resolve(string $name, string $kind, ?string $language, ?string $country, string $importKey, ?int $existingId = null): ?int
    {
        $existing = $existingId !== null
            ? $this->_db->fetchOne('SELECT * FROM etymolog_name WHERE franchise_code=? AND id=?', [$this->_code, $existingId])
            : $this->_db->fetchOne('SELECT * FROM etymolog_name WHERE franchise_code=? AND import_key=?', [$this->_code, $importKey]);
        if ($existingId !== null && !$existing) { throw new SyncException('missing_import_name'); }
        if ($existing) {
            if ((int)$existing['deleted'] === 1) { return null; }
            // Keep editorial renames and reclassification; normalize casing only.
            $name = $existing['name'];
            $kind = $existing['kind'];
        }
        $name = NameNormalizer::display($name);
        // Language/country/source describe evidence, not a separate name identity.
        // Indexed binary normalization retains diacritics; tombstones are never revived.
        $match = $this->_db->fetchOne('SELECT id,name,deleted FROM etymolog_name WHERE franchise_code=? AND kind=? AND normalized_name=LOWER(TRIM(?)) ORDER BY deleted DESC,(BINARY name=BINARY UPPER(name)),id LIMIT 1', [$this->_code, $kind, $name]);
        if ($match) {
            if ((int)$match['deleted'] === 1) { return null; }
            if ($match['name'] !== $name) {
                $this->_db->update('etymolog_name', ['name' => $name], 'id=? AND franchise_code=?', [$match['id'], $this->_code]);
            }
            return (int)$match['id'];
        }
        return $this->_db->insert('etymolog_name', ['franchise_code' => $this->_code, 'name' => $name,
            'kind' => $kind, 'language' => $language, 'country_code' => $country, 'published' => 0, 'import_key' => $importKey]);
    }

    /**
     * Načte existující jméno beze změny; existující řádek dodá všechny identifikační a editorské údaje.
     *
     * @param  int $id ID jména.
     * @return int|null  ID jména, nebo null pokud jméno je smazané.
     * @throws SyncException 'missing_import_name', pokud záznam neexistuje.
     */
    public function existing(int $id): ?int
    {
        // The existing row supplies all identity/editorial fields.
        return $this->resolve('', '', null, null, '', $id);
    }
}
