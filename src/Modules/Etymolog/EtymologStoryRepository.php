<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Etymolog\Providers\WikisourceProvider;

/**
 * Výhradně SQL pro kulturní texty (příběhy); voláno pod stejným zámkem okurku
 * a ve stejné dávkové transakci jako import jmen.
 *
 * Navržené vazby text–jméno se vytvářejí vždy jako nerevidované a ruční
 * odmítnutí se nikdy nepřepíše. Citace do textu výslovně dokládá znění pramene,
 * nikoli pravdivost děje, předpovědi nebo původ jména.
 */
final class EtymologStoryRepository extends BaseRepository
{
    /**
     * Uloží nebo aktualizuje jeden kulturní text.
     *
     * @param  array<string, mixed> $item     Položka s textem, zdrojem a navrženými jmény.
     * @param  string               $provider Klíč zdroje: 'wikisource' nebo 'erben-folklore'.
     * @return array{entry_id: int, source_id: int}|null IDs výkladu a zdroje, nebo null pokud
     *                                           byl výklad nebo zdroj smazán.
     * @throws SyncException                 'unsupported_story_provider' pro neznámý zdroj.
     * @throws \JsonException               Pokud payload nelze serializovat nebo dekódovat.
     */
    public function import(array $item, string $provider = 'wikisource'): ?array
    {
        if (!in_array($provider, ['wikisource', 'erben-folklore'], true)) {throw new SyncException('unsupported_story_provider');}
        $author = $item['author'] ?? WikisourceProvider::AUTHOR;
        $type = $item['type'] ?? 'legend';
        $existing = $this->_db->fetchOne("SELECT i.id,i.entry_id,i.source_id,e.deleted FROM etymolog_story_import i JOIN etymolog_entry e ON e.franchise_code=i.franchise_code AND e.id=i.entry_id WHERE i.franchise_code=? AND i.provider=? AND i.external_id=?", [$this->_code, $provider, $item['external_id']]);
        if ($existing && ((int)$existing['deleted'] === 1 || !$this->_db->fetchOne('SELECT id FROM etymolog_source WHERE franchise_code=? AND id=? AND deleted=0', [$this->_code, $existing['source_id']]))) { return null; }
        $payload = json_encode($item['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot = ['revision' => $item['revision'], 'source_url' => $item['source_url'],
            'license' => WikisourceProvider::LICENSE, 'license_url' => WikisourceProvider::LICENSE_URL,
            'attribution' => $author.'; Wikizdroje', 'payload' => $payload,
            'content_hash' => hash('sha256', $payload), 'fetched_at' => gmdate('Y-m-d H:i:s')];
        if ($existing) {
            // Reuse the same identity on refresh without recreating rejected links or changing review.
            foreach ($this->_db->fetchAll('SELECT id,name_id FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=? AND deleted=0', [$this->_code, $existing['entry_id']]) as $link) {
                $nameId = (new EtymologNameRepository($this->_db, $this->_code))->existing((int)$link['name_id']);
                if ($nameId === null || $nameId === (int)$link['name_id']) { continue; }
                // Do not overwrite an existing canonical link, including a rejected one.
                if (!$this->_db->fetchOne('SELECT id FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=? AND name_id=?', [$this->_code, $existing['entry_id'], $nameId])) {
                    $this->_db->update('etymolog_entry_name', ['name_id' => $nameId], 'franchise_code=? AND id=?', [$this->_code, $link['id']]);
                }
            }
            foreach ($item['names'] as $suggested) {
                $label=is_array($suggested) ? $suggested['name'] : $suggested;
                $kind=is_array($suggested) ? $suggested['kind'] : 'given';
                $name=$this->_db->fetchOne('SELECT id FROM etymolog_name WHERE franchise_code=? AND kind=? AND normalized_name=LOWER(TRIM(?)) AND deleted=0 ORDER BY id LIMIT 1', [$this->_code,$kind,$label]);
                if ($name && !$this->_db->fetchOne('SELECT id FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=? AND name_id=?', [$this->_code,$existing['entry_id'],$name['id']])) {
                    $this->_db->insert('etymolog_entry_name',['franchise_code'=>$this->_code,'entry_id'=>(int)$existing['entry_id'],'name_id'=>(int)$name['id'],'relation'=>'mentioned','reviewed'=>0]);
                }
            }
            // Preserve all editorial content, citations, reviewed/rejected states and publication.
            $this->_db->update('etymolog_story_import', $snapshot, 'id=? AND franchise_code=?', [(int)$existing['id'], $this->_code]);
            return ['entry_id' => (int)$existing['entry_id'], 'source_id' => (int)$existing['source_id']];
        }
        $sourceId = $this->_db->insert('etymolog_source', [
            'franchise_code' => $this->_code, 'title' => $item['source_title'] ?? ('Staré pověsti české (1959) – '.$item['title']),
            'author' => $author, 'url' => $item['source_url'], 'license' => WikisourceProvider::LICENSE,
            'license_url' => WikisourceProvider::LICENSE_URL, 'attribution' => $snapshot['attribution'], 'notes' => $item['bibliography'],
        ]);
        $entryId = $this->_db->insert('etymolog_entry', [
            'franchise_code' => $this->_code, 'name_id' => null, 'type' => $type, 'certainty' => $type === 'fiction' ? 'fiction' : 'unverified',
            'source_url' => $item['source_url'], 'title' => $item['title'], 'body' => $item['body'], 'language' => 'cs', 'region' => $item['region'], 'published' => 0,
        ]);
        $this->_db->insert('etymolog_citation', ['franchise_code' => $this->_code, 'entry_id' => $entryId,
            'source_id' => $sourceId, 'url' => $item['source_url'], 'quotation' => $item['body'], 'locator' => $item['bibliography'],
            'notes' => 'Převzatý kulturní text. Citace dokládá znění pramene, nikoli pravdivost děje, předpovědi nebo původ jména.']);
        $linked = [];
        foreach ($item['names'] as $suggestion) {
            $name = is_array($suggestion) ? $suggestion['name'] : $suggestion;
            $kind = is_array($suggestion) ? $suggestion['kind'] : 'given';
            // Shared case-insensitive name identity; suggested story links still require review.
            $key = $kind === 'given' ? 'wikisource:name:cs:'.hash('sha256', $name) : 'wikisource:surname:cs:'.hash('sha256', $name);
            $nameId = (new EtymologNameRepository($this->_db, $this->_code))->resolve($name, $kind, 'cs', null, $key);
            if ($nameId === null || isset($linked[$nameId])) { continue; }
            $linked[$nameId] = true;
            $this->_db->insert('etymolog_entry_name', ['franchise_code' => $this->_code, 'entry_id' => $entryId,
                'name_id' => $nameId, 'relation' => 'mentioned', 'reviewed' => 0,
                'notes' => 'Návrh podle výskytu jména v textu pramene; vyžaduje redakční kontrolu.']);
        }
        $this->_db->insert('etymolog_story_import', $snapshot + ['franchise_code' => $this->_code,
            'entry_id' => $entryId, 'source_id' => $sourceId, 'provider' => $provider, 'external_id' => $item['external_id']]);
        return ['entry_id' => $entryId, 'source_id' => $sourceId];
    }

    /**
     * Vrátí importní záznamy kulturního textu s dekódovaným payloadem.
     *
     * @param  int $entryId ID výkladu (textu).
     * @return list<array<string, mixed>> Záznamy seřazené podle ID.
     * @throws \JsonException               Pokud uložený payload není platný JSON.
     */
    public function imports(int $entryId): array
    {
        $rows = $this->_db->fetchAll('SELECT id,source_id,provider,external_id,revision,source_url,license,license_url,attribution,payload,content_hash,fetched_at FROM etymolog_story_import WHERE franchise_code=? AND entry_id=? ORDER BY id', [$this->_code, $entryId]);
        foreach ($rows as &$row) { $row['payload'] = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); }
        return $rows;
    }
}
