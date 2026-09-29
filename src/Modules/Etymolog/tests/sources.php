<?php
// Runs only within the isolated integration harness.
$external = new App\Modules\Etymolog\EtymologExternalRepository($db, 'etymolog');
$wikt = new App\Modules\Etymolog\Providers\WiktionaryProvider($fake);
$pesel = new App\Modules\Etymolog\Providers\PolandPeselProvider($fake);
$externalService = new App\Modules\Etymolog\EtymologSyncService($jobs, $sync, new App\Modules\Etymolog\ProviderRegistry(['wiktionary' => $wikt, 'poland-pesel' => $pesel]), $storyRepo, $external);
$wiktJob = status(api('POST', 'etymolog/sync-jobs', ['title' => 'Etymologies', 'provider' => 'wiktionary', 'kind' => 'surname', 'batch_size' => 1], $admin), 201, 'create etymology job');
$wiktJobId = (int)$wiktJob['id'];
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Too large', 'provider' => 'wiktionary', 'batch_size' => 4], $admin), 422, 'bound dictionary request count');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Too large', 'batch_size' => 51], $admin), 422, 'keep Wikidata batch bound');
$rights = ['query' => ['rightsinfo' => ['url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.en', 'text' => 'CC BY-SA 4.0']]];
$discoveryWikt = ['continue' => ['cmcontinue' => 'page|TEST|123'], 'query' => ['categorymembers' => [['pageid' => 123, 'ns' => 0, 'title' => 'Testovník']]]];
$htmlWikt = '<div class="mw-parser-output"><div class="mw-heading"><h2>Czech</h2></div><div class="mw-heading"><h3>Etymology 1</h3></div><p>Dictionary test etymology.<sup>1</sup><script>evil()</script></p><h4>Proper noun</h4><ol><li>a male surname</li></ol><h3>Etymology 2</h3><p>Wrong homonym.</p><h4>Noun</h4><ol><li>an object</li></ol><h2>German</h2><h3>Etymology</h3><p>Wrong language.</p><h3>Proper noun</h3><ol><li>a surname</li></ol></div>';
$parsedWikt = ['parse' => ['pageid' => 123, 'title' => 'Testovník', 'revid' => 11, 'categories' => [['*' => 'Czech_surnames']], 'text' => ['*' => $htmlWikt]]];
$fake->responses = [$jsonResponse($rights), $jsonResponse($discoveryWikt), $jsonResponse($parsedWikt)];
$result = $externalService->run($wiktJobId);
check($result['processed'] === 1 && strlen($result['cursor']) > 32, 'dictionary continuation persisted without truncation');
$record = $db->fetchOne("SELECT * FROM etymolog_external_record WHERE provider='wiktionary' AND franchise_code='etymolog'");
$dictEntryId = (int)$record['entry_id']; $dictNameId = (int)$record['name_id'];
$dictEntry = status(api('GET', 'etymolog/entries/'.$dictEntryId, token: $editor), 200, 'dictionary entry API');
check($dictEntry['body'] === 'Dictionary test etymology.' && $dictEntry['language'] === 'en' && $dictEntry['published'] === 0 && $dictEntry['certainty'] === 'unverified', 'correct homonym and language only, English draft text');
$dictName = status(api('GET', 'etymolog/names/'.$dictNameId, token: $editor), 200, 'dictionary name API');
check($dictName['language'] === 'cs' && $dictName['country_code'] === null, 'name language independent of English source text');
$records = status(api('GET', 'etymolog/entries/'.$dictEntryId.'/imports', token: $editor), 200, 'etymology provenance endpoint');
check($records[0]['license'] === 'CC-BY-SA-4.0' && str_contains($records[0]['attribution'], 'action=history') && isset($records[0]['payload']['changes']), 'dictionary attribution history license and changes retained');
status(api('GET', 'etymolog/names/'.$dictNameId.'/external-records', token: $editor), 200, 'name external snapshots endpoint');
status(api('GET', 'etymolog/names/'.$dictNameId.'/external-records', token: $other, host: 'other.test'), 404, 'foreign external name snapshots hidden');
status(api('GET', 'etymolog/entries/'.$dictEntryId.'/imports'), 401, 'anonymous dictionary provenance denied');
status(api('PATCH', 'etymolog/entries/'.$dictEntryId, ['body' => 'Manual etymology'], $editor), 200, 'edit dictionary import');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?', [$wiktJobId]);
$parsedWikt['parse']['revid'] = 12;
$fake->responses = [$jsonResponse($rights), $jsonResponse($discoveryWikt), $jsonResponse($parsedWikt)];
$externalService->run($wiktJobId);
check($db->fetchOne('SELECT body FROM etymolog_entry WHERE id=?', [$dictEntryId])['body'] === 'Manual etymology' && $external->imports('entries', $dictEntryId)[0]['revision'] === '12', 'dictionary refresh preserves manual text and updates snapshot');
check(count($external->imports('entries', $dictEntryId)) === 1, 'dictionary import idempotent');
$fake->responses = [$jsonResponse(['query' => ['rightsinfo' => ['url' => 'https://example.org/no-reuse']]])];
try {$wikt->batch('cs', 'surname', null, 1);throw new LogicException('Expected license failure');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'upstream_license_changed', 'dictionary license change fails closed');}
$noEty = $parsedWikt; $noEty['parse']['text']['*'] = str_replace('Etymology', 'Other', $htmlWikt);
$fake->responses = [$jsonResponse($rights), $jsonResponse($discoveryWikt), $jsonResponse($noEty)];
check($wikt->batch('cs', 'surname', null, 1)['items'] === [], 'no etymology never fabricates explanation');
$fake->responses = [$jsonResponse($rights), $jsonResponse(['query' => ['categorymembers' => []]])];
$b = $wikt->batch('cs', 'given', null, 1);
check(!$b['complete'] && json_decode($b['cursor'], true)['category'] === 1, 'given name discovery advances through gender categories');

$peselJob = status(api('POST', 'etymolog/sync-jobs', ['title' => 'PESEL', 'provider' => 'poland-pesel', 'language' => 'pl', 'kind' => 'surname_male', 'batch_size' => 1], $admin), 201, 'create statistics job');
$peselJobId = (int)$peselJob['id'];
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad', 'provider' => 'poland-pesel', 'kind' => 'surname_male', 'batch_size' => 1], $admin), 422, 'statistics job requires Polish dataset');
$dataset = ['data' => ['id' => '1681', 'attributes' => ['license_name' => 'CC0 1.0', 'current_condition_descriptions' => [], 'license_condition_original' => null]]];
$resMeta = ['id' => '111', 'relationships' => ['dataset' => ['data' => ['id' => '1681']]], 'attributes' => ['title' => 'Nazwiska męskie - stan na 2026-01-20', 'data_date' => '2026-01-20', 'contains_protected_data' => false, 'csv_file_url' => 'https://api.dane.gov.pl/media/resources/20260128/names.csv']];
$listMeta = ['data' => [$resMeta]];
$csvStats = "Nazwisko aktualne,Liczba\r\nNOWAK,98387\r\nKOWALSKI,66589\r\n";
$fake->responses = [$jsonResponse($dataset), $jsonResponse($listMeta), $jsonResponse(['data' => $resMeta]), new App\Modules\Http\HttpResponse(200, $csvStats)];
$result = $externalService->run($peselJobId);
$statsCursor = $result['cursor'];
check($result['processed'] === 1 && json_decode($statsCursor, true)['offset'] === 1, 'CSV cursor pins resource offset and hash');
$statsRecord = $db->fetchOne("SELECT * FROM etymolog_external_record WHERE provider='poland-pesel' AND franchise_code='etymolog'");
$occId = (int)$statsRecord['occurrence_id'];
$occ = status(api('GET', 'etymolog/occurrences/'.$occId, token: $editor), 200, 'statistics occurrence API');
check($occ['count'] === 98387 && $occ['sex'] === 'male' && $occ['observed_on'] === '2026-01-20' && $occ['measure'] === 'living_persons', 'preserve date population sex and count');
$statName = $db->fetchOne('SELECT language,country_code FROM etymolog_name WHERE id=?', [$statsRecord['name_id']]);
check($statName['language'] === null && $statName['country_code'] === 'PL', 'national statistics do not infer language/ethnicity');
status(api('GET', 'etymolog/occurrences/'.$occId.'/imports', token: $editor), 200, 'occurrence provenance API');
status(api('GET', 'etymolog/occurrences/'.$occId.'/imports', token: $other, host: 'other.test'), 404, 'foreign statistic provenance hidden');
status(api('PATCH', 'etymolog/occurrences/'.$occId, ['observed_on' => '2025-12-31'], $editor), 422, 'date and year cannot contradict');
status(api('PATCH', 'etymolog/occurrences/'.$occId, ['observed_on' => '2026-02-30'], $editor), 422, 'invalid calendar date rejected');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$peselJobId]);
$fake->responses = [$jsonResponse($dataset), $jsonResponse(['data' => $resMeta]), new App\Modules\Http\HttpResponse(200, $csvStats)];
$result = $externalService->run($peselJobId);
check($result['status'] === 'complete' && $result['cursor'] === null, 'CSV reaches true end rather than tabular API window');
check((int)$db->fetchOne("SELECT COUNT(DISTINCT source_id) n FROM etymolog_external_record WHERE provider='poland-pesel'")['n'] === 1, 'statistical rows share stable source');
$fake->responses = [$jsonResponse($dataset), $jsonResponse(['data' => $resMeta]), new App\Modules\Http\HttpResponse(200, str_replace('98387', '99999', $csvStats))];
try {$pesel->batch('pl', 'surname_male', $statsCursor, 1);throw new LogicException('Expected changed CSV failure');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'statistics_snapshot_changed', 'changing CSV during a pass never mixes snapshots');}
$badRes = $resMeta; $badRes['attributes']['csv_file_url'] = 'http://127.0.0.1/private.csv';
$fake->responses = [$jsonResponse($dataset), $jsonResponse(['data' => $badRes])];
$countRequests = count($fake->requests);
try {$pesel->batch('pl', 'surname_male', $statsCursor, 1);throw new LogicException('Expected SSRF rejection');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'statistics_download_url_not_allowed' && count($fake->requests) === $countRequests + 2, 'untrusted CSV URL never fetched');}
$badLicense = $dataset; $badLicense['data']['attributes']['license_condition_custom_description'] = 'No redistribution';
$fake->responses = [$jsonResponse($badLicense)];
try {$pesel->batch('pl', 'surname_male', null, 1);throw new LogicException('Expected license rejection');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'upstream_license_changed', 'custom restrictions block statistics import');}
$fake->responses = [$jsonResponse($dataset), $jsonResponse($listMeta), $jsonResponse(['data' => $resMeta]), new App\Modules\Http\HttpResponse(200, "Name,Count\nN,2\n")];
try {$pesel->batch('pl', 'surname_male', null, 1);throw new LogicException('Expected schema rejection');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'statistics_schema_changed', 'CSV schema change fails closed');}
status(api('PATCH', 'etymolog/occurrences/'.$occId, ['count' => 10], $editor), 200, 'editor can annotate statistics');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$peselJobId]);
$fake->responses = [$jsonResponse($dataset), $jsonResponse($listMeta), $jsonResponse(['data' => $resMeta]), new App\Modules\Http\HttpResponse(200, $csvStats)];
$externalService->run($peselJobId);
check((int)$db->fetchOne('SELECT count FROM etymolog_occurrence WHERE id=?', [$occId])['count'] === 10, 'statistics reimport preserves manual count');
status(api('DELETE', 'etymolog/occurrences/'.$occId, token: $editor), 200, 'archive imported occurrence');
status(api('DELETE', 'etymolog/occurrences/'.$occId.'?force=true', token: $admin), 409, 'physical delete cannot orphan import provenance');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?', [$peselJobId]);
$fake->responses = [$jsonResponse($dataset), $jsonResponse($listMeta), $jsonResponse(['data' => $resMeta]), new App\Modules\Http\HttpResponse(200, $csvStats)];
$externalService->run($peselJobId);
check((int)$db->fetchOne('SELECT deleted FROM etymolog_occurrence WHERE id=?', [$occId])['deleted'] === 1, 'statistics tombstone preserved');
check((new App\Modules\Etymolog\EtymologExternalRepository($db, 'other'))->imports('entries', $dictEntryId) === [], 'external repository tenant isolation');
try {
    $db->insert('etymolog_external_record', ['franchise_code' => 'other', 'provider' => 'wiktionary', 'external_id' => 'cross', 'name_id' => $dictNameId, 'source_id' => $record['source_id'], 'revision' => '1', 'source_url' => 'https://example.org', 'license' => 'test', 'license_url' => 'https://example.org', 'attribution' => 'test', 'payload' => '{}', 'content_hash' => str_repeat('a',64), 'fetched_at' => gmdate('Y-m-d H:i:s')]);
    throw new LogicException('Expected external FK failure');
} catch (RuntimeException $e) {check($e->getPrevious() instanceof PDOException, 'external FK independently blocks foreign tenant');}
$sourceSeed = file_get_contents($root.'/migrations/etymolog_seed.sql');
$db->getPdo()->exec($sourceSeed); $db->getPdo()->exec($sourceSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wiktionary'")['n'] === 13, 'dictionary country seed stays unique alongside explicit fixture job');
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='poland-pesel'")['n'] === 3, 'male and female seeds stay unique alongside explicit fixture job');

$csu = new App\Modules\Etymolog\Providers\CsuBabyNamesProvider($fake);
$zipFixture = static function (bool $formula = false, bool $externalRelationship = false): string {
    $path = tempnam(sys_get_temp_dir(), 'ety-fixture-'); $z = new ZipArchive(); $z->open($path, ZipArchive::OVERWRITE);
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $relNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $z->addFromString('xl/workbook.xml', '<workbook xmlns="'.$ns.'" xmlns:r="'.$relNs.'"><sheets><sheet name="Chlapci" r:id="rId1"/><sheet name="Dívky" r:id="rId2"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.$relNs.'/worksheet" Target="'.($externalRelationship ? 'https://example.org/sheet.xml' : 'worksheets/sheet1.xml').'"/><Relationship Id="rId2" Type="'.$relNs.'/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
    $z->addFromString('xl/sharedStrings.xml', '<sst xmlns="'.$ns.'"><si><t>Jméno</t></si><si><t>Počet</t></si><si><t>Pořadí</t></si></sst>');
    foreach ([1,2] as $sheet) {
        $xml = '<worksheet xmlns="'.$ns.'"><sheetData><row><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>';
        for ($rank=1;$rank<=100;++$rank) {
            $row = $rank+1;
            $xml .= '<row><c r="A'.$row.'" t="inlineStr"><is><t>TEST'.$sheet.'NAME'.$rank.'</t></is></c><c r="B'.$row.'">'.($formula && $rank===1 ? '<f>1+1</f>' : '').'<v>'.(1000-$rank).'</v></c><c r="C'.$row.'" t="inlineStr"><is><t>'.($rank===100 ? '100-101' : $rank).'</t></is></c></row>';
        }
        $z->addFromString('xl/worksheets/sheet'.$sheet.'.xml', $xml.'</sheetData></worksheet>');
    }
    $z->close(); $data = file_get_contents($path); unlink($path); return $data;
};
$xlsx = $zipFixture();
$csuTerms = '<a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>';
$csuJob = status(api('POST', 'etymolog/sync-jobs', ['title' => 'CSU', 'provider' => 'csu-baby-names', 'language' => 'cs', 'kind' => 'births_2025', 'batch_size' => 500], $admin), 201, 'create Czech newborn statistics job');
$csuJobId = (int)$csuJob['id'];
$csuService = new App\Modules\Etymolog\EtymologSyncService($jobs, $sync, new App\Modules\Etymolog\ProviderRegistry(['csu-baby-names' => $csu]), $storyRepo, $external);
$fake->responses = [new App\Modules\Http\HttpResponse(200, $csuTerms), new App\Modules\Http\HttpResponse(200, $xlsx)];
$result = $csuService->run($csuJobId);
check($result['processed'] === 200 && $result['status'] === 'complete', 'CSU imports both sex sheets and completes');
$csuRow = $db->fetchOne("SELECT o.* FROM etymolog_occurrence o JOIN etymolog_external_record e ON e.franchise_code=o.franchise_code AND e.occurrence_id=o.id WHERE e.provider='csu-baby-names' ORDER BY o.id LIMIT 1");
check($csuRow['measure'] === 'births' && $csuRow['observed_year'] === 2025 && $csuRow['observed_on'] === null && $csuRow['country_code'] === 'CZ' && $csuRow['count'] === 999, 'CSU population is annual births, not all residents or snapshot date');
$csuSnapshot = $external->imports('occurrences', (int)$csuRow['id'])[0];
check($csuSnapshot['license'] === 'CC-BY-4.0' && $csuSnapshot['payload']['coverage'] === 'top100_per_sex', 'CSU source licence and limited coverage retained');
$reader = new App\Modules\Etymolog\Readers\NameStatisticsXlsxReader();
check($reader->read($xlsx)[99]['rank'] === '100-101', 'shared rank range preserved without numeric truncation');
try {$reader->read($zipFixture(formula:true));throw new LogicException('Expected formula rejection');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'xlsx_formula_not_allowed', 'spreadsheet formulas never executed or trusted');}
try {$reader->read($zipFixture(externalRelationship:true));throw new LogicException('Expected external relationship rejection');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'invalid_xlsx_relationships', 'spreadsheet external worksheets never fetched');}
$fake->responses = [new App\Modules\Http\HttpResponse(200, 'No reuse')];
try {$csu->batch('cs', 'births_2025', null, 1);throw new LogicException('Expected rights rejection');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'upstream_license_changed', 'CSU requires confirmed licence marker');}
$fake->responses = [new App\Modules\Http\HttpResponse(200, $csuTerms), new App\Modules\Http\HttpResponse(200, $xlsx)];
$csuBatch = $csu->batch('cs', 'births_2025', null, 1);
check(!$csuBatch['complete'] && json_decode($csuBatch['cursor'],true)['offset']===1, 'CSU batch can resume within a release');
status(api('POST', 'etymolog/sync-jobs/'.$peselJobId.'/reset', token: $editor), 403, 'only admin can reset progress');
status(api('POST', 'etymolog/sync-jobs/'.$peselJobId.'/reset', token: $admin, host: 'other.test'), 401, 'reset token cannot cross tenant');
$reset = status(api('POST', 'etymolog/sync-jobs/'.$peselJobId.'/reset', token: $admin), 200, 'admin can restart changed snapshot');
check($reset['cursor'] === null && $reset['next_run_at'] === null && $reset['last_status'] === 'reset', 'reset keeps data and clears only progress');
$db->getPdo()->exec($sourceSeed);$db->getPdo()->exec($sourceSeed);
// Seed ran before the explicit fixture job; no extra seed insertion after fixture creation.
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='csu-baby-names'")['n'] === 2, 'CSU repeated seed does not duplicate existing jobs');

// A failure in a later external record must roll back all entities and progress.
$rollbackFirst = $csuBatch['items'][0];
$rollbackFirst['external_id'] = 'rollback:1';
$rollbackFirst['name'] = 'RollbackExternalName';
$rollbackFirst['source_key'] = 'rollback-source';
$rollbackFirst['source_title'] = 'Rollback external source';
$rollbackSecond = $rollbackFirst;
$rollbackSecond['external_id'] = 'rollback:2';
$rollbackSecond['name'] = 'RollbackExternalNameTwo';
$rollbackSecond['occurrence']['name_id'] = 2147483647; // Deliberate FK failure after first insert.
$brokenExternal = new class([$rollbackFirst, $rollbackSecond]) implements App\Modules\Etymolog\Contracts\BatchProvider {
    public function __construct(private array $items) {}
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array
    {return ['items' => $this->items, 'cursor' => 'must-not-commit', 'complete' => false];}
};
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$csuJobId]);
$brokenExternalService = new App\Modules\Etymolog\EtymologSyncService($jobs, $sync, new App\Modules\Etymolog\ProviderRegistry(['csu-baby-names' => $brokenExternal]), $storyRepo, $external);
try {$brokenExternalService->run($csuJobId);throw new LogicException('Expected rollback');}
catch (App\Modules\Etymolog\SyncException $e) {check($e->reason === 'sync_failed', 'external SQL failure exposes safe error only');}
check(!$db->fetchOne("SELECT id FROM etymolog_name WHERE name='RollbackExternalName'") && !$db->fetchOne("SELECT id FROM etymolog_source WHERE title='Rollback external source'") && !$db->fetchOne("SELECT id FROM etymolog_external_record WHERE external_id='rollback:1'"), 'external batch rolls back names sources occurrences and snapshots');
$failedExternal = $db->fetchOne('SELECT `cursor`,last_status FROM etymolog_sync_job WHERE id=?', [$csuJobId]);
check($failedExternal['cursor'] === null && $failedExternal['last_status'] === 'failed', 'external rollback keeps cursor and records failed run');
