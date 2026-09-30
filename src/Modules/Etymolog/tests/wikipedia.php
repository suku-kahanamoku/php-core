<?php
// Isolated DB and fake upstream responses: arbitrary test names must come from the DB.
use App\Modules\Etymolog\{EtymologDiscoveryRepository,EtymologRepository,EtymologSyncRepository,EtymologSyncService,EtymologExternalRepository,ProviderRegistry,SyncException};
use App\Modules\Etymolog\Providers\WikipediaNamesProvider;
$wikiTenant='wikipedia-fixture';
$wikiNames=new EtymologDiscoveryRepository($db,$wikiTenant);
$wiki=new WikipediaNamesProvider($fake,$wikiNames);
$fake->responses=[];
check($wiki->batch('cs','etymologies',null,1)['complete'],'empty tenant sends no HTTP and invents no target names');
$wikiName=$db->insert('etymolog_name',['franchise_code'=>$wikiTenant,'name'=>'Testava','kind'=>'given']);
$wikiSurname=$db->insert('etymolog_name',['franchise_code'=>$wikiTenant,'name'=>'TESTAVA','kind'=>'surname']);
$wikiDuplicate=$db->insert('etymolog_name',['franchise_code'=>$wikiTenant,'name'=>'TESTAVA','kind'=>'given']);
$wikiDeleted=$db->insert('etymolog_name',['franchise_code'=>$wikiTenant,'name'=>'Deleted','kind'=>'given','deleted'=>1]);
$db->insert('etymolog_name',['franchise_code'=>'other-wiki-fixture','name'=>'Foreign','kind'=>'given']);
check((int)$wikiNames->next('',0)['id']===$wikiName && (int)$wikiNames->next('',$wikiName)['id']===$wikiSurname && $wikiNames->next('',$wikiSurname)===null,'discovery includes drafts, distinguishes kind, deduplicates case, excludes deleted and foreign names');
check($wikiNames->find($wikiDeleted,'')===null && $wikiNames->find($wikiSurname,'given')===null,'resumed discovery is scoped to active tenant and kind');
$wikiRights=['query'=>['rightsinfo'=>['url'=>WikipediaNamesProvider::LICENSE_URL.'deed.cs']]];
$wikiPage=static function(string $kind='given',int $revision=11):array {
    return ['title'=>$kind==='given'?'Testava':'Testava (příjmení)','pageid'=>$kind==='given'?101:102,'revid'=>$revision,
        'templates'=>[['title'=>$kind==='given'?'Šablona:Infobox - jméno':'Šablona:Infobox - příjmení']],
        'text'=>['*'=>'<div class="mw-parser-output"><table><tr><td><p>Infobox excluded</p></td></tr></table><p>Testava pochází z původního slova &amp; tento odstavec je text testovacího pramene.<sup>99</sup><script>evil()</script></p><h2 id="Pranostiky">Pranostiky</h2><p>První původní řádek pranostiky.<br>Druhý původní řádek pranostiky.</p><h2>Svaté a blahoslavené</h2><ul><li><a href="/wiki/Testava_sv%C4%9Btice" title="Testava světice">Testava světice</a></li><li><a href="https://evil.invalid/wiki/Evil" title="Svatá Evil">Excluded</a></li></ul><h2>Významní nositelé</h2><p>Not etymology or mythology.</p></div>']];
};
$wikiQueue=static function(string $kind='given',int $revision=11) use($wikiRights,$wikiPage,$jsonResponse):array {
    $p=$wikiPage($kind,$revision);
    return array_map($jsonResponse,[$wikiRights,['query'=>['pages'=>[(string)$p['pageid']=>$p]]],['parse'=>$p]]);
};
foreach ([['sk','culture',null,1],['cs','surname',null,1],['cs','culture',null,4],['cs','culture','-1',1],['cs','culture','{"after":0,"name_id":1}',1]] as $args) {
    $fake->responses=[];
    try {$wiki->batch(...$args);throw new LogicException('Expected invalid configuration');}
    catch(SyncException $e){check(in_array($e->reason,['invalid_provider_configuration','invalid_provider_cursor'],true),'reject invalid configuration before HTTP');}
}
$fake->responses=[$jsonResponse(['query'=>['rightsinfo'=>['url'=>'https://example.org/no-reuse']]])];
try{$wiki->batch('cs','culture',null,1);throw new LogicException('Expected licence failure');}
catch(SyncException $e){check($e->reason==='upstream_license_changed','licence change fails closed');}
$fake->responses=[$jsonResponse($wikiRights),$jsonResponse(['query'=>['pages'=>['-1'=>['missing'=>'','title'=>'Testava']]]])];
$missing=$wiki->batch('cs','etymologies',null,1);
check(!$missing['complete'] && json_decode($missing['cursor'],true)['after']===$wikiName && !$missing['items'],'missing source skips to next DB name');
$untyped=$wikiPage();unset($untyped['templates']);
$fake->responses=[$jsonResponse($wikiRights),$jsonResponse(['query'=>['pages'=>['101'=>$untyped]]])];
check($wiki->batch('cs','etymologies',null,1)['items']===[],'an untyped biography with matching title is not a name article');
$wikiJobs=new EtymologRepository($db,$wikiTenant,'sync-jobs');
$wikiExternal=new EtymologExternalRepository($db,$wikiTenant);
$wikiSync=new EtymologSyncService($wikiJobs,new EtymologSyncRepository($db,$wikiTenant),new ProviderRegistry(['wikipedia-names'=>$wiki]),external:$wikiExternal);
$wikiJob=$db->insert('etymolog_sync_job',['franchise_code'=>$wikiTenant,'title'=>'Fixture etymology','provider'=>'wikipedia-names','language'=>'cs','kind'=>'etymologies','batch_size'=>1]);
$fake->responses=$wikiQueue();
$invalidPage=$wikiPage();$invalidPage['pageid']=999;
$fake->responses[2]=$jsonResponse(['parse'=>$invalidPage]);
try{$wikiSync->run($wikiJob);throw new LogicException('Expected page mismatch');}
catch(SyncException $e){check($e->reason==='invalid_dossier_revision' && $wikiJobs->findById($wikiJob)['cursor']===null,'failed article validation keeps cursor');}
check(!$db->fetchOne('SELECT id FROM etymolog_external_record WHERE franchise_code=?',[$wikiTenant]),'failed article batch writes no partial evidence');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?',[$wikiJob]);
$fake->responses=$wikiQueue();$requestStart=count($fake->requests);
$result=$wikiSync->run($wikiJob);
check($result['processed']===1 && json_decode($result['cursor'],true)['after']===$wikiName,'etymology imports lead and advances by DB ID');
$wikiRecord=$db->fetchOne('SELECT * FROM etymolog_external_record WHERE franchise_code=?',[$wikiTenant]);
$wikiEntryId=(int)$wikiRecord['entry_id'];
$wikiEntry=$db->fetchOne('SELECT * FROM etymolog_entry WHERE id=?',[$wikiEntryId]);
check($wikiEntry['body']==='Testava pochází z původního slova & tento odstavec je text testovacího pramene.' && (int)$wikiEntry['name_id']===$wikiName && !$wikiEntry['published'],'source lead excludes infobox, citations and scripts, reuses DB name as draft');
$wikiCitation=$db->fetchOne('SELECT * FROM etymolog_citation WHERE entry_id=?',[$wikiEntryId]);
check($wikiCitation['quotation']===$wikiEntry['body'] && $wikiCitation['url']===$wikiEntry['source_url'],'original source text and pinned revision citation retained');
parse_str(parse_url($fake->requests[$requestStart+1]->url,PHP_URL_QUERY),$wikiParams);
check(str_contains($wikiParams['titles'],'Testava') && !str_contains($wikiParams['titles'],'Anna'),'only current DB name supplies article candidates');
$db->update('etymolog_entry',['title'=>'Editorial title','published'=>1],'id=?',[$wikiEntryId]);
// Old catalog IDs are reused through their stored provenance without a legacy-name map.
$db->update('etymolog_external_record',['external_id'=>'cs:old-arbitrary-key'],'id=?',[$wikiRecord['id']]);
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?',[$wikiJob]);
$fake->responses=$wikiQueue(revision:12);$wikiSync->run($wikiJob);
$refreshed=$db->fetchOne('SELECT * FROM etymolog_entry WHERE id=?',[$wikiEntryId]);
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_external_record WHERE franchise_code=?',[$wikiTenant])['n']===1 && $refreshed['title']==='Editorial title' && (int)$refreshed['published']===1 && $wikiExternal->imports('entries',$wikiEntryId)[0]['revision']==='12','new discovery reuses legacy evidence, preserves editorial data and updates snapshot');
check($db->fetchOne('SELECT url FROM etymolog_citation WHERE entry_id=?',[$wikiEntryId])['url']===$wikiCitation['url'],'refresh preserves citation to original text revision');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?',[$wikiJob]);
$fake->responses=$wikiQueue('surname');$wikiSync->run($wikiJob);
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_entry WHERE franchise_code=?',[$wikiTenant])['n']===2,'same spelling surname and given name have independent etymologies');
$db->update('etymolog_entry',['deleted'=>1],'id=?',[$wikiEntryId]);
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?',[$wikiJob]);
$fake->responses=$wikiQueue(revision:13);$wikiSync->run($wikiJob);
check($wikiExternal->imports('entries',$wikiEntryId)[0]['revision']==='12','archived evidence is not resurrected or refreshed');
$fake->responses=$wikiQueue();$culture=$wiki->batch('cs','culture','12',1);
check(count($culture['items'])===1 && $culture['items'][0]['entry']['type']==='proverb' && str_contains($culture['items'][0]['entry']['body'],"\n"),'legacy numeric cursor restarts DB traversal and preserves verse lines');
$relatedState=json_decode($culture['cursor'],true);
check($relatedState['name_id']===$wikiName && $relatedState['revision']===11 && $relatedState['related']===0,'related article continuation pins parent name page revision');
$saint=['title'=>'Testava světice','pageid'=>200,'revid'=>20,'categories'=>[['title'=>'Kategorie:Římskokatoličtí svatí']],'text'=>['*'=>'<div class="mw-parser-output"><h2>Život</h2><p>Biography is not automatically a legend.</p><h2 id="Legenda">Legenda</h2><p>Původní doložené znění legendy v testovacím prameni.</p><h2>Uctívání</h2><p>Původní doložené znění tradice v testovacím prameni.</p></div>']];
$fake->responses=array_map($jsonResponse,[$wikiRights,['parse'=>$wikiPage()],['query'=>['pages'=>['200'=>$saint]]],['parse'=>$saint]]);
$cultureRelated=$wiki->batch('cs','culture',$culture['cursor'],1);
check(array_column(array_column($cultureRelated['items'],'entry'),'type')===['legend','tradition'] && json_decode($cultureRelated['cursor'],true)['after']===$wikiName,'explicit source links discover cultural sections, skip biography and advance after final related page');
check($cultureRelated['items'][0]['payload']['association']['revision']===11,'cultural association retains pinned source of name link');
foreach($cultureRelated['items'] as $item){$wikiJobs->exclusive(fn()=>$wikiJobs->transaction(fn()=>$wikiExternal->import('wikipedia-names',$item)));}
$legendRow=$db->fetchOne("SELECT * FROM etymolog_entry WHERE franchise_code=? AND type='legend'",[$wikiTenant]);
check((new EtymologRepository($db,$wikiTenant,'entries'))->hasWebQuotation((int)$legendRow['id'],$legendRow['source_url'],$legendRow['body']),'cultural import retains verifiable quotation required for publication');
$fake->responses=[$jsonResponse($wikiRights),$jsonResponse(['parse'=>$wikiPage(revision:99)])];
try{$wiki->batch('cs','culture',$culture['cursor'],1);throw new LogicException('Expected pinned revision rejection');}
catch(SyncException $e){check($e->reason==='invalid_dossier_revision','related discovery rejects a different parent revision');}
$fake->responses=[new App\Modules\Http\HttpResponse(429,'{}',retryAfter:9000)];
try{$wiki->batch('cs','etymologies',null,1);throw new LogicException('Expected rate limit');}
catch(SyncException $e){check($e->reason==='upstream_rate_limited' && $e->retryAfter===9000,'discovery honors upstream rate limits');}
// A name inserted after a completed pass is eligible; no code list needs editing.
$newWikiName=$db->insert('etymolog_name',['franchise_code'=>$wikiTenant,'name'=>'Dalsina','kind'=>'given']);
check((int)$wikiNames->next('',$wikiSurname)['id']===$newWikiName,'new DB names automatically enter discovery');
// Mythology follows an explicit article link, not a name-to-deity catalog.
$deityName=$wikiPage();
$deityName['text']['*']='<div class="mw-parser-output"><p>Jméno pochází z pramene, který odkazuje na <a title="Testava (mytologie)" href="/wiki/Testava_(mytologie)">mytologii</a>.</p></div>';
$fake->responses=array_map($jsonResponse,[$wikiRights,['query'=>['pages'=>['101'=>$deityName]]],['parse'=>$deityName]]);
$mythStart=$wiki->batch('cs','culture',null,1);
$deity=$saint;
$deity['title']='Testava (mytologie)';$deity['categories']=[['title'=>'Kategorie:Římští bohové']];
$deity['text']['*']='<div class="mw-parser-output"><p>Původní úvodní text pramene o mytologické postavě.</p></div>';
$fake->responses=array_map($jsonResponse,[$wikiRights,['parse'=>$deityName],['query'=>['pages'=>['200'=>$deity]]],['parse'=>$deity]]);
$mythResult=$wiki->batch('cs','culture',$mythStart['cursor'],1);
check($mythResult['items'][0]['entry']['type']==='mythology','category-verified mythological lead is imported from an explicit source association');
$twoLegends=$wikiPage();
$twoLegends['text']['*']='<div class="mw-parser-output"><h2 id="Legenda_1">Legenda</h2><p>První samostatný převzatý příběh s původním zněním.</p><h2 id="Legenda_2">Legenda</h2><p>Druhý samostatný převzatý příběh s původním zněním.</p></div>';
$fake->responses=array_map($jsonResponse,[$wikiRights,['query'=>['pages'=>['101'=>$twoLegends]]],['parse'=>$twoLegends]]);
$twoLegendsBatch=$wiki->batch('cs','culture',null,1);
check(count(array_unique(array_column($twoLegendsBatch['items'],'external_id')))===2,'repeated headings retain separate source section anchors');
foreach($twoLegendsBatch['items'] as $item){
    $wikiJobs->exclusive(fn()=>$wikiJobs->transaction(fn()=>$wikiExternal->import('wikipedia-names',$item)));
    $wikiJobs->exclusive(fn()=>$wikiJobs->transaction(fn()=>$wikiExternal->import('wikipedia-names',$item)));
}
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_entry WHERE franchise_code=? AND title='Testava – Legenda'",[$wikiTenant])['n']===2,'legacy matching does not merge new separate source sections, repeat import remains idempotent');
foreach (['culture', 'etymologies'] as $kind) {
    $created = status(api('POST', 'etymolog/sync-jobs', ['title' => 'Wiki '.$kind, 'provider' => 'wikipedia-names', 'language' => 'cs', 'kind' => $kind, 'batch_size' => 3], $admin), 201, 'admin creates Wikipedia '.$kind.' task');
    status(api('DELETE', 'etymolog/sync-jobs/'.$created['id'], token: $admin), 200, 'delete Wikipedia test task');
}
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad Wikipedia', 'provider' => 'wikipedia-names', 'kind' => 'culture', 'language' => 'en', 'batch_size' => 1], $admin), 422, 'Wikipedia CRUD rejects unsupported language');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Bad Wikipedia', 'provider' => 'wikipedia-names', 'kind' => 'culture', 'batch_size' => 4], $admin), 422, 'Wikipedia CRUD bounds batch');
status(api('POST', 'etymolog/sync-jobs', ['title' => 'Forbidden Wikipedia', 'provider' => 'wikipedia-names', 'kind' => 'culture', 'batch_size' => 1], $editor), 403, 'editor cannot create Wikipedia tasks');
// Seed must also respect deleted configurations. Test clean insertion under a separate tenant.
$wikiSeed = file_get_contents($root.'/migrations/etymolog_seed.sql');
$db->getPdo()->exec($wikiSeed); $db->getPdo()->exec($wikiSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikipedia-names'")['n'] === 4 && (int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider='wikipedia-names' AND deleted=0")['n'] === 2, 'Wikipedia seed respects deleted tasks and does not duplicate');
$wikiCleanSeed = str_replace("'etymolog'", "'wikipedia-seed-fixture'", $wikiSeed);
$db->getPdo()->exec($wikiCleanSeed); $db->getPdo()->exec($wikiCleanSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='wikipedia-seed-fixture' AND provider='wikipedia-names' AND enabled=1 AND next_run_at IS NULL AND last_status IS NULL")['n'] === 2, 'Wikipedia seed creates exactly two due tasks without running them');


// Enrichment skips only dossiers with all four visible types; drafts and short texts still need sources.
$coverageTenant='coverage-fixture';
$coverageRepo=new EtymologDiscoveryRepository($db,$coverageTenant);
$covered=$db->insert('etymolog_name',['franchise_code'=>$coverageTenant,'name'=>'Pokryté','kind'=>'given','published'=>1]);
$uncovered=$db->insert('etymolog_name',['franchise_code'=>$coverageTenant,'name'=>'Nedokončené','kind'=>'given','published'=>1]);
$etymology=$db->insert('etymolog_entry',['franchise_code'=>$coverageTenant,'name_id'=>$covered,'type'=>'etymology','title'=>'Origin','body'=>str_repeat('ž',49),'published'=>1]);
foreach (['mythology','tradition'] as $type) {
    $db->insert('etymolog_entry',['franchise_code'=>$coverageTenant,'name_id'=>$covered,'type'=>$type,'title'=>'Source','body'=>str_repeat('č',50),'published'=>1]);
}
$db->insert('etymolog_entry',['franchise_code'=>$coverageTenant,'name_id'=>$covered,'type'=>'proverb','title'=>'Saying','body'=>'Krátká pranostika.','published'=>1]);
check((int)$coverageRepo->nextIncomplete('',0)['id']===$covered,'49 Unicode characters do not complete a dossier');
$db->query('UPDATE etymolog_entry SET body=? WHERE id=?',[str_repeat('ž',50),$etymology]);
check((int)$coverageRepo->nextIncomplete('',0)['id']===$uncovered && $coverageRepo->findIncomplete($covered,'')===null,'complete published dossier is skipped before external lookup');
$db->query("UPDATE etymolog_entry SET published=0 WHERE franchise_code=? AND name_id=? AND type='mythology'",[$coverageTenant,$covered]);
check((int)$coverageRepo->nextIncomplete('',0)['id']===$covered,'unpublished evidence cannot mark a dossier complete');
check($coverageRepo->nextIncomplete('surname',0)===null,'completion lookup keeps name kinds separate');


$completeOnlyTenant='coverage-only-fixture';
$completeOnly=$db->insert('etymolog_name',['franchise_code'=>$completeOnlyTenant,'name'=>'Hotové','kind'=>'surname','published'=>1]);
foreach (['etymology','mythology','tradition','proverb'] as $type) {
    $db->insert('etymolog_entry',['franchise_code'=>$completeOnlyTenant,'name_id'=>$completeOnly,'type'=>$type,'title'=>'Evidence','body'=>str_repeat('A',$type==='proverb' ? 1 : 50),'published'=>1]);
}
$completeRepo=new EtymologDiscoveryRepository($db,$completeOnlyTenant);
$beforeRequests=count($fake->requests);$fake->responses=[];
$skipWiktionary=(new \App\Modules\Etymolog\Providers\WiktionaryProvider($fake,$completeRepo))->batch('cs','surname',null,1);
$skipWikipedia=(new WikipediaNamesProvider($fake,$completeRepo))->batch('cs','etymologies',null,1);
$skipWikisource=(new \App\Modules\Etymolog\Providers\WikisourceDiscoveryProvider($fake,$completeRepo))->page('Staré pověsti české (1959)',null);
check($skipWiktionary['complete'] && $skipWikipedia['complete'] && $skipWikisource['complete'] && count($fake->requests)===$beforeRequests,'complete dossier causes no Wiktionary, Wikipedia or Wikisource HTTP requests');
