<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/** Shared import identity. Caller holds Etymolog's tenant lock and transaction. */
final class EtymologNameRepository extends BaseRepository
{
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

    public function existing(int $id): ?int
    {
        // The existing row supplies all identity/editorial fields.
        return $this->resolve('', '', null, null, '', $id);
    }
}
