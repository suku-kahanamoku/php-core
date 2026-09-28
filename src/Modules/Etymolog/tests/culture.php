<?php
// Isolated MySQL + real authenticated API; no live source calls.
foreach (App\Modules\Etymolog\ResourceRegistry::CULTURAL_TYPES as $type) {
    status(api('POST', 'etymolog/entries', ['type'=>$type,'certainty'=>$type==='fiction'?'fiction':'unverified','title'=>'No source','body'=>'Invented'], $editor),422,'cultural draft requires web source: '.$type);
}
$webSource = status(api('POST','etymolog/sources',['title'=>'Web collection','url'=>'https://example.org/tradition','license'=>'CC0','attribution'=>'Test publisher'],$editor),201,'create cultural web source');
$culture = status(api('POST','etymolog/entries',['type'=>'tradition','title'=>'Tradition','body'=>'Verbatim source text.','source_url'=>'https://example.org/tradition'],$editor),201,'cultural sourced draft');
$cultureId=(int)$culture['id'];
status(api('PATCH','etymolog/entries/'.$cultureId,['published'=>1],$editor),422,'cultural publication requires quotation even if unverified');
$cultureCitation=status(api('POST','etymolog/citations',['entry_id'=>$cultureId,'source_id'=>(int)$webSource['id'],'quotation'=>'Verbatim source text. A second sentence.'],$editor),201,'record verbatim website quotation');
status(api('PATCH','etymolog/entries/'.$cultureId,['published'=>1],$editor),200,'publish verbatim excerpt with web citation');
status(api('PATCH','etymolog/entries/'.$cultureId,['body'=>'Generated story unrelated to quotation'],$editor),422,'rewriting published cultural text rejected');
status(api('PATCH','etymolog/entries/'.$cultureId,['source_url'=>'https://different.test/'],$editor),422,'unrelated website cannot substitute published source');
status(api('PATCH','etymolog/citations/'.$cultureCitation['id'],['quotation'=>'Different text'],$editor),409,'cannot replace published source quotation');
status(api('DELETE','etymolog/citations/'.$cultureCitation['id'],token:$editor),409,'cannot remove cultural publication evidence');
status(api('PATCH','etymolog/sources/'.$webSource['id'],['license'=>null],$editor),409,'cannot strip published cultural licence');
status(api('PATCH','etymolog/entries/'.$cultureId,['published'=>0],$editor),200,'unpublish before editorial work');
status(api('PATCH','etymolog/citations/'.$cultureCitation['id'],['quotation'=>'Different text'],$editor),200,'draft citation remains editable');
status(api('PATCH','etymolog/entries/'.$cultureId,['published'=>1],$editor),422,'changed citation blocks republication');

$cal=status(api('POST','etymolog/calendars',['title'=>'Regional test','country_code'=>'CZ','system'=>'gregorian','tradition'=>'Test local edition'],$editor),201,'editor creates calendar');
$calId=(int)$cal['id'];
$given=status(api('POST','etymolog/names',['name'=>'Calendar fixture name','kind'=>'given'],$editor),201,'calendar given name');
$dayData=['calendar_id'=>$calId,'source_id'=>(int)$webSource['id'],'name_id'=>(int)$given['id'],'title'=>'Leap day','month'=>2,'day'=>29,'source_url'=>'https://example.org/calendar'];
$day=status(api('POST','etymolog/calendar-days',$dayData,$editor),201,'February 29 supported as recurring date');
$dayId=(int)$day['id'];
status(api('POST','etymolog/calendar-days',array_replace($dayData,['month'=>4,'day'=>31]),$editor),422,'invalid recurring date rejected');
status(api('POST','etymolog/calendar-days',array_replace($dayData,['name_id'=>$id]),$editor),422,'surname cannot be a name day');
status(api('POST','etymolog/calendar-days',array_replace($dayData,['date_rule'=>'Easter + 1']),$editor),422,'fixed date cannot carry movable rule');
status(api('POST','etymolog/calendar-days',array_replace($dayData,['date_kind'=>'movable']),$editor),422,'movable date cannot silently retain month/day');
$movable=status(api('POST','etymolog/calendar-days',array_replace($dayData,['kind'=>'feast','name_id'=>null,'date_kind'=>'movable','month'=>null,'day'=>null,'date_rule'=>'First Sunday after Easter, as recorded by source']),$editor),201,'movable feast retains explicit source rule');
status(api('POST','etymolog/calendar-days',$dayData,$other,'other.test'),422,'foreign calendar references rejected');
status(api('GET','etymolog/calendar-days/'.$dayId,token:$other,host:'other.test'),404,'foreign calendar day hidden');
status(api('GET','etymolog/calendars'),401,'calendar requires existing auth');
status(api('DELETE','etymolog/calendars/'.$calId,token:$editor),409,'calendar deletion blocked while used');
status(api('PATCH','etymolog/calendar-days/'.$dayId,['notes'=>'Editorial'],$editor),200,'calendar PATCH');
status(api('PUT','etymolog/calendar-days/'.$dayId,$dayData,$editor),200,'calendar PUT');
status(api('DELETE','etymolog/calendar-days/'.$dayId,token:$editor),200,'calendar day archive');
status(api('DELETE','etymolog/calendar-days/'.$dayId.'?force=true',token:$admin),200,'calendar hard delete admin');
status(api('DELETE','etymolog/calendar-days/'.$movable['id'],token:$editor),200,'remove movable date');
status(api('DELETE','etymolog/calendars/'.$calId,token:$editor),200,'unused calendar archive');
try {$db->insert('etymolog_calendar_day',['franchise_code'=>'other','calendar_id'=>$calId,'source_id'=>$webSource['id'],'title'=>'Cross tenant','source_url'=>'https://example.org']);throw new LogicException('Expected FK');}
catch(RuntimeException $e){check($e->getPrevious() instanceof PDOException,'calendar composite FK independently enforces tenant');}

$calendarRepo=new App\Modules\Etymolog\EtymologCalendarRepository($db,'etymolog');
$erben=new App\Modules\Etymolog\Providers\ErbenFolkloreProvider($fake);
$erbenService=new App\Modules\Etymolog\EtymologSyncService($jobs,$sync,new App\Modules\Etymolog\ProviderRegistry(['erben-folklore'=>$erben]),$storyRepo,$external,$calendarRepo);
$erbenJob=status(api('POST','etymolog/sync-jobs',['title'=>'Folklore','provider'=>'erben-folklore','kind'=>'folklore','batch_size'=>1],$admin),201,'create folklore job');
$erbenFixture=static function(string $license='PD old 70',int $revision=1):array{return ['parse'=>['title'=>'Prostonárodní české písně a říkadla/25. ledna','pageid'=>99490,'revid'=>$revision,'text'=>['*'=>'<div class="mw-parser-output"><table class="textinfo"><tr><td>Titulek:</td><td>25. ledna</td></tr><tr><td>Autor:</td><td>zapsal Karel Jaromír Erben</td></tr><tr><td>Zdroj:</td><td>Erben, sbírka, 1864, s. 47.</td></tr><tr><td>Licence:</td><td>'.$license.'</td></tr></table><table><tr><td>Navigation</td></tr></table><div class="poem"><p>Fixture original verse one.<br>Fixture original verse two.<script>evil()</script></p></div></div>']]];};
$fake->responses=[$jsonResponse($erbenFixture())];
$result=$erbenService->run((int)$erbenJob['id']);
check($result['processed']===1 && $result['cursor']==='1','folklore cursor committed');
$ei=$db->fetchOne("SELECT * FROM etymolog_story_import WHERE provider='erben-folklore' AND franchise_code='etymolog'");
$ee=status(api('GET','etymolog/entries/'.$ei['entry_id'],token:$editor),200,'folklore entry API');
check($ee['type']==='proverb' && $ee['source_url']==='https://cs.wikisource.org/w/index.php?oldid=1' && $ee['body']==="Fixture original verse one.\nFixture original verse two.",'folklore imported verbatim with verse layout and source');
$ed=$db->fetchOne('SELECT * FROM etymolog_calendar_day WHERE entry_id=?',[$ei['entry_id']]);
check($ed['month']===1 && $ed['day']===25 && $ed['kind']==='folklore' && $ed['name_id']===null,'historical chapter date is not inferred modern name day');
$ep=status(api('GET','etymolog/entries/'.$ei['entry_id'].'/imports',token:$editor),200,'folklore provenance API');
check($ep[0]['attribution']==='Karel Jaromír Erben; Wikizdroje','folklore correct author rather than Jirasek');
status(api('PATCH','etymolog/entries/'.$ei['entry_id'],['published'=>1],$editor),200,'verbatim imported folklore may publish with citation');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?',[$erbenJob['id']]);
$fake->responses=[$jsonResponse($erbenFixture(revision:2))];$erbenService->run((int)$erbenJob['id']);
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_calendar_day WHERE entry_id=?',[$ei['entry_id']])['n']===1,'folklore date idempotent');
$fake->responses=[$jsonResponse($erbenFixture('All rights reserved'))];
try{$erben->batch('cs','folklore',null,1);throw new LogicException('Expected licence failure');}
catch(App\Modules\Etymolog\SyncException $e){check($e->reason==='story_license_not_allowed','folklore requires actual public-domain metadata');}

$calendarProvider=new App\Modules\Etymolog\Providers\CzechNamedaysProvider($fake);
$calendarService=new App\Modules\Etymolog\EtymologSyncService($jobs,$sync,new App\Modules\Etymolog\ProviderRegistry(['czech-namedays'=>$calendarProvider]),$storyRepo,$external,$calendarRepo);
$calendarJob=status(api('POST','etymolog/sync-jobs',['title'=>'Calendar','provider'=>'czech-namedays','kind'=>'calendar','batch_size'=>500],$admin),201,'create name-day job');
$dates=[];
for($m=1;$m<=12;++$m){for($d=1;$d<=31;++$d){if(checkdate($m,$d,2000)){$dates[sprintf('%02d%02d',$d,$m)]='Testovník';}}}
$dates['0101']='Nový rok';$dates['2902']='Horymír';$dates['2412']=['Eva','Adam','Štědrý den'];
$calendarJs='function when(when) { var json_data = '.json_encode($dates,JSON_UNESCAPED_UNICODE).'; var result = json_data[when]; }';
$calendarLicense=file_get_contents(__DIR__.'/fixtures/Unlicense.txt');
$revision=str_repeat('a',40);
$calendarResponses=static fn()=>[$jsonResponse(['sha'=>$revision]),new App\Modules\Http\HttpResponse(200,$calendarLicense),new App\Modules\Http\HttpResponse(200,$calendarJs)];
$fake->responses=$calendarResponses();$result=$calendarService->run((int)$calendarJob['id']);
check($result['processed']===368 && $result['status']==='complete','all 366 dates and shared name days imported');
$ny=$db->fetchOne("SELECT d.* FROM etymolog_calendar_day d JOIN etymolog_external_record x ON x.franchise_code=d.franchise_code AND x.calendar_day_id=d.id WHERE x.provider='czech-namedays' AND d.month=1 AND d.day=1");
check($ny['kind']==='observance' && $ny['name_id']===null,'New Year is not a person name');
$cp=status(api('GET','etymolog/calendar-days/'.$ny['id'].'/imports',token:$editor),200,'calendar provenance API');
check($cp[0]['license']==='Unlicense' && $cp[0]['revision']===$revision,'calendar pins source commit and licence');
status(api('GET','etymolog/calendar-days/'.$ny['id'].'/imports',token:$other,host:'other.test'),404,'calendar import snapshots tenant scoped');
status(api('PATCH','etymolog/calendar-days/'.$ny['id'],['notes'=>'Manual note'],$editor),200,'editor can annotate imported day');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?',[$calendarJob['id']]);
$fake->responses=$calendarResponses();$calendarService->run((int)$calendarJob['id']);
check($db->fetchOne('SELECT notes FROM etymolog_calendar_day WHERE id=?',[$ny['id']])['notes']==='Manual note','calendar reimport preserves editorial data');
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_external_record WHERE provider='czech-namedays'")['n']===368,'calendar imports idempotent');
$fake->responses=$calendarResponses();$cb=$calendarProvider->batch('cs','calendar',null,1);
check(!$cb['complete'] && json_decode($cb['cursor'],true)['revision']===$revision,'calendar resume pins commit');
$fake->responses=[new App\Modules\Http\HttpResponse(200,$calendarLicense),new App\Modules\Http\HttpResponse(200,$calendarJs)];
$cb2=$calendarProvider->batch('cs','calendar',$cb['cursor'],1);
check($cb2['items'][0]['day']===2,'calendar next batch starts after previous row');
$fake->responses=[$jsonResponse(['sha'=>$revision]),new App\Modules\Http\HttpResponse(200,'different licence')];
try{$calendarProvider->batch('cs','calendar',null,1);throw new LogicException('Expected licence failure');}
catch(App\Modules\Etymolog\SyncException $e){check($e->reason==='upstream_license_changed','calendar licence fingerprint must match reviewed terms');}
$fake->responses=[$jsonResponse(['sha'=>$revision]),new App\Modules\Http\HttpResponse(200,$calendarLicense),new App\Modules\Http\HttpResponse(200,'var json_data = {"0101": runMaliciousCode()}; var result = 1;')];
try{$calendarProvider->batch('cs','calendar',null,1);throw new LogicException('Expected JS rejection');}
catch(App\Modules\Etymolog\SyncException $e){check($e->reason==='calendar_schema_changed','source JavaScript never executed');}
status(api('DELETE','etymolog/calendar-days/'.$ny['id'],token:$editor),200,'imported day may archive');
status(api('DELETE','etymolog/calendar-days/'.$ny['id'].'?force=true',token:$admin),409,'snapshot preserves calendar provenance from hard delete');
$db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?',[$calendarJob['id']]);
$fake->responses=$calendarResponses();$calendarService->run((int)$calendarJob['id']);
check((int)$db->fetchOne('SELECT deleted FROM etymolog_calendar_day WHERE id=?',[$ny['id']])['deleted']===1,'calendar tombstone survives refresh');
$cultureSeed=file_get_contents($root.'/migrations/2026-09-28-etymolog-culture-tenant.sql');
$db->getPdo()->exec($cultureSeed);$db->getPdo()->exec($cultureSeed);
check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND provider IN ('czech-namedays','erben-folklore')")['n']===2,'cultural job seed idempotent');

// Legacy migration copies original source text, never the editor's rewritten body.
$legacyEntry=$db->insert('etymolog_entry',['franchise_code'=>'etymolog','type'=>'legend','title'=>'Legacy story','body'=>'Edited text is not original']);
$legacyCitation=$db->insert('etymolog_citation',['franchise_code'=>'etymolog','entry_id'=>$legacyEntry,'source_id'=>$webSource['id'],'url'=>'https://example.org/old']);
$db->insert('etymolog_story_import',['franchise_code'=>'etymolog','entry_id'=>$legacyEntry,'source_id'=>$webSource['id'],'provider'=>'wikisource','external_id'=>'legacy-migration','revision'=>'1','source_url'=>'https://example.org/old','license'=>'PD-old-70','license_url'=>'https://example.org/licence','attribution'=>'Original author','payload'=>json_encode(['body'=>'Original recorded web text']),'content_hash'=>str_repeat('a',64),'fetched_at'=>gmdate('Y-m-d H:i:s')]);
$db->getPdo()->exec($cultureMigration);$db->getPdo()->exec($cultureMigration);
check($db->fetchOne('SELECT quotation FROM etymolog_citation WHERE id=?',[$legacyCitation])['quotation']==='Original recorded web text','legacy backfill uses source snapshot rather than edited entry');
$legacy=$db->fetchOne('SELECT body,source_url FROM etymolog_entry WHERE id=?',[$legacyEntry]);
check($legacy['body']==='Edited text is not original' && $legacy['source_url']==='https://example.org/old','legacy migration preserves editorial body while adding known web provenance');
