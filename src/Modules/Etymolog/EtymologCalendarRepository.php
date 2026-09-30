<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/**
 * SQL pro importy kalendářů se zdrojem; volající drží výhradní zámek okurku
 * a transakci.
 *
 * Zrušené záznamy se nikdy neobnovují — místo toho se import tiše ukončí,
 * aby nevznikaly duplicity. Datum se u folklorních textů odvozuje jen z data
 * kapitoly pramene; vztah ke jménům zůstává redakčně kontrolovaný a
 * nezveřejněný.
 */
final class EtymologCalendarRepository extends BaseRepository
{
    /**
     * Vyřeší nebo vytvoří kalendář podle importního klíče.
     *
     * @param  array<string, mixed> $data Atributy kalendáře včetně `import_key`.
     * @return int|null                ID kalendáře, nebo null pokud byl zrušený.
     */
    private function calendar(array $data): ?int
    {
        $row = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_calendar WHERE franchise_code=? AND import_key=?', [$this->_code, $data['import_key']]);
        if ($row) {return (int)$row['deleted'] === 1 ? null : (int)$row['id'];}
        return $this->_db->insert('etymolog_calendar', $data + ['franchise_code' => $this->_code]);
    }

    /**
     * Připojí kalendářní den s datem folklorní kapitoly k existujícímu textu.
     *
     * @param  array<string, mixed> $item  Položka s `calendar`, názvem a datem kapitoly.
     * @param  array{entry_id: int, source_id: int} $story Uložený text a jeho zdroj.
     * @return void                       Vedlejší efekt: vložení nezveřejněného dne.
     */
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

    /**
     * Jedenkrát po ověřené dávce z Wikipedie zruší původní komunitní kalendář;
     * voláno ve stejném zámku okurku a transakci.
     *
     * @return void Vedlejší efekt: zrušení původních dnů, zdroje a kalendáře a oprava názvu úlohy.
     */
    public function retireLegacyCalendar(): void
    {
        $sources = $this->_db->fetchAll("SELECT id FROM etymolog_source WHERE franchise_code=? AND import_key='czech-namedays:cs' AND deleted=0", [$this->_code]);
        foreach ($sources as $source) {
            $this->_db->query('UPDATE etymolog_calendar_day SET published=0,deleted=1 WHERE franchise_code=? AND source_id=?', [$this->_code, $source['id']]);
            $this->_db->update('etymolog_source', ['deleted'=>1], 'franchise_code=? AND id=?', [$this->_code, $source['id']]);
        }
        $this->_db->query("UPDATE etymolog_calendar SET deleted=1 WHERE franchise_code=? AND import_key='czech-namedays:cs' AND deleted=0", [$this->_code]);
        $this->_db->query("UPDATE etymolog_sync_job SET title=? WHERE franchise_code=? AND provider='czech-namedays' AND title='Český jmenný kalendář – komunitní zdroj'", ['Wikipedie – český jmenný kalendář', $this->_code]);
        // Keep original snapshots and shared names. Never relabel GitHub data as Wikipedia.
    }

    /**
     * Uloží nebo aktualizuje kalendářní den s jmeninou.
     *
     * @param  array<string, mixed> $item Položka z poskytovatele jmenin (jméno, den, měsíc, revize, licence).
     * @return void                   Vedlejší efekt: zápis dne, zdroje, kalendáře a importního záznamu.
     * @throws \JsonException        Pokud payload nelze serializovat.
     */
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
            if ($existing['name_id'] !== null) {
                $nameId = (new EtymologNameRepository($this->_db, $this->_code))->existing((int)$existing['name_id']);
                if ($nameId === null) { return; }
                $this->_db->update('etymolog_calendar_day', ['name_id' => $nameId], 'franchise_code=? AND id=? AND name_id=?', [$this->_code, $existing['calendar_day_id'], $existing['name_id']]);
                $snapshot['name_id'] = $nameId;
            }
            $this->_db->update('etymolog_external_record', $snapshot, 'id=? AND franchise_code=?', [$existing['id'], $this->_code]);
            return;
        }
        $calendarId = $this->calendar(['import_key' => Providers\CzechNamedaysProvider::SOURCE_KEY, 'title' => Providers\CzechNamedaysProvider::TITLE, 'country_code' => 'CZ',
            'system' => 'gregorian', 'tradition' => 'Český občanský jmenný kalendář podle Wikipedie', 'notes' => 'Konkrétní webová edice; není univerzální ani oficiální kalendář.']);
        if ($calendarId === null) {return;}
        $source = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_source WHERE franchise_code=? AND import_key=?', [$this->_code, Providers\CzechNamedaysProvider::SOURCE_KEY]);
        if ($source && (int)$source['deleted'] === 1) {return;}
        $nameId = null;
        if ($item['name'] !== null) {
            $nameKey = 'czech-namedays:'.hash('sha256', $item['name']);
            $nameId = (new EtymologNameRepository($this->_db, $this->_code))->resolve($item['name'], 'given', 'cs', null, $nameKey);
            if ($nameId === null) { return; }
        }
        $sourceId = $source ? (int)$source['id'] : $this->_db->insert('etymolog_source', ['franchise_code' => $this->_code, 'import_key' => Providers\CzechNamedaysProvider::SOURCE_KEY,
            'title' => Providers\CzechNamedaysProvider::TITLE, 'url' => $item['source_url'], 'license' => $item['license'], 'license_url' => $item['license_url'], 'attribution' => $item['attribution']]);
        $dayId = $this->_db->insert('etymolog_calendar_day', ['franchise_code' => $this->_code, 'import_key' => $provider.':'.hash('sha256', $item['external_id']),
            'calendar_id' => $calendarId, 'source_id' => $sourceId, 'name_id' => $nameId, 'title' => $item['title'], 'kind' => $item['kind'],
            'month' => $item['month'], 'day' => $item['day'], 'source_url' => $item['source_url'], 'locator' => $item['locator'] ?? ('Jmeniny / '.$item['day'].'. '.$item['month'].'.'), 'published' => 0]);
        $this->_db->insert('etymolog_external_record', $snapshot + ['franchise_code' => $this->_code, 'provider' => $provider, 'external_id' => $item['external_id'],
            'name_id' => $nameId, 'source_id' => $sourceId, 'calendar_day_id' => $dayId]);
    }
}
