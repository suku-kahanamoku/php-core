<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Orchesterace pozadostv o synchronizaci Etymologu a behu odpojenych workeru.
 *
 * Synchronizace je vzdy explicitni: spustit ji lze jen z administratorskeho
 * tlacitka (viz `start()`) nebo pres odpojeny CLI worker (`workQueued()`).
 * Autonomni pruch se nikdy nespusti samovolne.
 *
 * Stav a kurzor prace drzi `EtymologBatchRepository`, takze HTTP pozadavek
 * jen rychle vlozi frontu a vrati stav, zatimco tezkou práci delaji workery.
 */
final class EtymologBackgroundService
{
    /**
     * @param  EtymologBatchRepository $batches Repozitar stavu a fronty synchronizace.
     * @param  EtymologSyncService      $sync    Sluzby synchronizace jednotlivych zdrojů.
     * @param  \Closure                 $launch  Spouštěč workera; injektovaný, aby testy nikdy nespustily skutecny import.
     * @return void
     */
    public function __construct(private readonly EtymologBatchRepository $batches, private readonly EtymologSyncService $sync, private readonly \Closure $launch) {}

    /**
     * Vrátí aktuální stav synchronizace pro zobrazení v UI.
     *
     * @return array<string, mixed>|null Stav běhu, nebo null pokud žádný běh neexistuje.
     */
    public function status(): ?array { return $this->batches->status(); }

    /**
     * Vloží nový požadavek na synchronizaci a pokusí se spustit workera.
     *
     * Pokud již existuje rozpracovaný požadavek, vrací jeho stav s `accepted = false`
     * a nespustí další běh. Nezbyde-li spuštění udržitelné (např. je worker
     * vypnutý nebo chybně konfigurovaný), vrací 503 s důvodem místo tichého selhání.
     *
     * @param  int|null $actor ID uživatele, který požadavek spustil, nebo null pro automatický běh.
     * @return array<string, mixed> Stav požadovaného běhu včetně klíče `accepted`.
     * @throws EtymologException 503, pokud workera nelze spustit a chybu se nepodari zapsat.
     */
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

    /** Zastaví požadovaný běh po právě zpracovávané dávce. */
    public function stop(string $id): array
    {
        return $this->batches->stop($id);
    }

    /**
     * Zpracuje pouze požadavek vytvořený tlačítkem; nikdy nespustí autonomní průch.
     *
     * @return array<string, mixed> Stav po zpracování, nebo `{ status: 'idle' }` pokud není čekání.
     */
    public function workQueued(): array
    {
        $current = $this->batches->status();
        if (!$current || $current['status'] !== 'queued') { return ['status' => 'idle']; }
        return $this->work($current['request_id']);
    }

    /**
     * Projde vsechny zbyle dávky kazdeho vypršelého a povoleného zdroje.
     *
     * Stejny pruch pouziva cron i odpojeny CLI. Pri behu bez `requestId` se nejprve
     * zkontroluje, zda jiny worker prave nepracuje, a pri jeho preruseni se beh
     * ozaci jako neuspesny. Kazda dávka se zapisuje do databaze samostatne, takze
     * omezeni rychlosti od upstreamu ukonci jen dávku, ne cely pozadavek.
     *
     * @param  string|null $requestId ID pozadovaného behu, nebo null pro automaticky novy.
     * @return array<string, mixed>  Stav po dokonceni pruchu.
     * @throws \Throwable            Chyba pri praci s databazi se po prepisu stavu vyhodi.
     */
    public function work(?string $requestId = null): array
    {
        return $this->batches->lock('worker',         /**
         * Tělo práce běží pod výhradním zámkem workera v jedné transakci.
         *
         * @return array<string, mixed> Stav dávky a počet zpracovaných kroků.
         * @throws \Throwable          Chyba databáze se po přepisu stavu vyhodí.
         */
function () use ($requestId) {
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
            try {
                $batch = $this->batches->prepareSteps($requestId);
                while ($batch['status'] === 'running') {
                    $ids = $this->batches->progress($batch)['jobs'];
                    try {
                        // CLI persists each batch separately too. Rate limits stop this job;
                        // its durable retry time is respected by the next cron invocation.
                        $this->sync->run((int)$ids[$batch['completed']], fn (array $result) => $this->batches->finishStep($batch, $result, false));
                    } catch (SyncException) { /* Failure audited; continue with the next source. */ }
                    $batch = $this->batches->status();
                }
                if ($batch['status'] === 'stopping') {
                    $this->batches->update($requestId, ['status' => 'stopped', 'finished_at' => gmdate('Y-m-d H:i:s')]);
                }
            } catch (\Throwable $e) {
                $this->batches->update($requestId, ['status' => 'failed', 'error_code' => 'worker_failed', 'finished_at' => gmdate('Y-m-d H:i:s')]);
                throw $e;
            }
            return $this->batches->status();
        });
    }
}
