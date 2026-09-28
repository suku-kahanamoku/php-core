<?php
// Mocked HTTP and disposable DB only; no live synchronization.
use App\Modules\Etymolog\{EtymologRepository, EtymologSyncRepository, EtymologSyncService, EtymologExternalRepository, ProviderRegistry, SyncException};
use App\Modules\Etymolog\Providers\WikipediaNamesProvider;

$wiki = new WikipediaNamesProvider($fake);
$wikiRights = ['query' => ['rightsinfo' => ['url' => WikipediaNamesProvider::LICENSE_URL.'deed.cs']]];
$wikiMeta = static function (string $title, array $headings, int $revision = 11): array {
    return ['parse' => ['title' => $title, 'pageid' => $title === 'Anna' ? 101 : 100, 'revid' => $revision,
        'sections' => array_map(static fn ($head, $index) => ['line' => $head, 'index' => (string)($index + 1), 'anchor' => $head], $headings, array_keys($headings))]];
};
$wikiBody = static function (string $title, string $heading, int $revision = 11): array {
    $text = '<div class="mw-parser-output"><div class="mw-heading"><h2>'.$heading.'</h2><span class="mw-editsection">edit</span></div><table><tr><td><p>Infobox excluded</p></td></tr></table><p>Fixture paragraph with <b>original words</b> &amp; symbols.<sup>99</sup><script>evil()</script></p><ul><li>First fixture line<br>Second fixture line</li></ul><div class="navbox"><p>Navigation excluded</p></div></div>';
    return ['parse' => ['title' => $title, 'pageid' => $title === 'Anna' ? 101 : 100, 'revid' => $revision, 'text' => ['*' => $text]]];
};
$wikiQueue = static function (int $revision = 11) use ($jsonResponse, $wikiRights, $wikiMeta, $wikiBody): array {
    return array_map($jsonResponse, [$wikiRights, $wikiMeta('Svatá Anna', ['Život', 'Patronka'], $revision), $wikiBody('Svatá Anna', 'Život', $revision), $wikiBody('Svatá Anna', 'Patronka', $revision), $wikiMeta('Anna', ['Pranostiky'], $revision), $wikiBody('Anna', 'Pranostiky', $revision)]);
};
foreach ([['sk', 'culture', null, 1], ['cs', 'surname', null, 1], ['cs', 'culture', null, 4], ['cs', 'culture', '-1', 1], ['cs', 'culture', '12', 1], ['cs', 'etymologies', 'https://example.org', 1]] as $args) {
    $fake->responses = [];
    try { $wiki->batch(...$args); throw new LogicException('Expected invalid configuration'); }
    catch (SyncException $e) { check(in_array($e->reason, ['invalid_provider_configuration', 'invalid_provider_cursor'], true), 'Wikipedia rejects unsupported configuration before HTTP'); }
}
$fake->responses = [$jsonResponse(['query' => ['rightsinfo' => ['url' => 'https://example.org/no-reuse']]])];
try { $wiki->batch('cs', 'culture', null, 1); throw new LogicException('Expected license failure'); }
catch (SyncException $e) { check($e->reason === 'upstream_license_changed', 'Wikipedia license change fails closed'); }
$fake->responses = [$jsonResponse($wikiRights), $jsonResponse($wikiMeta('Svatá Anna', ['Renamed']))];
try { $wiki->batch('cs', 'culture', null, 1); throw new LogicException('Expected heading failure'); }
catch (SyncException $e) { check($e->reason === 'culture_section_changed', 'missing reviewed heading never falls back to another section'); }
$fake->responses = [$jsonResponse($wikiRights), $jsonResponse($wikiMeta('Wrong page', ['Život']))];
try { $wiki->batch('cs', 'culture', null, 1); throw new LogicException('Expected title failure'); }
catch (SyncException $e) { check($e->reason === 'invalid_culture_response', 'wrong article rejected'); }
$fake->responses = [$jsonResponse($wikiRights), $jsonResponse($wikiMeta('Svatá Anna', ['Život'])), $jsonResponse($wikiBody('Svatá Anna', 'Život', 12))];
try { $wiki->batch('cs', 'culture', null, 1); throw new LogicException('Expected revision failure'); }
catch (SyncException $e) { check($e->reason === 'invalid_culture_revision', 'mixed revisions rejected'); }
$fake->responses = [$jsonResponse($wikiRights), $jsonResponse($wikiMeta('Svatá Anna', ['Život'])), $jsonResponse($wikiBody('Svatá Anna', 'Wrong section'))];
try { $wiki->batch('cs', 'culture', null, 1); throw new LogicException('Expected body heading failure'); }
catch (SyncException $e) { check($e->reason === 'culture_section_changed', 'body must contain the selected section heading'); }

$wikiTenant = 'wikipedia-fixture';
$wikiJobs = new EtymologRepository($db, $wikiTenant, 'sync-jobs');
$wikiExternal = new EtymologExternalRepository($db, $wikiTenant);
$wikiSync = new EtymologSyncService($wikiJobs, new EtymologSyncRepository($db, $wikiTenant), new ProviderRegistry(['wikipedia-names' => $wiki]), external: $wikiExternal);
$wikiName = $db->insert('etymolog_name', ['franchise_code' => $wikiTenant, 'name' => 'Anna', 'kind' => 'given', 'language' => 'cs', 'published' => 1]);
$wikiJob = $db->insert('etymolog_sync_job', ['franchise_code' => $wikiTenant, 'title' => 'Fixture culture', 'provider' => 'wikipedia-names', 'language' => 'cs', 'kind' => 'culture', 'batch_size' => 3]);
$fake->responses = $wikiQueue();
$fake->responses[3] = $jsonResponse($wikiBody('Svatá Anna', 'Patronka', 12));
try { $wikiSync->run($wikiJob); throw new LogicException('Expected failed batch'); }
catch (SyncException $e) { check($e->reason === 'invalid_culture_revision' && $wikiJobs->findById($wikiJob)['cursor'] === null, 'failed cultural batch keeps cursor'); }
check(!$db->fetchOne('SELECT id FROM etymolog_external_record WHERE franchise_code=?', [$wikiTenant]), 'failed batch creates no partial narratives');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$wikiJob]);
$fake->responses = $wikiQueue(); $requestStart = count($fake->requests);
$wikiResult = $wikiSync->run($wikiJob);
check($wikiResult['processed'] === 3 && $wikiResult['cursor'] === '3', 'first cultural batch imports three Anna sections');
$wikiRecords = $db->fetchAll('SELECT * FROM etymolog_external_record WHERE franchise_code=? ORDER BY id', [$wikiTenant]);
check(count($wikiRecords) === 3 && count(array_unique(array_column($wikiRecords, 'name_id'))) === 1 && (int)$wikiRecords[0]['name_id'] === $wikiName, 'cultural records reuse existing name across providers');
$wikiEntryId = (int)$wikiRecords[0]['entry_id'];
$wikiEntry = $db->fetchOne('SELECT * FROM etymolog_entry WHERE id=?', [$wikiEntryId]);
$wikiText = "Fixture paragraph with original words & symbols.\n\nFirst fixture line\nSecond fixture line";
check($wikiEntry['body'] === $wikiText && $wikiEntry['type'] === 'legend' && (int)$wikiEntry['published'] === 0 && $wikiEntry['certainty'] === 'unverified', 'preserve text and line breaks, remove UI, keep religious legend an unverified draft');
$wikiCitation = $db->fetchOne('SELECT * FROM etymolog_citation WHERE entry_id=?', [$wikiEntryId]);
check($wikiCitation['quotation'] === $wikiText && $wikiCitation['url'] === $wikiEntry['source_url'] && str_contains($wikiCitation['locator'], 'Život'), 'verbatim quotation and permanent section citation retained');
check((new EtymologRepository($db, $wikiTenant, 'entries'))->hasWebQuotation($wikiEntryId, $wikiEntry['source_url'], $wikiText), 'import evidence satisfies cultural publication guard');
check(!(new EtymologRepository($db, 'other', 'entries'))->hasWebQuotation($wikiEntryId, $wikiEntry['source_url'], $wikiText), 'Wikipedia evidence remains tenant scoped');
$wikiRequests = array_slice($fake->requests, $requestStart);
parse_str(parse_url($wikiRequests[2]->url, PHP_URL_QUERY), $wikiParams);
check(($wikiParams['oldid'] ?? '') === '11' && ($wikiParams['section'] ?? '') === '1' && !isset($wikiParams['page']), 'body request pins exact revision and selected section');
check(count($wikiRequests) === 6 && array_reduce($wikiRequests, static fn ($ok, $r) => $ok && str_starts_with($r->url, 'https://cs.wikipedia.org/w/api.php?') && $r->redirectHosts === [], true), 'fixed endpoint, no redirects, reuse metadata within batch');

$db->update('etymolog_entry', ['title' => 'Editorial title', 'published' => 1], 'id=?', [$wikiEntryId]);
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?', [$wikiJob]);
$fake->responses = $wikiQueue(12); $wikiSync->run($wikiJob);
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_external_record WHERE franchise_code=?', [$wikiTenant])['n'] === 3, 'Wikipedia repeated synchronization is idempotent');
$refreshed = $db->fetchOne('SELECT * FROM etymolog_entry WHERE id=?', [$wikiEntryId]);
check($refreshed['title'] === 'Editorial title' && (int)$refreshed['published'] === 1 && $wikiExternal->imports('entries', $wikiEntryId)[0]['revision'] === '12', 'refresh preserves editorial data and publication while updating snapshot');
check($db->fetchOne('SELECT url FROM etymolog_citation WHERE entry_id=?', [$wikiEntryId])['url'] === $wikiCitation['url'], 'refresh does not repoint original text evidence to a newer revision');
$db->update('etymolog_entry', ['deleted' => 1], 'id=?', [$wikiEntryId]);
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?', [$wikiJob]);
$fake->responses = $wikiQueue(13); $wikiSync->run($wikiJob);
check((int)$db->fetchOne('SELECT deleted FROM etymolog_entry WHERE id=?', [$wikiEntryId])['deleted'] === 1 && $wikiExternal->imports('entries', $wikiEntryId)[0]['revision'] === '12', 'Wikipedia deletion tombstone prevents resurrection');

$fake->responses = [$jsonResponse($wikiRights), $jsonResponse($wikiMeta('Svatá Anna', ['Etymologie'])), $jsonResponse($wikiBody('Svatá Anna', 'Etymologie'))];
$origin = $wiki->batch('cs', 'etymologies', null, 1);
check($origin['items'][0]['name'] === 'Anna' && $origin['items'][0]['entry']['type'] === 'etymology' && $origin['cursor'] === '1', 'independent etymology task begins with Anna');
$fake->responses = [$jsonResponse($wikiRights), $jsonResponse($wikiMeta('Diana (mytologie)', ['Funkce'])), $jsonResponse($wikiBody('Diana (mytologie)', 'Funkce'))];
$myth = $wiki->batch('cs', 'culture', '11', 3);
check($myth['complete'] && $myth['cursor'] === null && $myth['items'][0]['entry']['type'] === 'mythology' && $myth['items'][0]['name'] === 'Diana', 'last cultural section retains mythology classification and completes pass');

foreach (['culture', 'etymologies'] as $kind) {
    $created = status(api('POST', 'etymolog/sync-jobs', ['title' => 'Wiki '.$kind, 'provider' => 'wikipedia-names', 'language' => 'cs', 'kind' => $kind, 'batch_size' => 3], $admin), 201, 'admin creates Wikipedia '.$kind.' task');
    status(api('DELETE', 'etymolog/sync-jobs/'.$created['id'], token: $admin), 200, 'delete Wikipedia test task');
}
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad Wikipedia', 'provider' => 'wikipedia-names', 'kind' => 'culture', 'language' => 'en', 'batch_size' => 1], $admin), 422, 'Wikipedia CRUD rejects unsupported language');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad Wikipedia', 'provider' => 'wikipedia-names', 'kind' => 'culture', 'batch_size' => 4], $admin), 422, 'Wikipedia CRUD bounds batch');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Forbidden Wikipedia', 'provider' => 'wikipedia-names', 'kind' => 'culture', 'batch_size' => 1], $editor), 403, 'editor cannot create Wikipedia tasks');
// Seed must also respect deleted configurations. Test clean insertion under a separate tenant.
$wikiSeed = file_get_contents($root.'/migrations/2026-09-28-etymolog-wikipedia-tenant.sql');
$db->getPdo()->exec($wikiSeed); $db->getPdo()->exec($wikiSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikipedia-names'")['n'] === 2 && (int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikipedia-names' AND deleted=0")['n'] === 0, 'Wikipedia seed respects deleted tasks and does not duplicate');
$wikiCleanSeed = str_replace("'etymolog'", "'wikipedia-seed-fixture'", $wikiSeed);
$db->getPdo()->exec($wikiCleanSeed); $db->getPdo()->exec($wikiCleanSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='wikipedia-seed-fixture' AND provider='wikipedia-names' AND enabled=1 AND next_run_at IS NULL AND last_status IS NULL")['n'] === 2, 'Wikipedia seed creates exactly two due tasks without running them');
