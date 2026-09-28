<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

final class EtymologSyncService
{
    public function __construct(private readonly EtymologRepository $jobs, private readonly EtymologSyncRepository $sync, private readonly ProviderRegistry $providers, private readonly ?EtymologStoryRepository $stories = null, private readonly ?EtymologExternalRepository $external = null, private readonly ?EtymologCalendarRepository $calendar = null) {}

    /** One bounded batch per invocation; cursor is committed with the entire batch. */
    public function run(?int $jobId = null): array
    {
        return $this->jobs->exclusive(function () use ($jobId) {
            $job = $this->sync->nextJob($jobId);
            if (!$job) {
                return ['status' => 'idle', 'processed' => 0];
            }
            $runId = $this->sync->start((int)$job['id']);
            try {
                $batch = $this->providers->get($job['provider'])->batch($job['language'], $job['kind'], $job['cursor'], (int)$job['batch_size']);
                return $this->jobs->transaction(function () use ($job, $runId, $batch) {
                    foreach ($batch['items'] as $item) {
                        if (in_array($job['provider'], ['wikisource', 'erben-folklore'], true)) {
                            $story = ($this->stories ?? throw new SyncException('story_repository_missing'))->import($item, $job['provider']);
                            if ($job['provider'] === 'erben-folklore' && $story !== null) {($this->calendar ?? throw new SyncException('calendar_repository_missing'))->attachFolklore($item, $story);}
                        } elseif ($job['provider'] === 'czech-namedays') {
                            ($this->calendar ?? throw new SyncException('calendar_repository_missing'))->import($item);
                        } elseif (in_array($job['provider'], ['wiktionary', 'poland-pesel', 'csu-baby-names'], true)) {
                            ($this->external ?? throw new SyncException('external_repository_missing'))->import($job['provider'], $item);
                        } else {
                            $this->sync->import($job, $item);
                        }
                    }
                    $status = $batch['complete'] ? 'complete' : 'success';
                    $cursor = $batch['complete'] ? null : $batch['cursor'];
                    $count = count($batch['items']);
                    $this->sync->finish($job, $runId, $status, $count, $cursor);
                    return ['run_id' => $runId, 'job_id' => (int)$job['id'], 'status' => $status, 'processed' => $count, 'scanned' => $batch['scanned'] ?? $count, 'cursor' => $cursor];
                });
            } catch (\Throwable $e) {
                $reason = $e instanceof SyncException ? $e->reason : 'sync_failed';
                $delay = $e instanceof SyncException ? $e->retryAfter : 300;
                $this->jobs->transaction(fn () => $this->sync->finish($job, $runId, 'failed', 0, $job['cursor'], $reason, $delay));
                throw new SyncException($reason, $delay);
            }
        });
    }
}
