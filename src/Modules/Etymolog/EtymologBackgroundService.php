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
            catch (\Throwable) {
                $this->batches->update($request['request_id'], ['status' => 'failed', 'error_code' => 'worker_launch_failed', 'finished_at' => gmdate('Y-m-d H:i:s')]);
                throw new EtymologException('Background worker could not be started', 503);
            }
        }
        return $request;
    }

    /** Cron and the detached CLI use exactly this pass: one batch per due enabled job. */
    public function work(?string $requestId = null): array
    {
        return $this->batches->lock('worker', function () use ($requestId) {
            if ($requestId === null) {
                $previous = $this->batches->status();
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
