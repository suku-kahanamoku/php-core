<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Synchronizace etymologických zdrojů: stahuje dávky a ukládá je do správného repozitáře.
 *
 * Každá dávka je ohraničená a její kurzor se ukládá spolu s importovanými daty
 * v jedné transakci. Celý průchod běží pod výhradním zámkem okurku, takže dva
 * workery nikdy nezpracovávají stejný zdroj současně.
 *
 * Volitelné repozitáře (`$stories`, `$external`, `$calendar`) jsou potřeba jen
 * pro zdroje, které do nich zapisují; chybějící se pozná podle kódu chyby.
 */
final class EtymologSyncService
{
    /**
     * @param  EtymologRepository          $jobs      Repozitar s výhradním zámkem a transakcí.
     * @param  EtymologSyncRepository      $sync      Běhy, kurzory a importní záznamy.
     * @param  ProviderRegistry            $providers Registry zdrojů.
     * @param  EtymologStoryRepository|null $stories  Repo pro příběhy (Wikisource, Erben).
     * @param  EtymologExternalRepository|null $external Repo pro externí výskyty jmen.
     * @param  EtymologCalendarRepository|null $calendar Repo pro kalendářní dny.
     * @return void
     */
    public function __construct(private readonly EtymologRepository $jobs, private readonly EtymologSyncRepository $sync, private readonly ProviderRegistry $providers, private readonly ?EtymologStoryRepository $stories = null, private readonly ?EtymologExternalRepository $external = null, private readonly ?EtymologCalendarRepository $calendar = null) {}

    /**
     * Zpracuje vybraný vypršelý zdroj až do konce (pouze CLI; HTTP worker používá jednotlivé kroky).
     *
     * @param  int $jobId ID zdroje.
     * @return array<string, mixed>  Souhrn poslední dávky s celkovými `processed`, `scanned` a `batches`.
     * @throws SyncException         Chyba zdroje se po zapsání stavu znovu vyhodí.
     */
    public function runPass(int $jobId): array
    {
        $processed = 0; $scanned = 0; $batches = 0;
        do {
            $result = $this->run($jobId);
            $processed += $result['processed'];
            $scanned += $result['scanned'] ?? 0;
            if ($result['status'] !== 'idle') { ++$batches; }
        } while ($result['status'] === 'success');
        return array_replace($result, ['processed' => $processed, 'scanned' => $scanned, 'batches' => $batches]);
    }

    /**
     * Zpracuje jednu ohraničenou dávku zdroje; kurzor se ukládá s celou dávkou.
     *
     * Neúspěch se zapíše do běhu a zdroj naplánuje na pozdější termín, poté se
     * výjimka vyhodí znovu, aby dávka mohla pokračovat v dalším průchodu.
     *
     * @param  int|null         $jobId      Konkrétní zdroj, nebo null pro nejbližší vypršelý.
     * @param  \Closure|null    $onFinished Callback s výsledkem; volá se ve stejné transakci jako zápis.
     * @return array<string, mixed>         Výsledek dávky (`status`, `processed`, `scanned`, `cursor`).
     * @throws SyncException                'provider_cursor_stalled' při zaseknutém kurzoru, jinak důvod chyby.
     */
    public function run(?int $jobId = null, ?\Closure $onFinished = null): array
    {
        /**
         * Celý běh úlohy pod výhradním zámkem okurku.
         *
         * @return array<string, mixed> Výsledek běhu: stav, počet zpracovaných záznamů a případná chyba.
         * @throws \Throwable          Chyba se zapíše do běhu a převede na `SyncException`.
         */
        return $this->jobs->exclusive(
            /**
             * Tělo běhu úlohy pod výhradním zámkem okurku.
             *
             * @return array<string, mixed> Výsledek běhu.
             * @throws \Throwable          Chyba se zapíše do běhu a převede na `SyncException`.
             */
            function () use ($jobId, $onFinished) {
            $job = $this->sync->nextJob($jobId);
            if (!$job) {
                $result = ['status' => 'idle', 'processed' => 0];
                if ($onFinished) { $onFinished($result); }
                return $result;
            }
            $runId = $this->sync->start((int)$job['id']);
            try {
                $batch = $this->providers->get($job['provider'])->batch($job['language'], $job['kind'], $job['cursor'], (int)$job['batch_size']);
                if (!$batch['complete'] && (!is_string($batch['cursor'] ?? null) || $batch['cursor'] === '' || $batch['cursor'] === $job['cursor'])) {
                    throw new SyncException('provider_cursor_stalled');
                }
                /**
                 * Uložení výsledku dávky a stavu úlohy v jedné transakci.
                 *
                 * @return array<string, mixed> Výsledek uložený do běhu.
                 * @throws \Throwable          Chyba databáze se po přepisu stavu vyhodí.
                 */
                return $this->jobs->transaction(
                    /**
                     * Uložení výsledku dávky a stavu úlohy v jedné transakci.
                     *
                     * @return array<string, mixed> Výsledek uložený do běhu.
                     * @throws \Throwable          Chyba databáze se po přepisu stavu vyhodí.
                     */
                    function () use ($job, $runId, $batch, $onFinished) {
                    if ($job['provider'] === 'czech-namedays') {
                        ($this->calendar ?? throw new SyncException('calendar_repository_missing'))->retireLegacyCalendar();
                    }
                    foreach ($batch['items'] as $item) {
                        if (in_array($job['provider'], ['wikisource', 'erben-folklore'], true)) {
                            $story = ($this->stories ?? throw new SyncException('story_repository_missing'))->import($item, $job['provider']);
                            if ($job['provider'] === 'erben-folklore' && $story !== null) {($this->calendar ?? throw new SyncException('calendar_repository_missing'))->attachFolklore($item, $story);}
                        } elseif ($job['provider'] === 'czech-namedays') {
                            ($this->calendar ?? throw new SyncException('calendar_repository_missing'))->import($item);
                        } elseif (in_array($job['provider'], ['wiktionary', 'wiktionary-cs', 'wiktionary-fr', 'wikipedia-names', 'poland-pesel', 'csu-baby-names'], true)) {
                            ($this->external ?? throw new SyncException('external_repository_missing'))->import($job['provider'], $item);
                        } else {
                            $this->sync->import($job, $item);
                        }
                    }
                    $status = $batch['complete'] ? 'complete' : 'success';
                    $cursor = $batch['complete'] ? null : $batch['cursor'];
                    $count = count($batch['items']);
                    $this->sync->finish($job, $runId, $status, $count, $cursor);
                    $result = ['run_id' => $runId, 'job_id' => (int)$job['id'], 'status' => $status, 'processed' => $count, 'scanned' => $batch['scanned'] ?? $count, 'cursor' => $cursor];
                    if ($onFinished) { $onFinished($result); }
                    return $result;
                });
            } catch (\Throwable $e) {
                $reason = $e instanceof SyncException ? $e->reason : 'sync_failed';
                $delay = $e instanceof SyncException ? $e->retryAfter : 300;
                /**
                 * Zapíše stav 'failed' a naplánuje další pokus podle doporučeného odstupu.
                 *
                 * @return void      Bez návratu; změna se provede v transakci.
                 * @throws \Throwable Chyba databáze se propadne dál.
                 */
                $this->jobs->transaction(
                    /**
                     * Zapíše stav 'failed' a naplánuje další pokus podle doporučeného odstupu.
                     *
                     * @return void      Bez návratu; změna se provede v transakci.
                     * @throws \Throwable Chyba databáze se propadne dál.
                     */
                    function () use ($job, $runId, $reason, $delay, $onFinished) {
                    $this->sync->finish($job, $runId, 'failed', 0, $job['cursor'], $reason, $delay);
                    if ($onFinished) { $onFinished(['status' => 'failed', 'processed' => 0, 'error_code' => $reason, 'retry_after' => $delay]); }
                });
                throw new SyncException($reason, $delay);
            }
        });
    }
}
