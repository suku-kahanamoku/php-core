<?php
declare(strict_types=1);
// Offline: no bootstrap, PDO, env files or HTTP client.
require __DIR__.'/../../src/Utils/QueryPolicy.php';
require __DIR__.'/../../src/Utils/InternalAuth.php';
require __DIR__.'/../../src/Modules/Router/Request.php';
use App\Utils\QueryPolicy;
use App\Utils\InternalAuth;
use App\Modules\Router\Request;
$checks=[];
function checkAudit(string $id,bool $ok):void {global $checks;$checks[]=['id'=>$id,'result'=>$ok?'PASS':'FAIL'];}
try {
    $_ENV['INTERNAL_API_KEY']='synthetic-audit-key-never-used-in-production';
    foreach (['', 'wrong', 'synthetic-audit-key-never-used-in-production-x'] as $token) {
        $r=(new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Request::class,'headers'))->setValue($r,['x-internal-key'=>$token]);
        checkAudit('CORE-AUTH-'.count($checks),!InternalAuth::check($r));
    }
    $r=(new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(Request::class,'headers'))->setValue($r,['x-internal-key'=>$_ENV['INTERNAL_API_KEY']]);
    checkAudit('CORE-AUTH-CONTROL',InternalAuth::check($r));
    for($i=0;$i<128;$i++) {
        $raw=json_encode(['tenant_id'=>99,'deleted'=>1,'password'=>['$regex'=>'.*'],'name'=>['$eq'=>'audit-'.$i],'injected_'.$i=>'OR 1=1']);
        $safe=json_decode(QueryPolicy::filter($raw,['name','tenant_id'],['tenant_id'=>31,'deleted'=>0]),true);
        checkAudit('CORE-TENANT-'.$i,$safe['tenant_id']===31&&$safe['deleted']===0&&!isset($safe['password'])&&!isset($safe['injected_'.$i]));
    }
    foreach(['password DESC','id; DROP TABLE users','[ {"password":1} ]','__proto__ ASC'] as $raw)
        checkAudit('CORE-SORT-'.count($checks),QueryPolicy::sort($raw,['id','name'])==='');
    $data=QueryPolicy::fields(['id'=>1,'password'=>'synthetic','token'=>'synthetic','tenant_id'=>99],['id']);
    checkAudit('CORE-PROJECTION',$data===['id'=>1]);
}catch(Throwable $e){fwrite(STDERR,'HARNESS_ERROR '.get_class($e).': '.$e->getMessage()."\n");exit(2);}
echo json_encode(['checks'=>$checks],JSON_PRETTY_PRINT)."\n";
exit(count(array_filter($checks,fn($c)=>$c['result']==='FAIL'))?1:0);
