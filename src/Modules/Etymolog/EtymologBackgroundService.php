<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

final class EtymologBackgroundService
{
    /** Launcher is injected so tests never spawn a real importer. */
    public function __construct(private readonly EtymologBatchRepository $batches, private readonly EtymologSyncService $sync, private readonly \Closure $launch) {}

    public function status(): ?array { return $this->batches->status(); }

    public function start(?int $actor): array
    {
        $request = $this->batches->enqueue($actor);
        if ($request['accepted']) {
            try { ($this->launch)($request['request_id']); }
            catch (\Throwable $e) {
                $reason = $e instanceof SyncException && in_array($e->reason, ['worker_process_disabled', 'worker_unavailable', 'worker_configuration_invalid'], true) ? $e->reason : 'worker_launch_failed';
                // Queue acceptance may succeed even when the HTTP acknowledgement is lost.
                if (!$this->batches->failQueuedLaunch($request['request_id'], $reason)) { return $this->batches->status() + ['accepted' => true]; }
                throw new EtymologException('Background worker could not be started: '.$reason, 503);
            }
        }
        return $request;
    }

    /** Only requests created by the admin button; never start an autonomous pass. */
    public function workQueued(): array
    {
        $current = $this->batches->status();
        if (!$current || $current['status'] !== 'queued') { return ['status' => 'idle']; }
        return $this->work($current['request_id']);
    }

    /** Cron and the detached CLI use exactly this pass: one batch per due enabled job. */
    public function work(?string $requestId = null): array
    {
        return $this->batches->lock('worker', function () use ($requestId) {
            if ($requestId === null) {
                $previous = $this->batches->status();
                if ($previous && $previous['status'] === 'running' && ($previous['pending_jobs'] ?? null) !== null) { return ['status' => 'idle']; }
                if ($previous && $previous['status'] === 'running') {
                    $this->batches->update($previous['request_id'], ['status' => 'failed', 'error_code' => 'worker_interrupted', 'finished_at' => gmdate('Y-m-d H:i:s')]);
                }
                $request = $this->batches->enqueue(null);
                $requestId = $request['request_id'];
            }
            $current = $this->batches->status();
            if (!$current || $current['request_id'] !== $requestId || $current['status'] !== 'queued') {
                return ['status' => 'idle'];
            }
            $done = 0; $failed = 0; $processed = 0;
            try {
                $ids = $this->batches->dueIds();
                $this->batches->update($requestId, ['status' => 'running', 'total' => count($ids)]);
                foreach ($ids as $id) {
                    try { $result = $this->sync->run($id); $processed += $result['processed']; }
                    catch (SyncException) { ++$failed; } // Already audited with retry time; continue other sources.
                    ++$done;
                    $this->batches->update($requestId, ['completed' => $done, 'failed' => $failed, 'processed' => $processed]);
                }
                $this->batches->update($requestId, ['status' => $failed ? 'partial' : 'complete', 'finished_at' => gmdate('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {
                $this->batches->update($requestId, ['status' => 'failed', 'error_code' => 'worker_failed', 'finished_at' => gmdate('Y-m-d H:i:s')]);
                throw $e;
            }
            return $this->batches->status();
        });
    }
}
