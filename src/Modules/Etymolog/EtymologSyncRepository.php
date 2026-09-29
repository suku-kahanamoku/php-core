<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Etymolog\Providers\WikidataProvider;

final class EtymologSyncRepository extends BaseRepository
{
    public function nextJob(?int $id = null): ?array
    {
        $extra = $id === null ? '' : ' AND id = ?';
        return $this->_db->fetchOne('SELECT * FROM etymolog_sync_job WHERE franchise_code=? AND deleted=0 AND enabled=1 AND (next_run_at IS NULL OR next_run_at<=UTC_TIMESTAMP())'.$extra.' ORDER BY COALESCE(next_run_at,created_at),id LIMIT 1',
            $id === null ? [$this->_code] : [$this->_code, $id]) ?: null;
    }

    public function start(int $jobId): int
    {
        // Under the tenant advisory lock, a previous running record can only be an interrupted process.
        $this->_db->query("UPDATE etymolog_sync_run SET status='interrupted',error_code='process_interrupted',finished_at=UTC_TIMESTAMP() WHERE franchise_code=? AND status='running'", [$this->_code]);
        return $this->_db->insert('etymolog_sync_run', ['franchise_code' => $this->_code, 'job_id' => $jobId, 'status' => 'running', 'started_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function import(array $job, array $item): void
    {
        $key = 'wikidata:'.$job['kind'].':'.$item['external_id'];
        $existing = $this->_db->fetchOne("SELECT i.id,i.name_id FROM etymolog_import_record i JOIN etymolog_name n ON n.franchise_code=i.franchise_code AND n.id=i.name_id WHERE i.franchise_code=? AND i.provider='wikidata' AND i.external_id=? AND n.kind=? ORDER BY i.id LIMIT 1", [$this->_code, $item['external_id'], $job['kind']]);
        $id = (new EtymologNameRepository($this->_db, $this->_code))->resolve(
            $item['name'], $job['kind'], $job['language'], null, $key,
            $existing ? (int)$existing['name_id'] : null,
        );
        if ($id === null) { return; }
        // Source snapshots stay separate even when several Wikidata entities describe one name.
        $payload = json_encode($item['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data = ['name_id' => $id, 'external_id' => $item['external_id'], 'source_url' => $item['source_url'], 'license' => WikidataProvider::LICENSE,
            'license_url' => WikidataProvider::LICENSE_URL, 'attribution' => WikidataProvider::ATTRIBUTION,
            'revision' => $item['revision'], 'payload' => $payload, 'content_hash' => hash('sha256', $payload), 'fetched_at' => gmdate('Y-m-d H:i:s')];
        $existing = $this->_db->fetchOne("SELECT id FROM etymolog_import_record WHERE franchise_code=? AND name_id=? AND provider='wikidata' AND external_id=?", [$this->_code, $id, $item['external_id']]) ?: $existing;
        if ($existing) {
            $this->_db->update('etymolog_import_record', $data, 'id=? AND franchise_code=?', [(int)$existing['id'], $this->_code]);
        } else {
            $this->_db->insert('etymolog_import_record', $data + ['franchise_code' => $this->_code, 'name_id' => $id, 'provider' => 'wikidata']);
        }
    }

    public function finish(array $job, int $runId, string $status, int $processed, ?string $cursor, ?string $error = null, int $retryAfter = 0): void
    {
        $this->_db->update('etymolog_sync_run', ['status' => $status, 'processed' => $processed, 'error_code' => $error, 'finished_at' => gmdate('Y-m-d H:i:s')],
            'id=? AND franchise_code=?', [$runId, $this->_code]);
        $this->_db->update('etymolog_sync_job', ['cursor' => $cursor, 'last_status' => $status, 'last_error' => $error,
            'next_run_at' => gmdate('Y-m-d H:i:s', time() + ($error === 'upstream_rate_limited' ? max(300, $retryAfter) : max((int)$job['interval_seconds'], $retryAfter)))],
            'id=? AND franchise_code=?', [(int)$job['id'], $this->_code]);
    }

    public function imports(int $nameId): array
    {
        $rows = $this->_db->fetchAll('SELECT id,provider,external_id,source_url,license,license_url,attribution,revision,payload,content_hash,fetched_at FROM etymolog_import_record WHERE franchise_code=? AND name_id=? ORDER BY id', [$this->_code, $nameId]);
        foreach ($rows as &$row) {
            $row['payload'] = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
        }
        return $rows;
    }

    public function runs(int $jobId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, min(1000000, $page));
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;
        $total = (int)$this->_db->fetchOne('SELECT COUNT(*) AS cnt FROM etymolog_sync_run WHERE franchise_code=? AND job_id=?', [$this->_code, $jobId])['cnt'];
        $rows = $this->_db->fetchAll("SELECT id,job_id,status,processed,error_code,started_at,finished_at FROM etymolog_sync_run WHERE franchise_code=? AND job_id=? ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", [$this->_code, $jobId]);
        return $this->_resultList($rows, $total, $page, $limit);
    }
}
