<?php
use App\Modules\Etymolog\{EtymologBatchRepository,EtymologRepository,EtymologSyncRepository,EtymologSyncService,ProviderRegistry,EtymologHttpWorkerService,EtymologException,SyncException};
use App\Modules\Etymolog\Providers\{CloudflareDispatchProvider,SyncBudgetProvider};

$machineKey = 'etymolog-worker-integration-secret-32-chars';
$workerCall = static function(array $body, ?string $key = null, string $host = 'ety.test', bool $internal = true) use($http,$base):array {
    $headers=['Host'=>$host];if($internal)$headers['X-Internal-Key']='etymolog-integration-internal-key';if($key!==null)$headers['X-Etymolog-Worker-Key']=$key;
    $r=$http->send(new App\Modules\Http\HttpRequest($base.'/api/etymolog/sync/worker','POST',$headers,$body));
    return ['status'=>$r->status,'json'=>json_decode($r->body,true),'raw'=>$r->body];
};
status($workerCall(['action'=>'health']),403,'worker needs separate secret even with internal key');
status($workerCall(['action'=>'health'],$machineKey,'ety.test',false),401,'worker still needs internal API authentication');
status($workerCall(['action'=>'health'],$machineKey,'other.test'),403,'worker secret cannot cross tenant boundary');
check(status($workerCall(['action'=>'health'],$machineKey),200,'worker authenticated health')['status']==='ready','health endpoint does not import');
status($workerCall(['action'=>'health','tenant'=>'other'],$machineKey),422,'worker rejects tenant injection');
status($workerCall(['action'=>'nightly','date'=>'2000-01-01'],$machineKey),422,'nightly replay outside current Prague day rejected');
status($workerCall(['action'=>'step','request_id'=>str_repeat('f',32),'step'=>-1],$machineKey),422,'worker rejects negative step');
check(status($workerCall(['action'=>'step','request_id'=>str_repeat('f',32),'step'=>0],$machineKey),200,'stale worker request handled')['status']==='idle','stale request never creates or runs a batch');
status(api('GET','etymolog/sync/worker'),401,'worker route does not expose anonymous GET');

$tenant='http-step-fixture';$repo=new EtymologBatchRepository($db,$tenant,900,900);$jobRepo=new EtymologRepository($db,$tenant,'sync-jobs');
$good=$db->insert('etymolog_sync_job',['franchise_code'=>$tenant,'title'=>'One','provider'=>'ok']);
$bad=$db->insert('etymolog_sync_job',['franchise_code'=>$tenant,'title'=>'Two','provider'=>'bad']);
$service=new EtymologHttpWorkerService($repo,new EtymologSyncService($jobRepo,new EtymologSyncRepository($db,$tenant),new ProviderRegistry(['ok'=>$okProvider,'bad'=>$badProvider])));
$pending=$repo->enqueue(null);$calls=$okProvider->calls;
$step=$service->step($pending['request_id'],0);
check($step['status']==='running'&&$step['next_step']===1&&$step['total']===2&&$okProvider->calls===$calls+1,'HTTP worker runs exactly one due job batch');
check($service->step($pending['request_id'],0)===$step&&$okProvider->calls===$calls+1,'lost response replay does not repeat provider work');
try{$service->step($pending['request_id'],2);throw new LogicException('Expected ordering error');}catch(EtymologException $e){check($e->status===409,'out of order step rejected');}
$db->insert('etymolog_sync_job',['franchise_code'=>$tenant,'title'=>'Added later','provider'=>'ok']);
$done=$service->step($pending['request_id'],1);
check($done['status']==='partial'&&$repo->status()['failed']===1&&$done['total']===2,'source failure advances batch and frozen job list excludes later additions');
check($service->step($pending['request_id'],1)===$done,'completed failed step is idempotent');
$daily=$repo->lock('worker',fn()=>$repo->nightly('2026-09-28'));
check($repo->lock('worker',fn()=>$repo->nightly('2026-09-28'))['request_id']===$daily['request_id'],'daily schedule deduplicates repeated delivery');
$service->step($daily['request_id'],0);
check($repo->lock('worker',fn()=>$repo->nightly('2026-09-28'))['request_id']===$daily['request_id'],'daily schedule does not enqueue another pass after completion');

// Completion callback failure must roll back source/cursor writes before audited failure.
$atomicTenant='http-atomic-fixture';$atomicJobs=new EtymologRepository($db,$atomicTenant,'sync-jobs');
$atomicId=$db->insert('etymolog_sync_job',['franchise_code'=>$atomicTenant,'title'=>'Atomic','provider'=>'ok']);
$atomicSync=new EtymologSyncService($atomicJobs,new EtymologSyncRepository($db,$atomicTenant),new ProviderRegistry(['ok'=>$okProvider]));
try{$atomicSync->run($atomicId,static function($result)use($db,$atomicTenant){if($result['status']!=='failed'){$db->insert('etymolog_name',['franchise_code'=>$atomicTenant,'name'=>'Must rollback','kind'=>'given']);throw new RuntimeException('Completion fixture');}});throw new LogicException('Expected transaction failure');}
catch(SyncException $e){check($e->reason==='sync_failed'&&!$db->fetchOne('SELECT id FROM etymolog_name WHERE franchise_code=?',[$atomicTenant]),'step completion and import transaction roll back together');}

$fake->responses=[new App\Modules\Http\HttpResponse(202,'{}')];
(new CloudflareDispatchProvider($fake,'https://etymolog-sync.fixture.workers.dev/dispatch',$machineKey))->launch(str_repeat('a',32));
$sent=end($fake->requests);check($sent->method==='POST'&&$sent->redirectHosts===[]&&$sent->body===['request_id'=>str_repeat('a',32)],'dispatch uses shared HTTP, fixed payload and no redirects');
try{(new CloudflareDispatchProvider($fake,'http://evil.test/dispatch',$machineKey))->launch(str_repeat('a',32));throw new LogicException('Expected URL rejection');}catch(SyncException $e){check($e->reason==='worker_configuration_invalid','dispatch rejects non-allowlisted endpoint');}
$fake->responses=[new App\Modules\Http\HttpResponse(200,'{}')];
(new SyncBudgetProvider($fake,100))->send(new App\Modules\Http\HttpRequest('https://example.test',timeoutMs:30000));
check(end($fake->requests)->timeoutMs<=100,'worker clips upstream timeout to total request budget');
try{(new SyncBudgetProvider($fake,0))->send(new App\Modules\Http\HttpRequest('https://example.test'));throw new LogicException('Expected exhausted budget');}catch(SyncException $e){check($e->reason==='worker_time_budget_exceeded','exhausted worker budget stops further network requests');}

$raceRepo=new EtymologBatchRepository($db,'dispatch-race-fixture');
$raceService=new App\Modules\Etymolog\EtymologBackgroundService($raceRepo,$atomicSync,static function($id)use($raceRepo){$raceRepo->update($id,['status'=>'running']);throw new RuntimeException('Lost queue acknowledgement');});
check($raceService->start(null)['status']==='running'&&$raceRepo->status()['error_code']===null,'lost dispatch acknowledgement cannot fail a worker already running');

// Durable cooldown, bounded retries and unchanged cursor on a throttled source.
$rateTenant='http-rate-fixture';$rateRepo=new EtymologBatchRepository($db,$rateTenant,900,900);
$rateId=$db->insert('etymolog_sync_job',['franchise_code'=>$rateTenant,'title'=>'Limited weekly job','provider'=>'limited','interval_seconds'=>604800,'cursor'=>'kept']);
$rateProvider=new class implements App\Modules\Etymolog\Contracts\BatchProvider {
    public int $calls=0;public bool $limited=true;
    public function batch(string $language,string $kind,?string $cursor,int $limit):array {
        ++$this->calls;if($cursor==='advanced')return ['items'=>[],'complete'=>true,'cursor'=>null];if($cursor!=='kept')throw new LogicException('Cursor lost');
        if($this->limited)throw new SyncException('upstream_rate_limited',1800);
        return ['items'=>[],'complete'=>false,'cursor'=>'advanced'];
    }
};
$rateService=new EtymologHttpWorkerService($rateRepo,new EtymologSyncService(new EtymologRepository($db,$rateTenant,'sync-jobs'),new EtymologSyncRepository($db,$rateTenant),new ProviderRegistry(['limited'=>$rateProvider])));
$id=$rateRepo->enqueue(null)['request_id'];$r=$rateService->step($id,0);$state=$rateRepo->status();
check($r['status']==='running'&&$r['next_step']===0&&$r['retry_after']>=1798&&$state['failed']===0&&$state['retry_count']==1,'429 defers same step without counting a failed job');
$rateJob=$db->fetchOne('SELECT * FROM etymolog_sync_job WHERE id=?',[$rateId]);
check($rateJob['cursor']==='kept'&&strtotime($rateJob['next_run_at'].' UTC')<=time()+1800,'429 retains cursor and uses cooldown instead of weekly interval');
$rateService->step($id,0);check($rateProvider->calls===1,'premature delivery makes no upstream calls during cooldown');
$db->update('etymolog_sync_batch',['heartbeat_at'=>gmdate('Y-m-d H:i:s',time()-3600)],'franchise_code=?',[$rateTenant]);
check($rateRepo->status()['status']==='running','intentional cooldown is not mistaken for a crashed worker');
$due=static function()use($db,$rateTenant,$rateId){$db->update('etymolog_sync_batch',['retry_at'=>'2000-01-01 00:00:00','heartbeat_at'=>gmdate('Y-m-d H:i:s')],'franchise_code=?',[$rateTenant]);$db->update('etymolog_sync_job',['next_run_at'=>null],'id=?',[$rateId]);};
$due();$rateProvider->limited=false;$r=$rateService->step($id,0);
check($r['status']==='running'&&$r['next_step']===1&&$rateRepo->status()['completed']===0&&$rateRepo->status()['failed']===0&&$rateRepo->status()['retry_at']===null,'successful retry advances the batch and clears cooldown without finishing the job');
check($db->fetchOne('SELECT `cursor` FROM etymolog_sync_job WHERE id=?',[$rateId])['cursor']==='advanced','successful retry commits new cursor');
check($rateService->step($id,1)['status']==='complete','continuation after successful retry completes the source pass');
$rateProvider->limited=true;$db->update('etymolog_sync_job',['cursor'=>'kept','next_run_at'=>null],'id=?',[$rateId]);$id=$rateRepo->enqueue(null)['request_id'];
for($attempt=0;$attempt<3;$attempt++){$due();$r=$rateService->step($id,0);}
check($r['status']==='partial'&&$rateRepo->status()['completed']===1&&$rateRepo->status()['failed']===1,'persistent 429 stops after initial attempt plus two retries');

$budgetTenant='http-budget-fixture';$budgetRepo=new EtymologBatchRepository($db,$budgetTenant,900,900);
$budgetId=$db->insert('etymolog_sync_job',['franchise_code'=>$budgetTenant,'title'=>'Slow source','provider'=>'budget','interval_seconds'=>3600,'cursor'=>'kept']);
$budgetProvider=new class implements App\Modules\Etymolog\Contracts\BatchProvider {
    public int $calls=0;
    public function batch(string $language,string $kind,?string $cursor,int $limit):array {
        check($cursor==='kept','time-budget retry retains source cursor');
        if(++$this->calls===1)throw new SyncException('worker_time_budget_exceeded',60);
        return ['items'=>[],'complete'=>true,'cursor'=>null];
    }
};
$budgetService=new EtymologHttpWorkerService($budgetRepo,new EtymologSyncService(new EtymologRepository($db,$budgetTenant,'sync-jobs'),new EtymologSyncRepository($db,$budgetTenant),new ProviderRegistry(['budget'=>$budgetProvider])));
$budgetBatch=$budgetRepo->enqueue(null)['request_id'];$budgetResult=$budgetService->step($budgetBatch,0);
check($budgetResult['status']==='running'&&$budgetResult['next_step']===0&&$budgetResult['retry_after']>=58&&$budgetRepo->status()['failed']===0,'time-budget exhaustion retries the same step instead of failing the whole source');
$budgetJob=$db->fetchOne('SELECT `cursor`,next_run_at FROM etymolog_sync_job WHERE id=?',[$budgetId]);
check($budgetJob['cursor']==='kept'&&strtotime($budgetJob['next_run_at'].' UTC')<=time()+61,'time-budget retry preserves cursor and schedules a short cooldown');
$db->update('etymolog_sync_batch',['retry_at'=>'2000-01-01 00:00:00'],'franchise_code=?',[$budgetTenant]);
$db->update('etymolog_sync_job',['next_run_at'=>null],'id=?',[$budgetId]);
check($budgetService->step($budgetBatch,0)['status']==='complete'&&$budgetProvider->calls===2,'transient slow source finishes after bounded retry');

$clockMs=0.0;$sleeps=[];$timedFake=new class implements App\Modules\Http\Contracts\HttpClient {
    public function send(App\Modules\Http\HttpRequest $r):App\Modules\Http\HttpResponse{return new App\Modules\Http\HttpResponse(200,'{}');}
    public function sendAll(array $requests,int $budgetMs=6000,int $concurrency=4):array{return [];}
};
$paced=new App\Modules\Etymolog\Providers\WikimediaHttpProvider($timedFake,static function()use(&$clockMs){return $clockMs;},static function($microseconds)use(&$clockMs,&$sleeps){$sleeps[]=$microseconds;$clockMs+=$microseconds/1000;});
foreach(['https://cs.wikipedia.org/w/api.php','https://en.wiktionary.org/w/api.php','https://cs.wikisource.org/w/api.php','https://csu.gov.cz/'] as $u)$paced->send(new App\Modules\Http\HttpRequest($u));
check($sleeps===[1000000,1000000],'Wikimedia editions share one request per second, other sources are not paced');
$fake->responses=[new App\Modules\Http\HttpResponse(200,'{"error":{"code":"maxlag"}}',retryAfter:600)];
try{App\Modules\Etymolog\Providers\ProviderHttp::json($fake,'https://cs.wikipedia.org/w/api.php');throw new LogicException('Expected maxlag');}catch(SyncException $e){check($e->reason==='upstream_rate_limited'&&$e->retryAfter===600,'MediaWiki maxlag respects Retry-After even on HTTP 200');}
