<?php
// Disposable DB and mocked providers only. Never starts the production CLI or performs HTTP.
use App\Modules\Etymolog\{EtymologBatchRepository, EtymologBackgroundService, EtymologRepository, EtymologSyncRepository, EtymologSyncService, ProviderRegistry, SyncException, EtymologException};

status(api('POST', 'etymolog/sync/start', [], $editor), 403, 'editor cannot start background worker');
status(api('GET', 'etymolog/sync/status', token: $editor), 403, 'editor cannot inspect background worker');
status(api('POST', 'etymolog/sync/start', []), 401, 'anonymous cannot launch worker');
status(api('POST', 'etymolog/sync/start', ['tenant' => 'other'], $admin), 422, 'start rejects caller tenant or worker options');
status(api('GET', 'etymolog/sync/status', token: $admin), 200, 'admin can read idle batch status');

foreach (['cs', 'fr'] as $edition) {
    $provider = new App\Modules\Etymolog\Providers\WiktionaryProvider($fake, $edition);
    $lang = $edition === 'cs' ? 'čeština' : 'Tchèque';
    $ety = $edition === 'cs' ? 'etymologie' : 'Étymologie';
    $sense = $edition === 'cs' ? 'význam' : 'Nom de famille';
    $category = $edition === 'cs' ? 'Česká_příjmení' : 'Noms_de_famille_en_tchèque';
    $html = '<div class="mw-parser-output"><section><h2>'.$lang.'</h2><section><h3>'.$ety.'</h3><p>Source fixture.</p><h3>'.$sense.'</h3><ol><li>příjmení</li></ol></section></section><h2>English</h2><h3>Etymology</h3><p>Wrong language.</p></div>';
    if ($edition === 'fr') { $html = str_replace('<p>Source fixture.</p>', '<dl><dd>Source fixture.</dd></dl><h5>Notes</h5><dl><dd>Other section.</dd></dl>', $html); }
    $page = ['parse' => ['pageid' => 555, 'title' => 'Novotný', 'revid' => 777, 'categories' => [['*' => $category]], 'text' => ['*' => $html]]];
    $priority = ['query' => ['pages' => ['555' => ['pageid' => 555, 'ns' => 0, 'title' => 'Novotný']]]];
    $discovery = ['query' => ['categorymembers' => [['pageid' => 555, 'ns' => 0, 'title' => 'Novotný']]]];
    $fake->responses = [$jsonResponse($rights), $jsonResponse($priority), $jsonResponse($page)];
    $batch = $provider->batch('cs', 'surname_priority', null, 3);
    check($batch['scanned'] === 3 && $batch['cursor'] === '3' && $batch['items'][0]['entry']['language'] === $edition, $edition.' priority advances fixed discovery and retains source language');
    check($batch['items'][0]['entry']['body'] === 'Source fixture.' && str_contains($batch['items'][0]['source_url'], $edition.'.wiktionary.org'), $edition.' nested markup extracts only correct etymology');
    $priorityItem = $batch['items'][0];
    $fake->responses = [$jsonResponse($rights), $jsonResponse($discovery), $jsonResponse($page)];
    $categoryItem = $provider->batch('cs', 'surname', null, 3)['items'][0];
    check($categoryItem['external_id'] === $priorityItem['external_id'], $edition.' priority and category share identity');
    $jobs->exclusive(fn () => $jobs->transaction(function () use ($external, $edition, $priorityItem, $categoryItem) {
        $external->import('wiktionary-'.$edition, $priorityItem);
        $external->import('wiktionary-'.$edition, $categoryItem);
    }));
    check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_external_record WHERE provider=? AND external_id=?', ['wiktionary-'.$edition, $priorityItem['external_id']])['n'] === 1, $edition.' overlapping discovery does not duplicate entries');
    $page['parse']['text']['*'] = str_replace($ety, 'Other section', $html);
    $fake->responses = [$jsonResponse($rights), $jsonResponse($priority), $jsonResponse($page)];
    check($provider->batch('cs', 'surname_priority', null, 3)['items'] === [], $edition.' absent etymology is skipped');
    $fake->responses = [$jsonResponse($rights), $jsonResponse(['query' => ['pages' => ['-1' => ['title' => 'Missing', 'missing' => '']]]])];
    $last = $provider->batch('cs', 'surname_priority', '12', 3);
    check($last['complete'] && $last['items'] === [], $edition.' missing priority pages still finish the pass');
}
// Do not disturb other fixtures: independent tenant, one due job, one failure, future and disabled jobs.
$batchTenant = 'background-fixture';
$batchJobs = new EtymologRepository($db, $batchTenant, 'sync-jobs');
$ids = [];
foreach (['ok', 'bad', 'future', 'disabled'] as $tag) {
    $ids[$tag] = $db->insert('etymolog_sync_job', ['franchise_code' => $batchTenant, 'title' => $tag, 'provider' => $tag, 'enabled' => $tag === 'disabled' ? 0 : 1, 'next_run_at' => $tag === 'future' ? '2099-01-01 00:00:00' : null]);
}
$okProvider = new class implements App\Modules\Etymolog\Contracts\BatchProvider {
    public int $calls = 0;
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array { ++$this->calls; return ['items' => [], 'complete' => true, 'cursor' => null]; }
};
$badProvider = new class implements App\Modules\Etymolog\Contracts\BatchProvider {
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array { throw new SyncException('fixture_failure', 900); }
};
$batchSync = new EtymologSyncService($batchJobs, new EtymologSyncRepository($db, $batchTenant), new ProviderRegistry(['ok' => $okProvider, 'bad' => $badProvider]));
$batches = new EtymologBatchRepository($db, $batchTenant);
$launched = [];
$background = new EtymologBackgroundService($batches, $batchSync, static function ($id) use (&$launched) { $launched[] = $id; });
$first = $background->start(null);
$second = $background->start(null);
check($first['accepted'] && !$second['accepted'] && count($launched) === 1 && $okProvider->calls === 0, 'POST queues once without importing in request');
check((new EtymologBatchRepository($db, 'other'))->status() === null, 'batch status tenant isolation');
check($background->work(str_repeat('f', 32))['status'] === 'idle', 'stale worker request cannot claim another batch');
$completed = $background->work($first['request_id']);
check($completed['status'] === 'partial' && $completed['total'] === 2 && $completed['completed'] === 2 && $completed['failed'] === 1 && $okProvider->calls === 1, 'background visits every due enabled job and continues after failure');
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_sync_run WHERE franchise_code=?', [$batchTenant])['n'] === 2, 'each eligible job has normal cron audit');
check($background->work($first['request_id'])['status'] === 'idle', 'completed worker request cannot replay');
$again = $background->work();
check($again['status'] === 'complete' && $again['total'] === 0 && $okProvider->calls === 1, 'cron uses same runner and honors retry and interval');
$broken = new EtymologBackgroundService($batches, $batchSync, static function () { throw new RuntimeException('fixture'); });
try { $broken->start(null); throw new LogicException('Expected launch failure'); }
catch (EtymologException $e) { check($e->status === 503 && $batches->status()['error_code'] === 'worker_launch_failed', 'failed launcher remains visible and retryable'); }
$newSeed = file_get_contents($root.'/migrations/2026-09-28-etymolog-dictionaries-tenant.sql');
$db->getPdo()->exec($newSeed); $db->getPdo()->exec($newSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider IN ('wiktionary-cs','wiktionary-fr')")['n'] === 8, 'new dictionary rules seed idempotently');

$liveLock = 'ety-worker:'.substr(hash('sha256', $batchTenant), 0, 48);
$separate = new PDO($dsn, 'root', '');
$stmt = $separate->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$liveLock]);
try {
    try { $background->work(); throw new LogicException('Expected locked worker'); }
    catch (EtymologException $e) { check($e->status === 409, 'separate process cannot overlap tenant worker'); }
} finally { $stmt = $separate->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$liveLock]); }
$pending = $batches->enqueue(null);
$db->query("UPDATE etymolog_sync_batch SET heartbeat_at='2000-01-01 00:00:00' WHERE franchise_code=?", [$batchTenant]);
check($batches->status()['error_code'] === 'worker_interrupted', 'lost queued process becomes visibly failed');
$pending = $batches->enqueue(null);
$batches->update($pending['request_id'], ['status' => 'running']);
check($background->work()['status'] === 'complete', 'cron recovers interrupted worker after connection lock was released');
