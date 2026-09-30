<?php
/** Included after reset tests, only inside the disposable schema fixture. */
if (!isset($pdo,$dsn) || !str_starts_with($dsn,'mysql:unix_socket=/tmp/php-core-schema-test.')) { throw new RuntimeException('Disposable DB required'); }
$czReset=static function()use($pdo):array {
    $statement=$pdo->query(file_get_contents(dirname(__DIR__).'/migrations/etymolog_reset_cz.sql'));
    $result=[];
    do { if($statement->columnCount()){$result=$statement->fetchAll(PDO::FETCH_ASSOC);} } while($statement->nextRowset());
    return $result[0];
};
resetFixture('etymolog','etymolog_sync_job',['title'=>'Foreign inventory','provider'=>'poland-pesel','language'=>'pl','kind'=>'surname_male','enabled'=>1]);
resetFixture('etymolog','etymolog_sync_job',['title'=>'Foreign edition','provider'=>'wiktionary-fr','language'=>'cs','kind'=>'given','enabled'=>1]);
resetFixture('etymolog','etymolog_sync_job',['title'=>'Legacy alias','provider'=>'wiktionary-cs','language'=>'cs','kind'=>'given_priority','enabled'=>1]);
resetFixture('etymolog','etymolog_name',['name'=>'Foreign','kind'=>'surname']);
$czBefore=snapshot();
$busyKey='ety-worker:'.substr(hash('sha256','etymolog'),0,48);
$lockConnection->prepare('SELECT GET_LOCK(?,0)')->execute([$busyKey]);
verify($czReset()['result']==='SKIPPED_BUSY' && snapshot()===$czBefore,'CZ reset refuses concurrent worker and preserves all rows');
$lockConnection->prepare('SELECT RELEASE_LOCK(?)')->execute([$busyKey]);
resetFixture('etymolog','etymolog_sync_batch',['request_id'=>str_repeat('c',32),'status'=>'running','created_at'=>'2026-01-01 00:00:00','heartbeat_at'=>'2026-01-01 00:00:00']);
$busyBefore=snapshot();
verify($czReset()['result']==='SKIPPED_BUSY'&&snapshot()===$busyBefore,'CZ reset refuses active HTTP queue between steps');
$pdo->exec("UPDATE etymolog_sync_batch SET status='partial' WHERE franchise_code='etymolog'");
verify($czReset()['result']==='RESET','CZ content reset succeeds');
foreach(array_merge($resetTables,['source']) as $suffix){
 verify(rows("SELECT * FROM etymolog_$suffix WHERE franchise_code='etymolog'")===[],'CZ reset removes all '.$suffix.' content');
}
verify((int)$pdo->query("SELECT COUNT(*) FROM etymolog_sync_job WHERE franchise_code='etymolog'")->fetchColumn()===14,'eleven Czech seed definitions and three valid existing Czech fixtures remain');
verify(rows("SELECT id FROM etymolog_sync_job WHERE franchise_code='etymolog' AND (language<>'cs' OR provider IN ('wiktionary-fr','poland-pesel') OR (provider='wiktionary' AND kind<>'surname') OR kind LIKE '%priority')")===[],'foreign jobs and duplicate aliases removed entirely');
$czAfter=snapshot();
foreach(['user','role','etymolog_sync_schedule'] as $t){verify($czAfter[$t]===$czBefore[$t],'CZ reset preserves '.$t);}
foreach($beforeOther as $suffix=>$data){verify(rows("SELECT * FROM etymolog_$suffix WHERE franchise_code='reset-other' ORDER BY 1")===$data,'CZ reset preserves other tenant '.$suffix);}
verify($czReset()['result']==='RESET'&&snapshot()===$czAfter,'CZ reset is repeatable');
apply('etymolog_seed');
verify(snapshot()===$czAfter,'Czech seed does not recreate foreign jobs or overwrite preserved settings');
