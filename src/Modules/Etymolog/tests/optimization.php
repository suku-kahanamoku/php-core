<?php
// All IO below is confined to the disposable test DB and its temporary directory.
use App\Modules\Etymolog\{EtymologSnapshotRepository,EtymologBatchRepository,EtymologHttpWorkerService,EtymologRepository,EtymologSyncRepository,EtymologSyncService,ProviderRegistry,SyncException};
use App\Modules\Etymolog\Providers\{PolandPeselProvider,CsuBabyNamesProvider};

$cacheRoot=getenv('ETYMOLOG_TEST_DIR').'/snapshots';
$cache=new EtymologSnapshotRepository($cacheRoot,'cache-fixture');
$cachedPesel=new PolandPeselProvider($fake,$cache);
$cacheCsv="Nazwisko aktualne,Liczba\r\nNOWAK,10\r\nŻÓŁĆ-KOWALSKI,9\r\nNOVÁK,8\r\n";
$cacheHash=hash('sha256',$cacheCsv);
$cacheUrl=$resMeta['attributes']['csv_file_url'];
$fake->responses=[$jsonResponse($dataset),$jsonResponse($listMeta),$jsonResponse(['data'=>$resMeta]),new App\Modules\Http\HttpResponse(200,$cacheCsv)];
$cachedFirst=$cachedPesel->batch('pl','surname_male',null,1);
$cachedState=json_decode($cachedFirst['cursor'],true);
check($cachedState['position']===strlen("Nazwisko aktualne,Liczba\r\nNOWAK,10\r\n")&&$cache->get($cacheUrl,$cacheHash)===$cacheCsv,'first statistics batch stores immutable file and exact CSV byte position');
$cachedPesel=new PolandPeselProvider($fake,new EtymologSnapshotRepository($cacheRoot,'cache-fixture'));
$fake->responses=[$jsonResponse($dataset),$jsonResponse(['data'=>$resMeta])];$beforeCacheCalls=count($fake->requests);
$cachedSecond=$cachedPesel->batch('pl','surname_male',$cachedFirst['cursor'],1);
check(count($fake->requests)===$beforeCacheCalls+2&&$cachedSecond['items'][0]['name']==='ŻÓŁĆ-KOWALSKI','new PHP request reads cached CSV and seeks directly, only licence/resource metadata requested');
$fake->responses=[$jsonResponse($dataset),$jsonResponse(['data'=>$resMeta])];
$cachedEnd=$cachedPesel->batch('pl','surname_male',$cachedSecond['cursor'],1);
check($cachedEnd['complete']&&$cachedEnd['items'][0]['name']==='NOVÁK'&&$cachedEnd['items'][0]['payload']['row']===4,'UTF-8 byte cursor keeps exact row provenance and reaches real EOF');
$legacyCachedState=$cachedState;unset($legacyCachedState['position']);
$fake->responses=[$jsonResponse($dataset),$jsonResponse(['data'=>$resMeta])];
$legacyCached=$cachedPesel->batch('pl','surname_male',json_encode($legacyCachedState),1);
check($legacyCached['items']===$cachedSecond['items']&&isset(json_decode($legacyCached['cursor'],true)['position']),'old row-offset cursor upgrades without losing or repeating a row');
check((new EtymologSnapshotRepository($cacheRoot,'another-tenant'))->get($cacheUrl,$cacheHash)===null&&$cache->get('https://example.org/other.csv',$cacheHash)===null,'cached snapshots isolate tenant and original URL');
$invalidCachedState=$cachedState;$invalidCachedState['position']=1;
$fake->responses=[$jsonResponse($dataset),$jsonResponse(['data'=>$resMeta])];
try{$cachedPesel->batch('pl','surname_male',json_encode($invalidCachedState),1);throw new LogicException('Expected invalid position');}
catch(SyncException $e){check($e->reason==='invalid_provider_cursor','byte cursor cannot seek into the CSV header');}
$badCacheDataset=$dataset;$badCacheDataset['data']['attributes']['license_name']='No reuse';
$fake->responses=[$jsonResponse($badCacheDataset)];
try{$cachedPesel->batch('pl','surname_male',$cachedFirst['cursor'],1);throw new LogicException('Expected licence failure');}
catch(SyncException $e){check($e->reason==='upstream_license_changed','cache never bypasses fresh licence validation');}
$cacheFile=$cacheRoot.'/'.hash('sha256','cache-fixture').'/'.hash('sha256',$cacheUrl).'-'.$cacheHash.'.snapshot';
file_put_contents($cacheFile,'damaged');
$fake->responses=[$jsonResponse($dataset),$jsonResponse(['data'=>$resMeta]),new App\Modules\Http\HttpResponse(200,$cacheCsv)];
$recoveredCache=$cachedPesel->batch('pl','surname_male',$cachedFirst['cursor'],1);
check($recoveredCache['items']===$cachedSecond['items']&&$cache->get($cacheUrl,$cacheHash)===$cacheCsv,'corrupt local file is discarded and refetched with matching snapshot hash');
$expiredCache=new EtymologSnapshotRepository($cacheRoot,'cache-fixture',static fn()=>time()+604801);
check($expiredCache->get($cacheUrl,$cacheHash)===null,'cached source files have bounded retention');
$changedCacheCsv=str_replace('NOWAK,10','NOWAK,11',$cacheCsv);
$fake->responses=[$jsonResponse($dataset),$jsonResponse($listMeta),$jsonResponse(['data'=>$resMeta]),new App\Modules\Http\HttpResponse(200,$changedCacheCsv)];
$freshPass=$cachedPesel->batch('pl','surname_male',null,1);
check($freshPass['items'][0]['occurrence']['count']===11&&$freshPass['items'][0]['revision']!==$cacheHash,'new full pass fetches current upstream release instead of reusing stale cached data');
unlink($cacheFile);
$fake->responses=[$jsonResponse($dataset),$jsonResponse(['data'=>$resMeta]),new App\Modules\Http\HttpResponse(200,$changedCacheCsv)];
try{$cachedPesel->batch('pl','surname_male',$cachedFirst['cursor'],1);throw new LogicException('Expected changed pinned file');}
catch(SyncException $e){check($e->reason==='statistics_snapshot_changed','evicted snapshot cannot be silently replaced by changed upstream bytes');}
$cachedCsu=new CsuBabyNamesProvider($fake,$cache);
$fake->responses=[new App\Modules\Http\HttpResponse(200,$csuTerms),new App\Modules\Http\HttpResponse(200,$xlsx)];
$cachedCsuFirst=$cachedCsu->batch('cs','births_2025',null,1);
$cachedCsu=new CsuBabyNamesProvider($fake,new EtymologSnapshotRepository($cacheRoot,'cache-fixture'));
$fake->responses=[new App\Modules\Http\HttpResponse(200,$csuTerms)];$beforeCacheCalls=count($fake->requests);
$cachedCsuNext=$cachedCsu->batch('cs','births_2025',$cachedCsuFirst['cursor'],1);
check(count($fake->requests)===$beforeCacheCalls+1&&$cachedCsuNext['items'][0]['name']==='TEST1NAME2','CSU continuation reuses pinned XLSX and still checks current licence');

$planTenant='optimization-plan-fixture';$planRepo=new EtymologBatchRepository($db,$planTenant);
$db->insert('etymolog_name',['franchise_code'=>$planTenant,'name'=>'Existing','kind'=>'given']);
$planJob=static function(string $provider,string $kind,string $language='cs',array $extra=[])use($db,$planTenant):int{return $db->insert('etymolog_sync_job',$extra+['franchise_code'=>$planTenant,'provider'=>$provider,'kind'=>$kind,'language'=>$language,'title'=>$provider.' '.$kind]);};
$planStats=$planJob('poland-pesel','surname_male','pl');
$planInventory=$planJob('wikidata','given');
$planAlias=$planJob('wiktionary-cs','given_priority');
$planRegular=$planJob('wiktionary-cs','given');
$planSurname=$planJob('wiktionary-cs','surname');
$planFrench=$planJob('wiktionary-fr','given');
$planWiki=$planJob('wikipedia-names','culture');
$planIds=$planRepo->dueIds();
check(!in_array($planAlias,$planIds,true)&&in_array($planRegular,$planIds,true)&&in_array($planSurname,$planIds,true)&&in_array($planFrench,$planIds,true),'dictionary alias is skipped but separate kinds and editions are preserved');
check(array_slice($planIds,-2)===[$planInventory,$planStats],'all text jobs precede inventory expansion and statistics');
$db->update('etymolog_sync_job',['next_run_at'=>'2099-01-01 00:00:00'],'id=?',[$planRegular]);
check(!in_array($planAlias,$planRepo->dueIds(),true)&&!in_array($planRegular,$planRepo->dueIds(),true),'due alias cannot bypass regular dictionary refresh interval');
$db->update('etymolog_sync_job',['enabled'=>0],'id=?',[$planRegular]);
check(in_array($planAlias,$planRepo->dueIds(),true),'legacy alias remains usable when it is the only enabled task');
$db->update('etymolog_sync_job',['enabled'=>1,'next_run_at'=>null],'id=?',[$planRegular]);
$planBefore=$db->fetchOne('SELECT enabled,`cursor` FROM etymolog_sync_job WHERE id=?',[$planAlias]);
check((int)$planBefore['enabled']===1&&$planBefore['cursor']===null,'planning deduplication leaves job configuration and cursor intact');

$rotationTenant='optimization-rotation-fixture';$rotationRepo=new EtymologBatchRepository($db,$rotationTenant);
$rotationStats=$db->insert('etymolog_sync_job',['franchise_code'=>$rotationTenant,'provider'=>'poland-pesel','kind'=>'surname_male','title'=>'Statistics']);
$rotationEty=$db->insert('etymolog_sync_job',['franchise_code'=>$rotationTenant,'provider'=>'wikipedia-names','kind'=>'etymologies','title'=>'Etymologies']);
$rotationCulture=$db->insert('etymolog_sync_job',['franchise_code'=>$rotationTenant,'provider'=>'wikipedia-names','kind'=>'culture','title'=>'Culture']);
$rotationProvider=new class implements App\Modules\Etymolog\Contracts\BatchProvider {
 public array $visits=[];
 public function batch(string $language,string $kind,?string $cursor,int $limit):array{$this->visits[]=[$kind,$cursor];$done=$kind==='surname_male'||$cursor==='1';return ['items'=>[],'cursor'=>$done?null:'1','complete'=>$done];}
};
$rotationSync=new EtymologSyncService(new EtymologRepository($db,$rotationTenant,'sync-jobs'),new EtymologSyncRepository($db,$rotationTenant),new ProviderRegistry(['wikipedia-names'=>$rotationProvider,'poland-pesel'=>$rotationProvider]));
$rotationWorker=new EtymologHttpWorkerService($rotationRepo,$rotationSync);
$rotationId=$rotationRepo->enqueue(null)['request_id'];
$rotationFirst=$rotationWorker->step($rotationId,0);
check($rotationWorker->step($rotationId,0)===$rotationFirst&&count($rotationProvider->visits)===1,'replayed queue step does not repeat or rotate work twice');
for($index=1;$index<5;$index++){$rotationDone=$rotationWorker->step($rotationId,$index);}
check($rotationProvider->visits===[['etymologies',null],['culture',null],['etymologies','1'],['culture','1'],['surname_male',null]],'etymology and culture alternate batches and both finish before statistics');
check($rotationDone['status']==='complete'&&$rotationRepo->status()['completed']===3&&$rotationDone['next_step']===5,'rotation preserves full-pass job counts and independent batch index');

// Upgrade an already running old statistics-first queue, without resetting any source cursor.
foreach([$rotationStats,$rotationEty,$rotationCulture] as $id){$db->update('etymolog_sync_job',['next_run_at'=>null],'id=?',[$id]);}
$db->update('etymolog_sync_job',['cursor'=>'1'],'id=?',[$rotationStats]);
$rotationId=$rotationRepo->enqueue(null)['request_id'];
$rotationRepo->update($rotationId,['status'=>'running','total'=>3,'completed'=>0,'processed'=>500,'pending_jobs'=>json_encode(['jobs'=>[$rotationStats,$rotationEty,$rotationCulture],'step'=>40])]);
$rotationProvider->visits=[];
$upgraded=$rotationWorker->step($rotationId,40);
check($rotationProvider->visits===[['etymologies',null]]&&$upgraded['next_step']===41&&$rotationRepo->status()['processed']===500,'active old run switches to text first at next step while preserving batch sequence and counters');
check($db->fetchOne('SELECT `cursor` FROM etymolog_sync_job WHERE id=?',[$rotationStats])['cursor']==='1','postponing statistics keeps its saved source progress');

// Indexed identity must preserve the existing case/accent/kind/tenant contract.
$identityTenant='indexed-name-fixture';
$identityRepo=new App\Modules\Etymolog\EtymologNameRepository($db,$identityTenant);
$identityId=$db->insert('etymolog_name',['franchise_code'=>$identityTenant,'name'=>'  ŽANETA  ','kind'=>'given']);
check($identityRepo->resolve('žaneta','given','cs','CZ','identity-1')===$identityId,'indexed identity finds legacy uppercase names with surrounding spaces');
check($identityRepo->resolve('Zaneta','given','cs','CZ','identity-2')!==$identityId&&$identityRepo->resolve('Žaneta','surname','cs','CZ','identity-3')!==$identityId,'indexed identity preserves accents and separates given names from surnames');
$foreignIdentity=new App\Modules\Etymolog\EtymologNameRepository($db,'indexed-other-fixture');
check($foreignIdentity->resolve('Žaneta','given','cs','CZ','identity-1')!==$identityId,'indexed identity remains tenant scoped');
$db->update('etymolog_name',['name'=>'Renamed'],'id=?',[$identityId]);
check($db->fetchOne('SELECT normalized_name FROM etymolog_name WHERE id=?',[$identityId])['normalized_name']==='renamed','editorial rename updates generated lookup key automatically');
for($i=0;$i<1000;$i++){$db->insert('etymolog_name',['franchise_code'=>$identityTenant,'name'=>'Fixture'.$i,'kind'=>'given']);}
$identityPlan=$db->fetchOne('EXPLAIN SELECT id,name,deleted FROM etymolog_name WHERE franchise_code=? AND kind=? AND normalized_name=LOWER(TRIM(?)) ORDER BY deleted DESC,(BINARY name=BINARY UPPER(name)),id LIMIT 1',[$identityTenant,'given','RENAMED']);
check($identityPlan['key']==='idx_etymolog_name_identity'&&(int)$identityPlan['rows']<=2,'MySQL uses narrow indexed lookup instead of scanning tenant names for each imported row');

// First pass after a complete reset must not finish empty enrichment before names exist.
$bootTenant='empty-bootstrap-fixture';$bootRepo=new EtymologBatchRepository($db,$bootTenant);
$bootText=$db->insert('etymolog_sync_job',['franchise_code'=>$bootTenant,'provider'=>'wikipedia-names','kind'=>'etymologies','title'=>'Text']);
$bootNames=$db->insert('etymolog_sync_job',['franchise_code'=>$bootTenant,'provider'=>'wikidata','kind'=>'given','title'=>'Names']);
$bootCalendar=$db->insert('etymolog_sync_job',['franchise_code'=>$bootTenant,'provider'=>'czech-namedays','kind'=>'calendar','title'=>'Calendar']);
$bootCsu=$db->insert('etymolog_sync_job',['franchise_code'=>$bootTenant,'provider'=>'csu-baby-names','kind'=>'births_2025','title'=>'Czech births']);
check($bootRepo->dueIds()===[$bootCalendar,$bootCsu,$bootNames,$bootText],'empty archive bootstraps Czech calendar and names before text discovery');
$bootId=$bootRepo->enqueue(null)['request_id'];$bootBatch=$bootRepo->prepareSteps($bootId);
$db->insert('etymolog_name',['franchise_code'=>$bootTenant,'name'=>'Imported','kind'=>'given']);
$bootRepo->finishStep($bootBatch,['status'=>'success','processed'=>1]);
$bootState=$bootRepo->progress($bootRepo->status());
check($bootState['jobs']===[$bootCsu,$bootCalendar,$bootNames,$bootText]&&$bootState['priorities'][$bootNames]===-1,'bootstrap priority survives later steps after first name is imported');
check($bootRepo->dueIds()===[$bootText,$bootNames,$bootCalendar,$bootCsu],'subsequent plans restore text-first priority for populated archive');
