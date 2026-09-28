<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;

/** A short request lock and a separate worker lock keep HTTP requests nonblocking. */
final class EtymologBatchRepository extends BaseRepository
{
    public function lock(string $kind, callable $action): mixed
    {
        $key = 'ety-'.$kind.':'.substr(hash('sha256', $this->_code), 0, 48);
        if ((int)($this->_db->fetchOne('SELECT GET_LOCK(?,0) acquired', [$key])['acquired'] ?? 0) !== 1) {
            throw new EtymologException('Synchronization is already running', 409);
        }
        try { return $action(); }
        finally { $this->_db->fetchOne('SELECT RELEASE_LOCK(?) released', [$key]); }
    }

    public function status(): ?array
    {
        $row = $this->_db->fetchOne('SELECT * FROM etymolog_sync_batch WHERE franchise_code=?', [$this->_code]) ?: null;
        if ($row && in_array($row['status'], ['queued', 'running'], true)) {
            if (strtotime($row['heartbeat_at'].' UTC') < time() - 120 && (int)($this->_db->fetchOne('SELECT IS_FREE_LOCK(?) available', ['ety-worker:'.substr(hash('sha256', $this->_code), 0, 48)])['available'] ?? 0) === 1) {
                try {
                    $this->lock('worker', function () use (&$row) {
                        $fresh = $this->_db->fetchOne('SELECT * FROM etymolog_sync_batch WHERE franchise_code=?', [$this->_code]);
                        if ($fresh && $fresh['request_id'] === $row['request_id'] && in_array($fresh['status'], ['queued', 'running'], true) && strtotime($fresh['heartbeat_at'].' UTC') < time() - 120) {
                            $this->update($fresh['request_id'], ['status' => 'failed', 'error_code' => 'worker_interrupted', 'finished_at' => gmdate('Y-m-d H:i:s')]);
                            $row['status'] = 'failed'; $row['error_code'] = 'worker_interrupted';
                        }
                    });
                } catch (EtymologException $e) {
                    if ($e->status !== 409) { throw $e; } // A live worker still owns the connection lock.
                }
            }
        }
        if ($row) {
            unset($row['franchise_code']);
            foreach (['total', 'completed', 'failed', 'processed'] as $key) { $row[$key] = (int)$row[$key]; }
        }
        return $row;
    }

    public function enqueue(?int $actor): array
    {
        return $this->lock('request', function () use ($actor) {
            $current = $this->status();
            if ($current && in_array($current['status'], ['queued', 'running'], true)) { return $current + ['accepted' => false]; }
            $id = bin2hex(random_bytes(16));
            $this->_db->query("INSERT INTO etymolog_sync_batch (franchise_code,request_id,status,requested_by,created_at,heartbeat_at) VALUES (?,?,'queued',?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id),status='queued',requested_by=VALUES(requested_by),total=0,completed=0,failed=0,processed=0,error_code=NULL,created_at=UTC_TIMESTAMP(),heartbeat_at=UTC_TIMESTAMP(),finished_at=NULL", [$this->_code, $id, $actor]);
            return $this->status() + ['accepted' => true];
        });
    }

    public function dueIds(): array
    {
        return array_map(static fn ($row) => (int)$row['id'], $this->_db->fetchAll('SELECT id FROM etymolog_sync_job WHERE franchise_code=? AND deleted=0 AND enabled=1 AND (next_run_at IS NULL OR next_run_at<=UTC_TIMESTAMP()) ORDER BY COALESCE(next_run_at,created_at),id', [$this->_code]));
    }

    public function update(string $id, array $data): void
    {
        $this->_db->update('etymolog_sync_batch', $data + ['heartbeat_at' => gmdate('Y-m-d H:i:s')], 'franchise_code=? AND request_id=?', [$this->_code, $id]);
    }
}
