<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/** SQL for licensed etymology/statistics imports, called under tenant lock + transaction. */
final class EtymologExternalRepository extends BaseRepository
{
    public function import(string $provider, array $item): void
    {
        if (!in_array($provider, ['wiktionary', 'wiktionary-cs', 'wiktionary-fr', 'wikipedia-names', 'poland-pesel', 'csu-baby-names'], true)) { throw new SyncException('unsupported_external_provider'); }
        $existing = $this->_db->fetchOne('SELECT * FROM etymolog_external_record WHERE franchise_code=? AND provider=? AND external_id=?', [$this->_code, $provider, $item['external_id']]);
        $payload = json_encode($item['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot = ['revision' => $item['revision'], 'source_url' => $item['source_url'], 'license' => $item['license'],
            'license_url' => $item['license_url'], 'attribution' => $item['attribution'], 'payload' => $payload,
            'content_hash' => hash('sha256', $payload), 'fetched_at' => gmdate('Y-m-d H:i:s')];
        if ($existing) {
            foreach (['name_id' => 'etymolog_name', 'source_id' => 'etymolog_source', 'entry_id' => 'etymolog_entry', 'occurrence_id' => 'etymolog_occurrence'] as $field => $table) {
                if ($existing[$field] !== null && !$this->_db->fetchOne("SELECT id FROM {$table} WHERE franchise_code=? AND id=? AND deleted=0", [$this->_code, $existing[$field]])) { return; }
            }
            $this->_db->update('etymolog_external_record', $snapshot, 'id=? AND franchise_code=?', [$existing['id'], $this->_code]);
            return; // Never overwrite manual edits, citations or publication.
        }
        $nameKey = $provider.':'.hash('sha256', json_encode([$item['kind'], $item['language'], $item['country_code'], $item['name']], JSON_THROW_ON_ERROR));
        $stable = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_name WHERE franchise_code=? AND import_key=?', [$this->_code, $nameKey]);
        $names = $stable ? [$stable] : $this->_db->fetchAll('SELECT id,deleted FROM etymolog_name WHERE franchise_code=? AND BINARY name=BINARY ? AND kind=? AND language<=>? AND (? IS NOT NULL OR country_code<=>?)', [$this->_code, $item['name'], $item['kind'], $item['language'], $item['language'], $item['country_code']]);
        if (count($names) > 1) { throw new SyncException('ambiguous_name_match'); }
        if ($names && (int)$names[0]['deleted'] === 1) { return; }
        $sourceKey = $provider.':'.hash('sha256', $item['source_key']);
        $source = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_source WHERE franchise_code=? AND import_key=?', [$this->_code, $sourceKey]);
        if ($source && (int)$source['deleted'] === 1) { return; }
        $nameId = $names ? (int)$names[0]['id'] : $this->_db->insert('etymolog_name', [
            'franchise_code' => $this->_code, 'name' => $item['name'], 'kind' => $item['kind'], 'language' => $item['language'],
            'country_code' => $item['country_code'], 'published' => 0, 'import_key' => $nameKey,
        ]);
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

    public function imports(string $resource, int $id): array
    {
        $column = ['names' => 'name_id', 'entries' => 'entry_id', 'occurrences' => 'occurrence_id', 'calendar-days' => 'calendar_day_id'][$resource] ?? throw new EtymologException('Invalid import resource');
        $rows = $this->_db->fetchAll("SELECT id,provider,external_id,name_id,source_id,entry_id,occurrence_id,calendar_day_id,revision,source_url,license,license_url,attribution,payload,content_hash,fetched_at FROM etymolog_external_record WHERE franchise_code=? AND {$column}=? ORDER BY id", [$this->_code, $id]);
        foreach ($rows as &$row) { $row['payload'] = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); }
        return $rows;
    }
}
