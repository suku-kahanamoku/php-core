<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;

/** A short request lock and a separate worker lock keep HTTP requests nonblocking. */
final class EtymologBatchRepository extends BaseRepository
{
    public function __construct(Database $db, string $tenant, private readonly int $queuedTimeout = 120, private readonly int $runningTimeout = 120)
    {
        parent::__construct($db, $tenant);
    }

    private function expired(array $row): bool
    {
        $timeout = $row['status'] === 'queued' ? $this->queuedTimeout : $this->runningTimeout;
        return max(strtotime($row['heartbeat_at'].' UTC'), $row['retry_at'] ? strtotime($row['retry_at'].' UTC') : 0) < time() - $timeout;
    }

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
            if ($this->expired($row) && (int)($this->_db->fetchOne('SELECT IS_FREE_LOCK(?) available', ['ety-worker:'.substr(hash('sha256', $this->_code), 0, 48)])['available'] ?? 0) === 1) {
                try {
                    $this->lock('worker', function () use (&$row) {
                        $fresh = $this->_db->fetchOne('SELECT * FROM etymolog_sync_batch WHERE franchise_code=?', [$this->_code]);
                        if ($fresh && $fresh['request_id'] === $row['request_id'] && in_array($fresh['status'], ['queued', 'running'], true) && $this->expired($fresh)) {
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
            $this->_db->query("INSERT INTO etymolog_sync_batch (franchise_code,request_id,status,requested_by,created_at,heartbeat_at) VALUES (?,?,'queued',?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id),status='queued',requested_by=VALUES(requested_by),total=0,completed=0,failed=0,processed=0,error_code=NULL,pending_jobs=NULL,retry_at=NULL,retry_count=0,created_at=UTC_TIMESTAMP(),heartbeat_at=UTC_TIMESTAMP(),finished_at=NULL", [$this->_code, $id, $actor]);
            return $this->status() + ['accepted' => true];
        });
    }

    public function failQueuedLaunch(string $id, string $reason): bool
    {
        $stmt = $this->_db->query("UPDATE etymolog_sync_batch SET status='failed',error_code=?,finished_at=UTC_TIMESTAMP(),heartbeat_at=UTC_TIMESTAMP() WHERE franchise_code=? AND request_id=? AND status='queued'", [$reason, $this->_code, $id]);
        return $stmt->rowCount() > 0;
    }

    public function nightly(string $date): array
    {
        $existing = $this->_db->fetchOne('SELECT request_id FROM etymolog_sync_schedule WHERE franchise_code=? AND scheduled_date=?', [$this->_code, $date]);
        if ($existing) { return ['request_id' => $existing['request_id'], 'next_step' => 0]; }
        $pdo = $this->_db->getPdo(); $pdo->beginTransaction();
        try {
            $request = $this->enqueue(null);
            $this->_db->insert('etymolog_sync_schedule', ['franchise_code' => $this->_code, 'scheduled_date' => $date, 'request_id' => $request['request_id']]);
            $pdo->commit();
            return ['request_id' => $request['request_id'], 'next_step' => $request['completed']];
        } catch (\Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    }

    public function prepareSteps(string $id): array
    {
        $ids = $this->dueIds();
        $this->update($id, ['pending_jobs' => json_encode($ids, JSON_THROW_ON_ERROR), 'total' => count($ids), 'status' => $ids ? 'running' : 'complete', 'finished_at' => $ids ? null : gmdate('Y-m-d H:i:s')]);
        return $this->status();
    }

    /** Called inside the same transaction as imported content, job cursor and audit. */
    public function finishStep(array $batch, array $result): void
    {
        $limited = ($result['error_code'] ?? null) === 'upstream_rate_limited';
        $retryAt = $limited ? gmdate('Y-m-d H:i:s', time() + max(300, min(604800, (int)($result['retry_after'] ?? 300)))) : null;
        if ($limited && (int)$batch['retry_count'] < 2) {
            // Audit/cursor and this deferral commit in one transaction. No completed/failed count yet.
            $this->update($batch['request_id'], ['retry_at' => $retryAt, 'retry_count' => (int)$batch['retry_count'] + 1]);
            return;
        }
        $completed = (int)$batch['completed'] + 1;
        $failed = (int)$batch['failed'] + ($result['status'] === 'failed' ? 1 : 0);
        $done = $completed >= (int)$batch['total'];
        $this->update($batch['request_id'], ['completed' => $completed, 'failed' => $failed, 'retry_count' => 0, 'retry_at' => $done ? null : $retryAt,
            'processed' => (int)$batch['processed'] + $result['processed'],
            'status' => $done ? ($failed ? 'partial' : 'complete') : 'running',
            'finished_at' => $done ? gmdate('Y-m-d H:i:s') : null]);
    }

    public function retryAfter(array $batch): int
    {
        return $batch['retry_at'] ? max(0, strtotime($batch['retry_at'].' UTC') - time()) : 0;
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
