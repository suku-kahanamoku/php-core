<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/**
 * SQL pro importy licencovaných etymologických a statistických zdrojů;
 * voláno pod zámkem okurku a v transakci.
 *
 * Repo zásadně nepřepisuje ruční editaci, citace ani stav publikace — při
 * opakovaném importu jen aktualizuje snapshot a přesměruje vazby na cílové
 * jméno. Pokud cílový záznam už neexistuje, import tiše skončí, aby nevznikaly
 * osiřelé vazby.
 */
final class EtymologExternalRepository extends BaseRepository
{
    /**
     * Uloží jednu položku z externího zdroje.
     *
     * Podle poskytovatele vytvoří buď výklad s citací (Wiktionary, Wikipedie)
     * nebo statistický výskyt (PESEL, ČSÚ).
     *
     * @param  string $provider Klíč poskytovatele z podporované množiny.
     * @param  array<string, mixed> $item Importovaná položka (jméno, zdroj, licence, payload, revize).
     * @return void                     Vedlejší efekt: zápis záznamu, případně výkladu, citace a výskytu.
     * @throws SyncException           'unsupported_external_provider' pro neznámého poskytovatele.
     * @throws EtymologException       422 při požadavku na neznámý zdroj v `imports()`.
     * @throws \JsonException         Pokud payload nelze serializovat nebo dekódovat.
     */
    public function import(string $provider, array $item): void
    {
        if (!in_array($provider, ['wiktionary', 'wiktionary-cs', 'wiktionary-fr', 'wikipedia-names', 'poland-pesel', 'csu-baby-names'], true)) { throw new SyncException('unsupported_external_provider'); }
        $existing = $this->_db->fetchOne('SELECT * FROM etymolog_external_record WHERE franchise_code=? AND provider=? AND external_id=?', [$this->_code, $provider, $item['external_id']]);
        // Reuse evidence imported before DB-driven discovery, including archived entries.
        // Match source page + section + tenant name/kind, never a list of legacy name keys.
        if (!$existing && $provider === 'wikipedia-names' && isset($item['payload']['page_id'], $item['payload']['section'])) {
            $existing = $this->_db->fetchOne("SELECT r.* FROM etymolog_external_record r JOIN etymolog_name n ON n.id=r.name_id AND n.franchise_code=r.franchise_code WHERE r.franchise_code=? AND r.provider=? AND r.external_id NOT LIKE 'cs:auto:%' AND n.kind=? AND n.normalized_name=LOWER(TRIM(?)) AND JSON_UNQUOTE(JSON_EXTRACT(r.payload,'$.page_id'))=? AND JSON_UNQUOTE(JSON_EXTRACT(r.payload,'$.section'))=? ORDER BY r.id LIMIT 1", [$this->_code,$provider,$item['kind'],$item['name'],(string)$item['payload']['page_id'],$item['payload']['section']]);
            if ($existing) {
                $this->_db->update('etymolog_external_record', ['external_id'=>$item['external_id']], 'franchise_code=? AND id=?', [$this->_code,$existing['id']]);
            }
        }
        $payload = json_encode($item['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot = ['revision' => $item['revision'], 'source_url' => $item['source_url'], 'license' => $item['license'],
            'license_url' => $item['license_url'], 'attribution' => $item['attribution'], 'payload' => $payload,
            'content_hash' => hash('sha256', $payload), 'fetched_at' => gmdate('Y-m-d H:i:s')];
        if ($existing) {
            foreach (['name_id' => 'etymolog_name', 'source_id' => 'etymolog_source', 'entry_id' => 'etymolog_entry', 'occurrence_id' => 'etymolog_occurrence'] as $field => $table) {
                if ($existing[$field] !== null && !$this->_db->fetchOne("SELECT id FROM {$table} WHERE franchise_code=? AND id=? AND deleted=0", [$this->_code, $existing[$field]])) { return; }
            }
            $nameId = (new EtymologNameRepository($this->_db, $this->_code))->existing((int)$existing['name_id']);
            if ($nameId === null) { return; }
            foreach (['entry_id' => 'etymolog_entry', 'occurrence_id' => 'etymolog_occurrence'] as $field => $table) {
                if ($existing[$field] !== null) {
                    $this->_db->update($table, ['name_id' => $nameId], 'franchise_code=? AND id=? AND name_id=?', [$this->_code, $existing[$field], $existing['name_id']]);
                }
            }
            $snapshot['name_id'] = $nameId;
            $this->_db->update('etymolog_external_record', $snapshot, 'id=? AND franchise_code=?', [$existing['id'], $this->_code]);
            return; // Never overwrite manual edits, citations or publication.
        }
        $nameKey = $provider.':'.hash('sha256', json_encode([$item['kind'], $item['language'], $item['country_code'], $item['name']], JSON_THROW_ON_ERROR));
        $sourceKey = $provider.':'.hash('sha256', $item['source_key']);
        $source = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_source WHERE franchise_code=? AND import_key=?', [$this->_code, $sourceKey]);
        if ($source && (int)$source['deleted'] === 1) { return; }
        $nameId = (new EtymologNameRepository($this->_db, $this->_code))->resolve($item['name'], $item['kind'], $item['language'], $item['country_code'], $nameKey);
        if ($nameId === null) { return; }
        $sourceId = $source ? (int)$source['id'] : $this->_db->insert('etymolog_source', [
            'franchise_code' => $this->_code, 'import_key' => $sourceKey, 'title' => $item['source_title'],
            'url' => $item['source_url'], 'license' => $item['license'], 'license_url' => $item['license_url'],
            'attribution' => $item['attribution'], 'notes' => $item['notes'],
        ]);
        $entryId = null; $occurrenceId = null;
        if (in_array($provider, ['wiktionary', 'wiktionary-cs', 'wiktionary-fr', 'wikipedia-names'], true)) {
            $entryId = $this->_db->insert('etymolog_entry', $item['entry'] + ['franchise_code' => $this->_code, 'name_id' => $nameId]);
            $this->_db->insert('etymolog_citation', ['franchise_code' => $this->_code, 'entry_id' => $entryId, 'source_id' => $sourceId,
                'url' => $item['source_url'], 'locator' => $item['locator'] ?? 'Wiktionary revision '.$item['revision'], 'quotation' => $provider === 'wikipedia-names' ? $item['entry']['body'] : null, 'notes' => $item['notes']]);
        } else {
            $occurrenceId = $this->_db->insert('etymolog_occurrence', $item['occurrence'] + ['franchise_code' => $this->_code, 'name_id' => $nameId, 'source_id' => $sourceId]);
        }
        $this->_db->insert('etymolog_external_record', $snapshot + ['franchise_code' => $this->_code, 'provider' => $provider,
            'external_id' => $item['external_id'], 'name_id' => $nameId, 'source_id' => $sourceId, 'entry_id' => $entryId, 'occurrence_id' => $occurrenceId]);
    }

    /**
     * Vrátí importní záznamy navázané na záznam daného zdroje.
     *
     * @param  string $resource Klíč zdroje ('names', 'entries', 'occurrences', 'calendar-days').
     * @param  int    $id      ID záznamu, podle kterého se importy filtrují.
     * @return list<array<string, mixed>> Záznamy s dekódovaným payloadem, seřazené podle ID.
     * @throws EtymologException       422 'Invalid import resource' pro neznámý zdroj.
     * @throws \JsonException         Pokud uložený payload není platný JSON.
     */
    public function imports(string $resource, int $id): array
    {
        $column = ['names' => 'name_id', 'entries' => 'entry_id', 'occurrences' => 'occurrence_id', 'calendar-days' => 'calendar_day_id'][$resource] ?? throw new EtymologException('Invalid import resource');
        $rows = $this->_db->fetchAll("SELECT id,provider,external_id,name_id,source_id,entry_id,occurrence_id,calendar_day_id,revision,source_url,license,license_url,attribution,payload,content_hash,fetched_at FROM etymolog_external_record WHERE franchise_code=? AND {$column}=? ORDER BY id", [$this->_code, $id]);
        foreach ($rows as &$row) { $row['payload'] = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); }
        return $rows;
    }
}
