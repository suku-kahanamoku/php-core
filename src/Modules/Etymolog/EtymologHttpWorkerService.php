<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/** One HTTP call runs one job batch; SQL and source progress commit together. */
final class EtymologHttpWorkerService
{
    public function __construct(private readonly EtymologBatchRepository $batches, private readonly EtymologSyncService $sync) {}

    public function nightly(string $date): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague'));
        if ($now->format('Y-m-d') !== $date || $now->format('H') !== '03') { throw new EtymologException('Nightly request outside Prague 03:00 window', 422); }
        return $this->batches->lock('worker', fn () => $this->batches->nightly($date));
    }

    public function step(string $id, int $index): array
    {
        return $this->batches->lock('worker', function () use ($id, $index) {
            $batch = $this->batches->status();
            if (!$batch || $batch['request_id'] !== $id) { return ['status' => 'idle']; }
            if (!in_array($batch['status'], ['queued', 'running'], true)) { return $this->result($batch); }
            if ($batch['status'] === 'queued') { $batch = $this->batches->prepareSteps($id); }
            if ($index < $batch['completed']) { return $this->result($batch); } // Lost response / duplicate delivery.
            if ($index !== $batch['completed']) { throw new EtymologException('Out of order worker step', 409); }
            if ($batch['completed'] >= $batch['total']) { return $this->result($batch); }
            $ids = json_decode($batch['pending_jobs'], true, 64, JSON_THROW_ON_ERROR);
            try {
                $this->sync->run((int)$ids[$index], fn (array $result) => $this->batches->finishStep($batch, $result));
            } catch (SyncException) { /* Audited failure and step progress have committed together. */ }
            return $this->result($this->batches->status());
        });
    }

    private function result(array $batch): array
    {
        return ['status' => $batch['status'], 'request_id' => $batch['request_id'], 'next_step' => (int)$batch['completed'], 'total' => (int)$batch['total']];
    }
}
