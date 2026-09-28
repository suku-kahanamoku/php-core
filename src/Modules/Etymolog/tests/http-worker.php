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
