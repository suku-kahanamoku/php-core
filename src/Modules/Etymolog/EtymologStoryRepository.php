<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Etymolog\Providers\WikisourceProvider;

/** Story SQL only; called under the same tenant lock and batch transaction as name imports. */
final class EtymologStoryRepository extends BaseRepository
{
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
            // Preserve all editorial content, citations, reviewed/rejected links and publication state.
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
        foreach ($item['names'] as $suggestion) {
            $name = is_array($suggestion) ? $suggestion['name'] : $suggestion;
            $kind = is_array($suggestion) ? $suggestion['kind'] : 'given';
            // Exact spelling, language and kind only. Ambiguous matches remain suggestions in the snapshot.
            $key = $kind === 'given' ? 'wikisource:name:cs:'.hash('sha256', $name) : 'wikisource:surname:cs:'.hash('sha256', $name);
            $stable = $this->_db->fetchOne('SELECT id,deleted FROM etymolog_name WHERE franchise_code=? AND import_key=?', [$this->_code, $key]);
            $matches = $stable ? [$stable] : $this->_db->fetchAll("SELECT id,deleted FROM etymolog_name WHERE franchise_code=? AND BINARY name=BINARY ? AND kind=? AND language='cs'", [$this->_code, $name, $kind]);
            if (count($matches) > 1 || ($matches && (int)$matches[0]['deleted'] === 1)) { continue; }
            $nameId = $matches ? (int)$matches[0]['id'] : $this->_db->insert('etymolog_name', [
                'franchise_code' => $this->_code, 'name' => $name, 'kind' => $kind, 'language' => 'cs',
                'published' => 0, 'import_key' => $key,
            ]);
            $this->_db->insert('etymolog_entry_name', ['franchise_code' => $this->_code, 'entry_id' => $entryId,
                'name_id' => $nameId, 'relation' => 'mentioned', 'reviewed' => 0,
                'notes' => 'Návrh z kurátorovaného seznamu postav; vyžaduje redakční kontrolu.']);
        }
        $this->_db->insert('etymolog_story_import', $snapshot + ['franchise_code' => $this->_code,
            'entry_id' => $entryId, 'source_id' => $sourceId, 'provider' => $provider, 'external_id' => $item['external_id']]);
        return ['entry_id' => $entryId, 'source_id' => $sourceId];
    }

    public function imports(int $entryId): array
    {
        $rows = $this->_db->fetchAll('SELECT id,source_id,provider,external_id,revision,source_url,license,license_url,attribution,payload,content_hash,fetched_at FROM etymolog_story_import WHERE franchise_code=? AND entry_id=? ORDER BY id', [$this->_code, $entryId]);
        foreach ($rows as &$row) { $row['payload'] = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR); }
        return $rows;
    }
}
