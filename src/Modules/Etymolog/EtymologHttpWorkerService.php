<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Worker synchronizace, kde každý HTTP požadavek zpracuje právě jednu dávku.
 *
 * Změny v databázi a postup zdroje se ukládají v jedné transakci, takže i při
 * ztrátě odpovědi nedojde k částečnému importu. Worker navíc musí volat kroky
 * popořadí — duplicitní krok se tiše ignoruje, krok mimo pořadí je chyba.
 */
final class EtymologHttpWorkerService
{
    /**
     * @param  EtymologBatchRepository $batches Repozitar stavu a fronty dávky.
     * @param  EtymologSyncService      $sync    Sluzby synchronizace jednotlivych zdrojů.
     * @return void
     */
    public function __construct(private readonly EtymologBatchRepository $batches, private readonly EtymologSyncService $sync) {}

    /**
     * Zahájí noční dávku, ale pouze v povoleném okně 03:00 podle času v Praze.
     *
     * @param  string $date Datum ve formátu `Y-m-d`.
     * @return array{request_id: string, next_step: int} ID dávky a krok k pokračování.
     * @throws EtymologException 422 při volání mimo okno, 409 při souběžném běhu.
     */
    public function nightly(string $date): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague'));
        if ($now->format('Y-m-d') !== $date || $now->format('H') !== '03') { throw new EtymologException('Nightly request outside Prague 03:00 window', 422); }
        return $this->batches->lock('worker', fn () => $this->batches->nightly($date));
    }

    /**
     * Zpracuje jednu dávku a vrátí stav pro pokračování workera.
     *
     * Opakované dodání téhož kroku vrací aktuální stav beze změny, krok mimo
     * pořadí je odmítnut s 409. Omezení rychlosti od upstreamu pouze vrátí stav
     * s nenulovým `retry_after`, další volání pak dávku zopakuje.
     *
     * @param  string $id    `request_id` dávky.
     * @param  int    $index Pořadové číslo kroku.
     * @return array<string, mixed> Stav dávky s `next_step`, `total` a `retry_after`.
     * @throws EtymologException 409 při kroku mimo pořadí, 422 při chybných parametrech.
     */
    public function step(string $id, int $index): array
    {
        return $this->batches->lock('worker',         /**
         * Tělo kroku běží pod zámkem workera v jedné transakci.
         *
         * @return array<string, mixed> Stav kroku a výsledek poskytovatele.
         * @throws EtymologException     409 při kroku mimo pořadí, 422 při chybných parametrech.
         */
function () use ($id, $index) {
            $batch = $this->batches->status();
            if (!$batch || $batch['request_id'] !== $id) { return ['status' => 'idle']; }
            if (!in_array($batch['status'], ['queued', 'running'], true)) { return $this->result($batch); }
            if ($batch['status'] === 'queued') { $batch = $this->batches->prepareSteps($id); }
            $batch = $this->batches->optimizePending($batch);
            if ($index < $batch['step_index']) { return $this->result($batch); } // Lost response / duplicate delivery.
            if ($index !== $batch['step_index']) { throw new EtymologException('Out of order worker step', 409); }
            if ($batch['completed'] >= $batch['total']) { return $this->result($batch); }
            if ($this->batches->retryAfter($batch) > 0) { return $this->result($batch); }
            $ids = $this->batches->progress($batch)['jobs'];
            try {
                $this->sync->run((int)$ids[$batch['completed']], fn (array $result) => $this->batches->finishStep($batch, $result));
            } catch (SyncException) { /* Audited failure and step progress have committed together. */ }
            return $this->result($this->batches->status());
        });
    }

    /**
     * Sestaví odpověď workera ze stavu dávky.
     *
     * @param  array<string, mixed> $batch Stav dávky.
     * @return array<string, mixed>      Stav, další krok, celkový počet a zpoždění dalšího pokusu.
     */
    private function result(array $batch): array
    {
        return ['status' => $batch['status'], 'request_id' => $batch['request_id'], 'next_step' => (int)$batch['step_index'], 'total' => (int)$batch['total'], 'retry_after' => $this->batches->retryAfter($batch)];
    }
}
