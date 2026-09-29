<?php
// Real disposable SQL and fake providers: no network or application DB synchronization.
$passTenant='full-pass-fixture';
$passRepo=new App\Modules\Etymolog\EtymologBatchRepository($db,$passTenant,900,900);
$passJobs=new App\Modules\Etymolog\EtymologRepository($db,$passTenant,'sync-jobs');
$passProvider=new class implements App\Modules\Etymolog\Contracts\BatchProvider {
    public array $cursors=[];
    public function batch(string $language,string $kind,?string $cursor,int $limit):array {
        $this->cursors[]=$cursor;$offset=$cursor===null?0:(int)$cursor;
        $items=$offset===0?[]:[['external_id'=>'Q88000'.$offset,'revision'=>'1','name'=>'Import '.$offset,'language'=>'cs','source_url'=>'https://www.wikidata.org/wiki/Q88000'.$offset,'payload'=>[]]];
        return ['items'=>$items,'scanned'=>1,'cursor'=>$offset<2?(string)($offset+1):null,'complete'=>$offset===2];
    }
};
$passJob=$db->insert('etymolog_sync_job',['franchise_code'=>$passTenant,'provider'=>'paged','title'=>'Full pass','interval_seconds'=>86400]);
$passSync=new App\Modules\Etymolog\EtymologSyncService($passJobs,new App\Modules\Etymolog\EtymologSyncRepository($db,$passTenant),new App\Modules\Etymolog\ProviderRegistry(['paged'=>$passProvider]));
$passHttp=new App\Modules\Etymolog\EtymologHttpWorkerService($passRepo,$passSync);
$passId=$passRepo->enqueue(null)['request_id'];
$step=$passHttp->step($passId,0);
check($step['status']==='running'&&$step['next_step']===1&&$step['total']===1&&$passRepo->status()['completed']===0,'empty intermediate batch advances step but keeps job running');
check($passHttp->step($passId,0)===$step&&count($passProvider->cursors)===1,'lost intermediate response replay never imports the batch twice');
check($passRepo->enqueue(null)['request_id']===$passId,'another button click reuses the full active pass');
$jobAfterFirst=$passJobs->findById($passJob);
check($jobAfterFirst['cursor']==='1'&&strtotime($jobAfterFirst['next_run_at'].' UTC')<=time(),'daily job continues immediately until its pass completes');
// Reconstruct services to represent separate stateless PHP HTTP requests.
$passHttp=new App\Modules\Etymolog\EtymologHttpWorkerService(new App\Modules\Etymolog\EtymologBatchRepository($db,$passTenant,900,900),$passSync);
$step=$passHttp->step($passId,1);
check($step['next_step']===2&&$passRepo->status()['processed']===1&&$passRepo->status()['completed']===0,'next request resumes durable cursor, counts items across batches and retains same job');
$done=$passHttp->step($passId,2);
check($done['status']==='complete'&&$done['next_step']===3&&$passRepo->status()['processed']===2&&$passRepo->status()['completed']===1,'one run completes all three batches with one completed job');
check($passProvider->cursors===[null,'1','2']&&$passJobs->findById($passJob)['cursor']===null,'all source pages imported exactly once without restarting completed source');
check(strtotime($passJobs->findById($passJob)['next_run_at'].' UTC')>=time()+86398,'refresh interval starts only after the final batch');
check($passHttp->step($passId,3)===$done&&count($passProvider->cursors)===3,'extra queue delivery cannot start another source pass');
$passNext=$passRepo->enqueue(null)['request_id'];
check($passHttp->step($passNext,0)['status']==='complete'&&count($passProvider->cursors)===3,'new run respects not-yet-due source interval');

// Same full pass in the detached process / CLI cron, with source idempotency.
$db->update('etymolog_sync_job',['next_run_at'=>null],'id=?',[$passJob]);
$passProvider->cursors=[];
$passBackground=new App\Modules\Etymolog\EtymologBackgroundService($passRepo,$passSync,static function(){});
$cli=$passBackground->work();
check($cli['status']==='complete'&&$cli['completed']===1&&$cli['step_index']===3&&$cli['processed']===2&&$passProvider->cursors===[null,'1','2'],'CLI cron drains every batch and updates durable progress');
check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_name WHERE franchise_code=?',[$passTenant])['n']===2,'repeated full pass does not duplicate imported names');
$db->update('etymolog_sync_job',['next_run_at'=>null,'cursor'=>'1'],'id=?',[$passJob]);
$passProvider->cursors=[];$single=$passSync->runPass($passJob);
check($single['status']==='complete'&&$single['batches']===2&&$single['processed']===2&&$passProvider->cursors===['1','2'],'CLI --job resumes saved cursor and drains the remaining batches');

// Protect a full run from providers reporting the same cursor forever.
$stalled=new class implements App\Modules\Etymolog\Contracts\BatchProvider {
 public int $calls=0;
 public function batch(string $language,string $kind,?string $cursor,int $limit):array {++$this->calls;return ['items'=>[],'cursor'=>$cursor,'complete'=>false];}
};
$stalledId=$db->insert('etymolog_sync_job',['franchise_code'=>$passTenant,'provider'=>'stalled','title'=>'Broken source','cursor'=>'unchanged']);
$healthyId=$db->insert('etymolog_sync_job',['franchise_code'=>$passTenant,'provider'=>'paged','title'=>'Healthy source','cursor'=>'2']);
$guardedSync=new App\Modules\Etymolog\EtymologSyncService($passJobs,new App\Modules\Etymolog\EtymologSyncRepository($db,$passTenant),new App\Modules\Etymolog\ProviderRegistry(['stalled'=>$stalled,'paged'=>$passProvider]));
$guarded=new App\Modules\Etymolog\EtymologHttpWorkerService($passRepo,$guardedSync);
$guardedId=$passRepo->enqueue(null)['request_id'];$failedStep=$guarded->step($guardedId,0);
check($failedStep['status']==='running'&&$passRepo->status()['failed']===1&&$stalled->calls===1&&$passJobs->findById($stalledId)['last_error']==='provider_cursor_stalled','non-advancing provider fails once and keeps its saved cursor');
check($guarded->step($guardedId,1)['status']==='partial'&&$passRepo->status()['completed']===2,'broken source does not prevent remaining jobs from finishing');

// Existing names receive text evidence before inventory expansion.
$orderTenant='pass-order-fixture';$orderRepo=new App\Modules\Etymolog\EtymologBatchRepository($db,$orderTenant);
$db->insert('etymolog_name',['franchise_code'=>$orderTenant,'name'=>'Existing','kind'=>'given']);
$enrichment=$db->insert('etymolog_sync_job',['franchise_code'=>$orderTenant,'provider'=>'wikipedia-names','title'=>'Older enrichment']);
$inventory=$db->insert('etymolog_sync_job',['franchise_code'=>$orderTenant,'provider'=>'czech-namedays','title'=>'Later inventory']);
check($orderRepo->dueIds()===[$enrichment,$inventory],'text enrichment precedes inventory expansion');

// Deployed requests using the legacy JSON list remain consumable after code upgrade.
$db->update('etymolog_sync_job',['next_run_at'=>null,'cursor'=>'1'],'id=?',[$passJob]);
$legacyId=$passRepo->enqueue(null)['request_id'];
$passRepo->update($legacyId,['status'=>'running','total'=>2,'completed'=>1,'pending_jobs'=>json_encode([$healthyId,$passJob])]);
$legacyStep=$passHttp->step($legacyId,1);
check($legacyStep['next_step']===2&&$passRepo->status()['completed']===1,'legacy job list upgrades to separate step index while keeping current job');
check($passHttp->step($legacyId,2)['status']==='complete','upgraded active request finishes its remaining source batches');
