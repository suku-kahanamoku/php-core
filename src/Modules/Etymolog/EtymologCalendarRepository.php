<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/** SQL for sourced calendar imports; caller holds the tenant lock and transaction. */
final class EtymologCalendarRepository extends BaseRepository
{
    private function calendar(array $data): ?int
    {
        $row = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_calendar WHERE franchise_code=? AND import_key=?', [$this->_code, $data['import_key']]);
        if ($row) {return (int)$row['deleted'] === 1 ? null : (int)$row['id'];}
        return $this->_db->insert('etymolog_calendar', $data + ['franchise_code' => $this->_code]);
    }

    public function attachFolklore(array $item, array $story): void
    {
        if (!isset($item['calendar'])) {return;}
        $key = 'erben:'.$item['external_id'];
        if ($this->_db->fetchOne('SELECT id FROM etymolog_calendar_day WHERE franchise_code=? AND import_key=?', [$this->_code, $key])) {return;}
        $calendarId = $this->calendar($item['calendar']);
        if ($calendarId === null) {return;}
        $this->_db->insert('etymolog_calendar_day', ['franchise_code' => $this->_code, 'import_key' => $key, 'calendar_id' => $calendarId,
            'source_id' => $story['source_id'], 'entry_id' => $story['entry_id'], 'name_id' => null, 'title' => $item['title'],
            'kind' => 'folklore', 'month' => $item['month'], 'day' => $item['day'], 'source_url' => $item['source_url'], 'locator' => $item['bibliography'],
            'notes' => 'Datum kapitoly historické sbírky; vztah ke jménům je veden přes redakčně kontrolované entry-names.', 'published' => 0]);
    }

    public function import(array $item): void
    {
        $provider = 'czech-namedays';
        $existing = $this->_db->fetchOne('SELECT * FROM etymolog_external_record WHERE franchise_code=? AND provider=? AND external_id=?', [$this->_code, $provider, $item['external_id']]);
        $payload = json_encode($item['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot = ['revision' => $item['revision'], 'source_url' => $item['source_url'], 'license' => $item['license'],
            'license_url' => $item['license_url'], 'attribution' => $item['attribution'], 'payload' => $payload,
            'content_hash' => hash('sha256', $payload), 'fetched_at' => gmdate('Y-m-d H:i:s')];
        if ($existing) {
            foreach (['name_id' => 'etymolog_name', 'source_id' => 'etymolog_source', 'calendar_day_id' => 'etymolog_calendar_day'] as $field => $table) {
                if ($existing[$field] !== null && !$this->_db->fetchOne("SELECT id FROM {$table} WHERE franchise_code=? AND id=? AND deleted=0", [$this->_code, $existing[$field]])) {return;}
            }
            $day = $this->_db->fetchOne('SELECT c.deleted FROM etymolog_calendar_day d JOIN etymolog_calendar c ON c.franchise_code=d.franchise_code AND c.id=d.calendar_id WHERE d.franchise_code=? AND d.id=?', [$this->_code, $existing['calendar_day_id']]);
            if (!$day || (int)$day['deleted'] === 1) {return;}
            $this->_db->update('etymolog_external_record', $snapshot, 'id=? AND franchise_code=?', [$existing['id'], $this->_code]);
            return;
        }
        $calendarId = $this->calendar(['import_key' => 'czech-namedays:cs', 'title' => 'Český jmenný kalendář – segeda/svatky-api-nodejs', 'country_code' => 'CZ',
            'system' => 'gregorian', 'tradition' => 'Komunitní občanský jmenný kalendář', 'notes' => 'Konkrétní webová edice; není univerzální ani oficiální kalendář.']);
        if ($calendarId === null) {return;}
        $source = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_source WHERE franchise_code=? AND import_key=?', [$this->_code, 'czech-namedays:cs']);
        if ($source && (int)$source['deleted'] === 1) {return;}
        $nameId = null;
        if ($item['name'] !== null) {
            $nameKey = 'czech-namedays:'.hash('sha256', $item['name']);
            $stable = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_name WHERE franchise_code=? AND import_key=?', [$this->_code, $nameKey]);
            $matches = $stable ? [$stable] : $this->_db->fetchAll("SELECT id,deleted FROM etymolog_name WHERE franchise_code=? AND BINARY name=BINARY ? AND kind='given' AND language='cs'", [$this->_code, $item['name']]);
            if (count($matches)>1) {throw new SyncException('ambiguous_name_match');}
            if ($matches && (int)$matches[0]['deleted']===1) {return;}
            $nameId = $matches ? (int)$matches[0]['id'] : $this->_db->insert('etymolog_name', ['franchise_code' => $this->_code, 'import_key' => $nameKey, 'name' => $item['name'], 'kind' => 'given', 'language' => 'cs', 'published' => 0]);
        }
        $sourceId = $source ? (int)$source['id'] : $this->_db->insert('etymolog_source', ['franchise_code' => $this->_code, 'import_key' => 'czech-namedays:cs',
            'title' => 'segeda/svatky-api-nodejs – český kalendář', 'url' => $item['source_url'], 'license' => $item['license'], 'license_url' => $item['license_url'], 'attribution' => $item['attribution']]);
        $dayId = $this->_db->insert('etymolog_calendar_day', ['franchise_code' => $this->_code, 'import_key' => $provider.':'.hash('sha256', $item['external_id']),
            'calendar_id' => $calendarId, 'source_id' => $sourceId, 'name_id' => $nameId, 'title' => $item['title'], 'kind' => $item['kind'],
            'month' => $item['month'], 'day' => $item['day'], 'source_url' => $item['source_url'], 'locator' => 'cs.js: '.sprintf('%02d%02d', $item['day'], $item['month']), 'published' => 0]);
        $this->_db->insert('etymolog_external_record', $snapshot + ['franchise_code' => $this->_code, 'provider' => $provider, 'external_id' => $item['external_id'],
            'name_id' => $nameId, 'source_id' => $sourceId, 'calendar_day_id' => $dayId]);
    }
}
