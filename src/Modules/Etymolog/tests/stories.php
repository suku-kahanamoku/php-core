<?php

// Included in the isolated integration harness; reuses real HTTP auth and test DB.
$storyRepo = new App\Modules\Etymolog\EtymologStoryRepository($db, 'etymolog');
$storyProvider = new App\Modules\Etymolog\Providers\WikisourceProvider($fake, new App\Modules\Etymolog\EtymologDiscoveryRepository($db, 'etymolog'));
$storyService = new App\Modules\Etymolog\EtymologSyncService($jobs, $sync, new App\Modules\Etymolog\ProviderRegistry(['wikisource' => $storyProvider]), $storyRepo);
$storyJob = status(api('POST', 'etymolog/sync-jobs', ['title' => 'Stories', 'provider' => 'wikisource', 'kind' => 'stories', 'batch_size' => 1], $admin), 201, 'create story job');
$storyJobId = (int)$storyJob['id'];
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad', 'provider' => 'wikisource'], $admin), 422, 'story job rejects name kind/default batch');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad', 'provider' => 'wikisource', 'kind' => 'stories', 'batch_size' => 1, 'language' => 'de'], $admin), 422, 'story job rejects unsupported language');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad', 'kind' => 'stories'], $admin), 422, 'Wikidata rejects story kind');
$myth = status(api('POST', 'etymolog/entries', ['type' => 'mythology', 'title' => 'Myth', 'body' => 'Mythological narrative', 'source_url' => 'https://example.org/myth'], $editor), 201, 'mythology can exist without a primary name');
check($myth['name_id'] === null && $myth['published'] === 0, 'shared entry nullable name and draft');
$link = status(api('POST', 'etymolog/entry-names', ['entry_id' => (int)$myth['id'], 'name_id' => $id], $editor), 201, 'link shared entry');
status(api('POST', 'etymolog/entry-names', ['entry_id' => (int)$myth['id'], 'name_id' => $id], $editor), 409, 'duplicate active link rejected');
status(api('POST', 'etymolog/entry-names', ['entry_id' => (int)$myth['id'], 'name_id' => $id], $other, 'other.test'), 422, 'foreign story link rejected');
status(api('PATCH', 'etymolog/entry-names/'.$link['id'], ['reviewed' => 1], $other, 'other.test'), 404, 'foreign link update hidden');
status(api('DELETE', 'etymolog/entries/'.$myth['id'], token: $editor), 409, 'shared entry deletion blocked by active link');
status(api('PATCH', 'etymolog/entry-names/'.$link['id'], ['reviewed' => 1], $editor), 200, 'editor confirms association');
try {
    $db->insert('etymolog_entry_name', ['franchise_code' => 'other', 'entry_id' => $myth['id'], 'name_id' => $id]);
    throw new LogicException('Expected composite FK failure');
} catch (RuntimeException $e) { check($e->getPrevious() instanceof PDOException, 'story link FK prevents tenant crossing'); }

$storyNameIds=[];
foreach (['Libuše','Kazi','Teta','Závěrečná'] as $label) {
    $storyNameIds[]=$db->insert('etymolog_name',['franchise_code'=>'etymolog','name'=>$label,'kind'=>'given']);
}
$storyStart=json_encode(['after'=>$storyNameIds[0]-1]);
$db->query('UPDATE etymolog_sync_job SET `cursor`=? WHERE id=?',[$storyStart,$storyJobId]);
$storySearch=['query'=>['search'=>[['pageid'=>2669,'ns'=>0,'title'=>'Staré pověsti české (1959)/O Libuši']]]];
$bodyText = str_repeat('Libuše, Kazi & Teta vystupují v původní pověsti. ', 6);
$storyFixture = static function (string $title = 'O Libuši', int $pageId = 2669, int $revision = 1, string $license = 'PD old 70') use ($bodyText): array {
    return ['parse' => ['title' => 'Staré pověsti české (1959)/'.$title, 'pageid' => $pageId, 'revid' => $revision,
        'text' => ['*' => '<table class="textinfo"><tr><td>Titulek:</td><td>'.$title.'</td></tr><tr><td>Autor:</td><td>Alois Jirásek</td></tr><tr><td>Zdroj:</td><td>Staré pověsti české, 1959, s. 27–31.</td></tr><tr><td>Licence:</td><td>'.$license.'</td></tr></table><p>OUTSIDE NAVIGATION</p><div class="forma proza"><p>'.htmlspecialchars($bodyText).'<script>evil()</script><sup>999</sup></p><p>Druhý odstavec.</p></div>']]];
};
$fake->responses = [$jsonResponse($storySearch), $jsonResponse($storyFixture())];
$result = $storyService->run($storyJobId);
check($result['processed'] === 1 && json_decode($result['cursor'],true)['after'] === $storyNameIds[0], 'story import commits batch cursor');
$import = $db->fetchOne("SELECT * FROM etymolog_story_import WHERE franchise_code='etymolog' AND external_id='cs:2669'");
$storyId = (int)$import['entry_id'];
$story = status(api('GET', 'etymolog/entries/'.$storyId, token: $editor), 200, 'imported story readable');
check($story['type'] === 'legend' && $story['certainty'] === 'unverified' && $story['published'] === 0 && $story['name_id'] === null, 'import is a draft legend, never historical fact');
check(!str_contains($story['body'], 'evil') && !str_contains($story['body'], 'OUTSIDE') && !str_contains($story['body'], '999') && str_contains($story['body'], '&') && str_contains($story['body'], "\n\n"), 'plain prose preserves paragraphs and strips markup/navigation');
// A later DB name discovers the same chapter: append a link, not another story.
foreach ([1,2] as $index) {
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?',[$storyJobId]);
    $fake->responses=[$jsonResponse($storySearch),$jsonResponse($storyFixture())];
    $storyService->run($storyJobId);
}
$links = $db->fetchAll('SELECT * FROM etymolog_entry_name WHERE entry_id=?', [$storyId]);
check(count($links) === 3 && array_sum(array_column($links, 'reviewed')) === 0, 'one story linked to three names awaiting review');
$provenance = status(api('GET', 'etymolog/entries/'.$storyId.'/imports', token: $editor), 200, 'story provenance API');
check($provenance[0]['license'] === 'PD-old-70' && $provenance[0]['revision'] === '1' && $provenance[0]['payload']['author'] === 'Alois Jirásek', 'source rights revision author and text retained');
status(api('GET', 'etymolog/entries/'.$storyId.'/imports', token: $other, host: 'other.test'), 404, 'foreign story provenance hidden');
status(api('GET', 'etymolog/entries/'.$storyId.'/imports'), 401, 'anonymous story provenance denied');
check((new App\Modules\Etymolog\EtymologStoryRepository($db, 'other'))->imports($storyId) === [], 'story snapshot repository tenant isolated');
status(api('PATCH', 'etymolog/entries/'.$storyId, ['body' => 'Druhý odstavec.', 'published' => 1], $editor), 200, 'publish a verbatim editorial excerpt');
status(api('PATCH', 'etymolog/entry-names/'.$links[0]['id'], ['reviewed' => 1], $editor), 200, 'approve imported association');
status(api('DELETE', 'etymolog/entry-names/'.$links[1]['id'], token: $editor), 200, 'reject imported association');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=? WHERE id=?', [$storyStart,$storyJobId]);
$fake->responses = [$jsonResponse($storySearch), $jsonResponse($storyFixture(revision: 2))];
$storyService->run($storyJobId);
$again = $db->fetchOne('SELECT * FROM etymolog_entry WHERE id=?', [$storyId]);
check($again['body'] === 'Druhý odstavec.' && $again['published'] === 1 && $storyRepo->imports($storyId)[0]['revision'] === '2', 'story refresh preserves editorial text and publication while updating snapshot');
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_story_import WHERE entry_id=?', [$storyId])['n'] === 1 && (int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_citation WHERE entry_id=?', [$storyId])['n'] === 1, 'repeated story import creates no duplicate story citation or snapshot');
check((int)$db->fetchOne('SELECT reviewed FROM etymolog_entry_name WHERE id=?', [$links[0]['id']])['reviewed'] === 1 && (int)$db->fetchOne('SELECT deleted FROM etymolog_entry_name WHERE id=?', [$links[1]['id']])['deleted'] === 1, 'refresh preserves approved and rejected associations');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=? WHERE id=?', [$storyStart,$storyJobId]);
$fake->responses = [$jsonResponse($storySearch), $jsonResponse($storyFixture(license: 'CC BY-NC'))];
try { $storyService->run($storyJobId); throw new LogicException('Expected license rejection'); }
catch (App\Modules\Etymolog\SyncException $e) { check($e->reason === 'story_license_not_allowed' && $jobs->findById($storyJobId)['cursor'] === $storyStart && $storyRepo->imports($storyId)[0]['revision'] === '2', 'license change fails without modifying snapshot or cursor'); }
$fake->responses = [new App\Modules\Http\HttpResponse(429, '{}', retryAfter: 9000)];
try { $storyProvider->batch('cs', 'stories', $storyStart, 1); throw new LogicException('Expected 429'); }
catch (App\Modules\Etymolog\SyncException $e) { check($e->reason === 'upstream_rate_limited' && $e->retryAfter === 9000, 'story provider respects rate limit'); }
$fake->responses = [$jsonResponse(['error' => ['code' => 'maxlag']])];
try { $storyProvider->batch('cs', 'stories', $storyStart, 1); throw new LogicException('Expected maxlag'); }
catch (App\Modules\Etymolog\SyncException $e) { check($e->reason === 'upstream_rate_limited', 'story provider handles API error'); }
$badMarkup = $storyFixture();
$badMarkup['parse']['text']['*'] = str_replace('forma proza', 'unknown', $badMarkup['parse']['text']['*']);
$fake->responses = [$jsonResponse($storySearch), $jsonResponse($badMarkup)];
try { $storyProvider->batch('cs', 'stories', $storyStart, 1); throw new LogicException('Expected markup failure'); }
catch (App\Modules\Etymolog\SyncException $e) { check($e->reason === 'story_markup_changed', 'changed prose layout fails closed'); }
check($storyProvider->batch('cs', 'stories', json_encode(['after'=>PHP_INT_MAX]), 1)['complete'] === true, 'DB name traversal completes');
try { $storyProvider->batch('cs', 'stories', '{"after":-1}', 1); throw new LogicException('Expected cursor failure'); }
catch (App\Modules\Etymolog\SyncException $e) { check($e->reason === 'invalid_provider_cursor', 'invalid story cursor rejected'); }

// Transaction rollback must undo earlier story, source, name and citation inserts.
$brokenStory = new class implements App\Modules\Etymolog\Contracts\BatchProvider {
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array {
        $item = ['external_id' => 'cs:88888', 'revision' => '1', 'source_url' => 'https://cs.wikisource.org/w/index.php?oldid=1', 'payload' => [], 'title' => 'Rollback narrative', 'body' => 'Body', 'region' => 'Čechy', 'names' => ['Rollbackname'], 'bibliography' => 'Test'];
        return ['items' => [$item, array_replace($item, ['external_id' => 'cs:88889', 'title' => str_repeat('x', 300)])], 'cursor' => '2', 'complete' => false];
    }
};
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$storyJobId]);
$atomicStories = new App\Modules\Etymolog\EtymologSyncService($jobs, $sync, new App\Modules\Etymolog\ProviderRegistry(['wikisource' => $brokenStory]), $storyRepo);
try { $atomicStories->run($storyJobId); throw new LogicException('Expected story SQL failure'); }
catch (App\Modules\Etymolog\SyncException $e) { check($e->reason === 'sync_failed' && $jobs->findById($storyJobId)['cursor'] === $storyStart, 'failed story batch preserves cursor'); }
check(!$db->fetchOne("SELECT id FROM etymolog_entry WHERE title='Rollback narrative'") && !$db->fetchOne("SELECT id FROM etymolog_name WHERE name='Rollbackname'") && !$db->fetchOne("SELECT id FROM etymolog_source WHERE title LIKE '%Rollback narrative'"), 'failed story batch rolls back all dependent records');
foreach ($links as $storyLink) {
    if ((int)$storyLink['id'] !== (int)$links[1]['id']) { status(api('DELETE', 'etymolog/entry-names/'.$storyLink['id'], token: $editor), 200, 'remove remaining association'); }
}
status(api('PATCH', 'etymolog/entries/'.$storyId, ['published'=>0], $editor), 200, 'unpublish cultural entry before removing source');
$storyCite = $db->fetchOne('SELECT id FROM etymolog_citation WHERE entry_id=?', [$storyId]);
status(api('DELETE', 'etymolog/citations/'.$storyCite['id'], token: $editor), 200, 'remove narrative citation');
status(api('DELETE', 'etymolog/entries/'.$storyId, token: $editor), 200, 'archive imported story');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$storyJobId]);
$fake->responses = [$jsonResponse($storySearch), $jsonResponse($storyFixture(revision: 3))];
$storyService->run($storyJobId);
check((int)$db->fetchOne('SELECT deleted FROM etymolog_entry WHERE id=?', [$storyId])['deleted'] === 1 && $storyRepo->imports($storyId)[0]['revision'] === '2', 'story tombstone prevents recreation and refresh');
status(api('GET', 'etymolog/entries/'.$storyId.'/imports', token: $editor), 404, 'archived story provenance hidden');
$db->getPdo()->exec(file_get_contents($root.'/migrations/etymolog_seed.sql'));
$db->getPdo()->exec(file_get_contents($root.'/migrations/etymolog_seed.sql'));
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikisource'")['n'] === 2, 'story seed stays unique alongside explicit fixture job');

// Search continuation must exhaust chapters for a DB name before selecting another name.
$storyDiscovery=new App\Modules\Etymolog\Providers\WikisourceDiscoveryProvider($fake,new App\Modules\Etymolog\EtymologDiscoveryRepository($db,'etymolog'));
$pagedSearch=$storySearch;$pagedSearch['continue']=['sroffset'=>1,'continue'=>'-||'];
$fake->responses=[$jsonResponse($pagedSearch),$jsonResponse($storyFixture())];
$storyPage=$storyDiscovery->page('Staré pověsti české (1959)',$storyStart);
$storyState=json_decode($storyPage['cursor'],true);
check($storyState['name_id']===$storyNameIds[0] && $storyState['offset']===1 && $storyState['after']===$storyNameIds[0]-1,'story cursor keeps name ID and search offset until all matching chapters are visited');
$fake->responses=[$jsonResponse(['query'=>['search'=>[]]])];
$storyEnd=$storyDiscovery->page('Staré pověsti české (1959)',$storyPage['cursor']);
check(json_decode($storyEnd['cursor'],true)['after']===$storyNameIds[0] && $storyEnd['page']===null,'empty later search page advances to next DB name');
parse_str(parse_url($fake->requests[array_key_last($fake->requests)]->url,PHP_URL_QUERY),$storyParams);
check($storyParams['sroffset']==='1' && str_contains($storyParams['srsearch'],'Libuše'),'resumed search uses stored offset and exact DB name');
check(!App\Modules\Etymolog\Providers\WikisourceDiscoveryProvider::mentions('Příběh vypráví o Annabelle.','Anna') && App\Modules\Etymolog\Providers\WikisourceDiscoveryProvider::mentions('Příběh: ANNA.','Anna'),'story association uses full case-insensitive words, not a partial-name match');
$fake->responses=[];
foreach (['{"after":0,"name_id":1}','{"after":2,"name_id":1,"offset":1}','{"after":0,"offset":1}','-1'] as $invalidCursor) {
    try{$storyDiscovery->page('Staré pověsti české (1959)',$invalidCursor);throw new LogicException('Expected cursor rejection');}
    catch(App\Modules\Etymolog\SyncException $e){check($e->reason==='invalid_provider_cursor','invalid search continuation rejected before HTTP');}
}
$wrongCollection=$storySearch;$wrongCollection['query']['search'][0]['title']='Jiná kniha/Kapitola';
$fake->responses=[$jsonResponse($wrongCollection)];
try{$storyDiscovery->page('Staré pověsti české (1959)',$storyStart);throw new LogicException('Expected source rejection');}
catch(App\Modules\Etymolog\SyncException $e){check($e->reason==='invalid_story_discovery','search result outside licensed source collection rejected');}
