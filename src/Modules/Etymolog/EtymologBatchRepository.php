<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\BaseRepository;
use App\Modules\Database\Database;

/**
 * Repozitar stavu a fronty synchronizace Etymologu.
 *
 * Drzi jeden radek `etymolog_sync_batch` pro dávku a dva nezavislé MySQL zámky:
 * `request` (rychlé vlozeni pozadovanky) a `worker` (tehke zpracovani). Diky
 * tomu HTTP pozadavek nikdy neblokuje a dva workery nepracuji soucasne.
 *
 * Dávka stale starnou bezi s timeoutem a bez zivu heartbeatu je pri prvnim
 * dotazu na stav oznacena jako prerusená (`worker_interrupted`).
 */
final class EtymologBatchRepository extends BaseRepository
{
    /**
     * @param  Database $db            Databazove pripojeni.
     * @param  string   $tenant        Kod okurku; vsechny dotazy jsou jim omezene.
     * @param  int      $queuedTimeout Sekund, po kterych je radek ve stavu `queued` prohlasen za stale.
     * @param  int      $runningTimeout Sekund, po kterych je radek ve stavu `running` prohlasen za stale.
     * @return void
     */
    public function __construct(Database $db, string $tenant, private readonly int $queuedTimeout = 120, private readonly int $runningTimeout = 120)
    {
        parent::__construct($db, $tenant);
    }

    /**
     * Rozhodne, zda je radek dávky stary a je možné ho převést na `failed`.
     *
     * @param  array<string, mixed> $row Radek z `etymolog_sync_batch`.
     * @return bool                    true, pokud `heartbeat_at` (nebo `retry_at`) je starší než timeout stavu.
     */
    private function expired(array $row): bool
    {
        $timeout = $row['status'] === 'queued' ? $this->queuedTimeout : $this->runningTimeout;
        return max(strtotime($row['heartbeat_at'].' UTC'), $row['retry_at'] ? strtotime($row['retry_at'].' UTC') : 0) < time() - $timeout;
    }

    /**
     * Provede akci pod zámkem daného druhu (bez cekani).
     *
     * @param  string   $kind   Druh zámku: 'request' nebo 'worker'.
     * @param  callable $action Akce k provedení.
     * @return mixed           Návratová hodnota akce.
     * @throws EtymologException 409, pokud zámek drží jiná relace.
     */
    public function lock(string $kind, callable $action): mixed
    {
        $key = 'ety-'.$kind.':'.substr(hash('sha256', $this->_code), 0, 48);
        if ((int)($this->_db->fetchOne('SELECT GET_LOCK(?,0) acquired', [$key])['acquired'] ?? 0) !== 1) {
            throw new EtymologException('Synchronization is already running', 409);
        }
        try { return $action(); }
        finally { $this->_db->fetchOne('SELECT RELEASE_LOCK(?) released', [$key]); }
    }

    /**
     * Načte stav dávky pro daný okurk a případně označí přerušenou práci.
     *
     * Pokud je radek stale a pracovní zámek je volný, převede ho na `failed`
     * s kódem `worker_interrupted`. Císelné sloupce jsou přetypované a klíč
     * `franchise_code` je z výsledku odstraněn.
     *
     * @return array<string, mixed>|null Stav dávky včetně `step_index`, nebo null pokud žádný není.
     * @throws EtymologException       Chyba databáze se propaguje dál.
     */
    public function status(): ?array
    {
        $row = $this->_db->fetchOne('SELECT * FROM etymolog_sync_batch WHERE franchise_code=?', [$this->_code]) ?: null;
        if ($row && in_array($row['status'], ['queued', 'running'], true)) {
            if ($this->expired($row) && (int)($this->_db->fetchOne('SELECT IS_FREE_LOCK(?) available', ['ety-worker:'.substr(hash('sha256', $this->_code), 0, 48)])['available'] ?? 0) === 1) {
                try {
                    $this->lock('worker',                     /**
                     * Přepíše vypršelou dávku pod zámkem workera; změna se ukončí,
                     * pokud dávku mezitím převzal jiný proces.
                     *
                     * @return array<string, mixed>|null  Nový stav, nebo null pokud dávka mezitím doběhla.
                     * @throws EtymologException           409, pokud zámek drží jiný proces.
                     */
function () use (&$row) {
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
            $row['step_index'] = $this->progress($row)['step'];
        }
        return $row;
    }

    /**
     * Vloží novou žádost o synchronizaci, pokud žádná jiná nečeká nebo neběží.
     *
     * @param  int|null $actor ID uživatele, který žádost vytvořil, nebo null pro noční dávku.
     * @return array<string, mixed> Stav dávky s `accepted` (true = nová, false = už existuje).
     * @throws EtymologException   409, pokud souběžná žádost drží zámek.
     */
    public function enqueue(?int $actor): array
    {
        return $this->lock('request',         /**
         * Zařadí novou dávku pod zámkem žádostí; běžící dávka se nevytlačí.
         *
         * @return array<string, mixed> Stav dávky a příznak `accepted`.
         * @throws EtymologException   409, pokud souběžná žádost drží zámek.
         */
function () use ($actor) {
            $current = $this->status();
            if ($current && in_array($current['status'], ['queued', 'running'], true)) { return $current + ['accepted' => false]; }
            $id = bin2hex(random_bytes(16));
            $this->_db->query("INSERT INTO etymolog_sync_batch (franchise_code,request_id,status,requested_by,created_at,heartbeat_at) VALUES (?,?,'queued',?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id),status='queued',requested_by=VALUES(requested_by),total=0,completed=0,failed=0,processed=0,error_code=NULL,pending_jobs=NULL,retry_at=NULL,retry_count=0,created_at=UTC_TIMESTAMP(),heartbeat_at=UTC_TIMESTAMP(),finished_at=NULL", [$this->_code, $id, $actor]);
            return $this->status() + ['accepted' => true];
        });
    }

    /**
     * Označí čekající žádost jako neúspěšnou, pokud se nepodařilo spustit workera.
     *
     * Metoda je podmíněná na stavu `queued`, takže nesmí přepsat práci,
     * kterou mezitím převzal živý worker.
     *
     * @param  string $id     `request_id` dávky.
     * @param  string $reason Strojový kód důvodu (např. 'worker_process_disabled').
     * @return bool           true, pokud se stav změnil.
     */
    public function failQueuedLaunch(string $id, string $reason): bool
    {
        $stmt = $this->_db->query("UPDATE etymolog_sync_batch SET status='failed',error_code=?,finished_at=UTC_TIMESTAMP(),heartbeat_at=UTC_TIMESTAMP() WHERE franchise_code=? AND request_id=? AND status='queued'", [$reason, $this->_code, $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Zajistí, že se pro dané datum spustí nejvýše jedna dávka, a vrátí její stav.
     *
     * @param  string $date Datum ve formátu `Y-m-d`.
     * @return array{request_id: string, next_step: int} ID dávky a krok, od kterého pokračovat.
     * @throws \Throwable         Chyba databáze se po rollbacku znovu vyhodí.
     */
    public function nightly(string $date): array
    {
        $existing = $this->_db->fetchOne('SELECT request_id FROM etymolog_sync_schedule WHERE franchise_code=? AND scheduled_date=?', [$this->_code, $date]);
        if ($existing) { return ['request_id' => $existing['request_id'], 'next_step' => 0]; }
        $pdo = $this->_db->getPdo(); $pdo->beginTransaction();
        try {
            $request = $this->enqueue(null);
            $this->_db->insert('etymolog_sync_schedule', ['franchise_code' => $this->_code, 'scheduled_date' => $date, 'request_id' => $request['request_id']]);
            $pdo->commit();
            return ['request_id' => $request['request_id'], 'next_step' => $request['step_index']];
        } catch (\Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    }

    /**
     * Sestaví plán práce pro žádost: vyberte vypršelé zdroje a uloží jejich pořadí.
     *
     * Pokud není co dělat, dávka se rovnou uzavře jako `complete`.
     *
     * @param  string $id `request_id` dávky.
     * @return array<string, mixed>|null Stav dávky po naplánování.
     */
    public function prepareSteps(string $id): array
    {
        $bootstrap = $this->needsBootstrap();
        $ids = $this->dueIds($bootstrap);
        $state = $this->plannedState($ids, 0, $bootstrap);
        $this->update($id, ['pending_jobs' => json_encode($state, JSON_THROW_ON_ERROR), 'total' => count($ids), 'status' => $ids ? 'running' : 'complete', 'finished_at' => $ids ? null : gmdate('Y-m-d H:i:s')]);
        return $this->status();
    }

    /**
     * Jednou po nasazení přeskupe nedokončenou práci bez opakování již uložených kroků.
     *
     * Hotové dávky zůstanou v plánu beze změny, zbytek se přesmykuje a dávka se
     * případně uzavře.
     *
     * @param  array<string, mixed> $batch Aktuální stav dávky.
     * @return array<string, mixed>|null   Nový stav, nebo `$batch` pokud není co přesmykovat.
     */
    public function optimizePending(array $batch): array
    {
        $state = $this->progress($batch);
        if (($state['plan'] ?? null) === 2 || $batch['status'] !== 'running') { return $batch; }
        // The current retry count belongs to the old first job; preserve an active cooldown.
        $prefix = array_slice($state['jobs'], 0, $batch['completed']);
        $remaining = array_slice($state['jobs'], $batch['completed']);
        $rows = $this->jobRows();
        $completedKeys = [];
        foreach ($rows as $row) {
            if (in_array((int)$row['id'], $prefix, true)) { $completedKeys[$this->workKey($row)] = true; }
        }
        $rows = array_values(array_filter($rows, fn ($row) => in_array((int)$row['id'], $remaining, true) && !isset($completedKeys[$this->workKey($row)])));
        $ids = array_column($this->uniqueJobs($rows), 'id');
        $next = $this->plannedState(array_merge($prefix, array_map('intval', $ids)), $state['step']);
        $done = count($next['jobs']) === (int)$batch['completed'];
        $this->update($batch['request_id'], ['pending_jobs' => json_encode($next, JSON_THROW_ON_ERROR), 'total' => count($next['jobs']), 'retry_count' => 0,
            'status' => $done ? ($batch['failed'] ? 'partial' : 'complete') : 'running',
            'retry_at' => $done ? null : $batch['retry_at'], 'finished_at' => $done ? gmdate('Y-m-d H:i:s') : null]);
        return $this->status();
    }

    /**
     * Vytvoří serializovaný plán dávky z ID zdrojů a jejich priorit.
     *
     * @param  list<int> $ids      ID zdrojů v pořadí zpracování.
     * @param  int       $step     Aktuální krok.
     * @param  bool      $bootstrap true při plnění prázdného archivu.
     * @return array{jobs: list<int>, step: int, plan: int, priorities: array<int, int>} Stav k uložení do `pending_jobs`.
     */
    private function plannedState(array $ids, int $step, bool $bootstrap = false): array
    {
        $priorities = [];
        foreach ($this->jobRows() as $row) { $priorities[(int)$row['id']] = $this->priority($row['provider'], $bootstrap); }
        return ['jobs' => $ids, 'step' => $step, 'plan' => 2, 'priorities' => $priorities];
    }

    /**
     * Normalizuje uložený plán na tvar `{ jobs, step, ... }`.
     *
     * Staré rozpracované žádosti uchovávaly pouze seznam zdrojů a jejich krok
     * se rovnal počtu dokončených dávek.
     *
     * @param  array<string, mixed> $batch Aktuální stav dávky.
     * @return array<string, mixed>         Plán s kliči `jobs` a `step`.
     * @throws \JsonException               Pokud `pending_jobs` není platný JSON.
     */
    public function progress(array $batch): array
    {
        if ($batch['pending_jobs'] === null) { return ['jobs' => [], 'step' => (int)$batch['completed']]; }
        $state = json_decode($batch['pending_jobs'], true, 64, JSON_THROW_ON_ERROR);
        if (array_is_list($state)) { return ['jobs' => $state, 'step' => (int)$batch['completed']]; }
        return $state;
    }

    /**
     * Zapíše výsledek jedné dávky a posune kurzor plánu.
     *
     * Volá se uvnitř stejné transakce jako importovaný obsah, kurzor zdroje a
     * audit. Při omezení rychlosti od upstreamu se kurzor neposune, dokud dávka
     * neuspěje nebo nevyčerpá retry rozpočet. Úspěšné dávky se ve fázi
     * rotují, aby mytologie nečekala na celý etymologický slovník.
     *
     * @param  array<string, mixed> $batch        Stav dávky před zpracováním.
     * @param  array<string, mixed> $result       Výsledek dávky (`status`, `processed`, volitelně `error_code`, `retry_after`).
     * @param  bool                 $retryLimited true, pokud má být respektován retry limit.
     * @return void                              Vedlejší efekt: aktualizace řádku dávky.
     * @throws \JsonException                    Pokud `pending_jobs` není platný JSON.
     */
    public function finishStep(array $batch, array $result, bool $retryLimited = true): void
    {
        $error = $result['error_code'] ?? null;
        $retryable = in_array($error, ['upstream_rate_limited', 'worker_time_budget_exceeded'], true);
        $minimumDelay = $error === 'upstream_rate_limited' ? 300 : 60;
        $retryAt = $retryable ? gmdate('Y-m-d H:i:s', time() + max($minimumDelay, min(604800, (int)($result['retry_after'] ?? $minimumDelay)))) : null;
        if ($retryLimited && $retryable && (int)$batch['retry_count'] < 2) {
            // No advancement until this source batch succeeds or exhausts its retry budget.
            $this->update($batch['request_id'], ['retry_at' => $retryAt, 'retry_count' => (int)$batch['retry_count'] + 1]);
            return;
        }
        $state = $this->progress($batch);
        ++$state['step'];
        // Rotate unfinished text jobs within their phase so mythology does not wait
        // for the entire etymology dictionary. Later inventory/statistics stay behind.
        if ($result['status'] === 'success' && isset($state['priorities'])) {
            $index = (int)$batch['completed'];
            $current = $state['jobs'][$index];
            $priority = $state['priorities'][$current] ?? 1;
            $end = $index;
            while (isset($state['jobs'][$end + 1]) && ($state['priorities'][$state['jobs'][$end + 1]] ?? 1) === $priority) { ++$end; }
            if ($end > $index) {
                array_splice($state['jobs'], $index, 1);
                array_splice($state['jobs'], $end, 0, [$current]);
            }
        }
        $completed = (int)$batch['completed'] + ($result['status'] === 'success' ? 0 : 1);
        $failed = (int)$batch['failed'] + ($result['status'] === 'failed' ? 1 : 0);
        $done = $completed >= (int)$batch['total'];
        $this->update($batch['request_id'], ['completed' => $completed, 'failed' => $failed, 'retry_count' => 0, 'retry_at' => $done || !$retryLimited ? null : $retryAt,
            'pending_jobs' => json_encode($state, JSON_THROW_ON_ERROR),
            'processed' => (int)$batch['processed'] + $result['processed'],
            'status' => $done ? ($failed ? 'partial' : 'complete') : 'running',
            'finished_at' => $done ? gmdate('Y-m-d H:i:s') : null]);
    }

    /**
     * Vrátí počet sekund do dalšího pokusu po omezení rychlosti.
     *
     * @param  array<string, mixed> $batch Stav dávky.
     * @return int                   0, pokud není naplánován žádný pokus.
     */
    public function retryAfter(array $batch): int
    {
        return $batch['retry_at'] ? max(0, strtotime($batch['retry_at'].' UTC') - time()) : 0;
    }

    /**
     * Zjistí, zda je archiv jmen v okurku prázdný.
     *
     * @return bool true, pokud neexistuje ani jeden nezrušený záznam v `etymolog_name`.
     */
    private function needsBootstrap(): bool
    {
        return !$this->_db->fetchOne('SELECT id FROM etymolog_name WHERE franchise_code=? AND deleted=0 LIMIT 1', [$this->_code]);
    }

    /**
     * Vrátí pořadí zpracování zdroje (nižší dřív).
     *
     * @param  string $provider Klíč zdroje.
     * @param  bool   $bootstrap true při plnění prázdného archivu, kdy mají jména přednost.
     * @return int              Priorita zdroje.
     */
    private function priority(string $provider, bool $bootstrap = false): int
    {
        // An empty archive needs real source names before DB-driven text discovery.
        if ($bootstrap && in_array($provider, ['czech-namedays','csu-baby-names'], true)) { return -2; }
        if ($bootstrap && $provider === 'wikidata') { return -1; }
        return match ($provider) {
            'wikipedia-names', 'wiktionary', 'wiktionary-cs', 'wiktionary-fr', 'wikisource', 'erben-folklore' => 0,
            'wikidata', 'czech-namedays' => 2,
            'poland-pesel', 'csu-baby-names' => 3,
            default => 1,
        };
    }

    /**
     * Načte všechny povolené a nezrušené zdroje pro okurk.
     *
     * @return list<array<string, mixed>> Řádky `etymolog_sync_job` seřazené podle ID.
     */
    private function jobRows(): array
    {
        return $this->_db->fetchAll('SELECT id,provider,language,kind,next_run_at,created_at FROM etymolog_sync_job WHERE franchise_code=? AND deleted=0 AND enabled=1 ORDER BY id', [$this->_code]);
    }

    /**
     * Vrátí klíč, podle kterého se zdroje slučují (aliasy slovníků se neduplikují).
     *
     * @param  array<string, mixed> $job Řádek `etymolog_sync_job`.
     * @return string                Klíč práce.
     */
    private function workKey(array $job): string
    {
        if (in_array($job['provider'], ['wiktionary','wiktionary-cs','wiktionary-fr'], true)) {
            return $job['provider'].':'.$job['language'].':'.preg_replace('/_priority$/D', '', $job['kind']);
        }
        return 'job:'.$job['id'];
    }

    /**
     * Slučí aliasy slovníků a seřadí zdroje podle priority.
     *
     * Upřednostní se běžný slovník; samostatně zapnutý starší alias však funguje dál.
     *
     * @param  list<array<string, mixed>> $rows      Řádky zdrojů k deduplikaci.
     * @param  bool                       $bootstrap true při plnění prázdného archivu.
     * @return list<array<string, mixed>>            Unikátní zdroje v pořadí zpracování.
     */
    private function uniqueJobs(array $rows, bool $bootstrap = false): array
    {
        $selected = [];
        foreach ($rows as $row) {
            $key = $this->workKey($row);
            if (!isset($selected[$key]) || (str_ends_with($selected[$key]['kind'], '_priority') && !str_ends_with($row['kind'], '_priority'))) { $selected[$key] = $row; }
        }
        $rows = array_values($selected);
        usort($rows, fn ($a, $b) => ($this->priority($a['provider'], $bootstrap) <=> $this->priority($b['provider'], $bootstrap))
            ?: strcmp($a['next_run_at'] ?? $a['created_at'], $b['next_run_at'] ?? $b['created_at']) ?: ((int)$a['id'] <=> (int)$b['id']));
        return $rows;
    }

    /**
     * Vrátí ID zdrojů, jejichž obnovovací interval uplynul.
     *
     * Aliasy se deduplikují ještě před kontrolou vypršení, aby nemohly obejít
     * interval běžného zdroje. Nastavení, kurzory ani importovaná data se nemažou.
     *
     * @param  bool|null $bootstrap Přepíše automatické zjištění prázdného archivu.
     * @return list<int>            ID zdrojů k synchronizaci.
     */
    public function dueIds(?bool $bootstrap = null): array
    {
        // Deduplicate BEFORE checking due times, so an alias cannot bypass the regular
        // job's refresh interval. Settings, cursors and imported data are not deleted.
        $rows = array_filter($this->uniqueJobs($this->jobRows(), $bootstrap ?? $this->needsBootstrap()), static fn ($row) => $row['next_run_at'] === null || strtotime($row['next_run_at'].' UTC') <= time());
        return array_map(static fn ($row) => (int)$row['id'], array_values($rows));
    }

    /**
     * Aktualizuje dávku a současně osvěží heartbeat.
     *
     * @param  string                 $id   `request_id` dávky.
     * @param  array<string, mixed>   $data Sloupce k aktualizaci.
     * @return void                          Vedlejší efekt: změna databáze.
     */
    public function update(string $id, array $data): void
    {
        $this->_db->update('etymolog_sync_batch', $data + ['heartbeat_at' => gmdate('Y-m-d H:i:s')], 'franchise_code=? AND request_id=?', [$this->_code, $id]);
    }
}
