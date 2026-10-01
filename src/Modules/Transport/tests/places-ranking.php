<?php
declare(strict_types=1);

use App\Modules\Transport\Core\{PlaceSearchService, ResourceService, ProviderRegistry};
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Integrations\Spojenka\SpojenkaProvider;
use App\Modules\Http\HttpResponse;

$rankingFix = ['type'=>'current_location','lat'=>49.195,'lon'=>16.61,'observed_at'=>gmdate(DATE_RFC3339)];
$rankingRows = [
    ['id'=>'tabor','name'=>'Tábor','lat'=>49.414,'lon'=>14.658],
    ['id'=>'near','name'=>'Brno, Pod Táborem','lat'=>49.196,'lon'=>16.61],
    ['id'=>'brno','name'=>'Brno, Tábor','lat'=>49.21,'lon'=>16.59],
    ['id'=>'unknown','name'=>'Tab','lat'=>null,'lon'=>null],
    ['id'=>'invalid','name'=>'Tab invalid','lat'=>999,'lon'=>16],
    ['id'=>'unrelated','name'=>'Brno, Hlavní nádraží','lat'=>49.195,'lon'=>16.61],
];
check(array_column(PlaceSearchService::rank($rankingRows,'tab',20,$rankingFix),'id') === ['near','brno','tabor','unknown','invalid'], 'GPS ranks only text matches by distance, retaining distant and coordinate-less matches');
check(PlaceSearchService::rank($rankingRows,'tab',1,$rankingFix)[0]['id']==='near', 'GPS ranking precedes the result limit');
check(PlaceSearchService::rank($rankingRows,'tab',1)[0]['id']==='unknown', 'without GPS exact text relevance remains first');
check(PlaceSearchService::rank($rankingRows,'.*',20,$rankingFix)===[], 'GPS never changes literal substring semantics');
$rankingProvider = new SpojenkaProvider(new ProviderDefinition('tram','pid','spojenka',['url'=>'https://spojenka.d3s.mff.cuni.cz/api'],[['country'=>'CZ','cities'=>['Praha'],'bbox'=>[14.2,49.9,14.7,50.2]]]));
$rankingStations = array_map(fn($r)=>['persistentId'=>$r['id'],'name'=>$r['name'],'latitude'=>$r['lat'],'longitude'=>$r['lon']], array_slice($rankingRows,0,4));
$rankingHttp = new FakeHttp(['pid'=>new HttpResponse(200,json_encode($rankingStations))]);
$rankingInput = ['query'=>'tab','limit'=>1,'city'=>null,'location'=>$rankingFix];
check($rankingProvider->resourceResult('places',$rankingHttp->responses['pid'],$rankingInput)[0]['name']==='Brno, Pod Táborem', 'Spojenka preserves distance priority before trimming adapter results');
$rankingRequest = $rankingProvider->resourceRequest('places',$rankingInput);
parse_str(parse_url($rankingRequest->url,PHP_URL_QUERY),$rankingParams);
check($rankingParams['name']==='tab' && (float)$rankingParams['lat']===$rankingFix['lat'] && (float)$rankingParams['lon']===$rankingFix['lon'], 'adapter receives both text and ranking coordinates');
$r->providerSuccess('pid');
$rankingService = new ResourceService(new ProviderRegistry([$rankingProvider]),$rankingHttp,$r);
$ranked = $rankingService->places('tab',20,'CZ',null,$rankingFix);
check(count($rankingHttp->requests)===1 && count($ranked['places'])===4 && $ranked['places'][0]['name']==='Brno, Pod Táborem', 'country text search includes providers outside the GPS area and ranks merged results');
$beforeRankingCalls=count($rankingHttp->requests);
fails(fn()=>$rankingService->places('tab',20,'CZ',null,array_replace($rankingFix,['observed_at'=>gmdate(DATE_RFC3339,time()-60)])), 'stale_location');
check(count($rankingHttp->requests)===$beforeRankingCalls, 'stale ranking GPS is rejected before calling any provider');

$savedRankingStops=$r->rows("SELECT franchise_code,version_id,external_id,name,lat,lon FROM transport_stop WHERE franchise_code=?",['tram']);
try {
    check(count($savedRankingStops)>=2, 'ranking fallback fixture has multiple stops');
    foreach ($savedRankingStops as $index=>$row) {
        $r->execute('UPDATE transport_stop SET name=?,lat=?,lon=? WHERE franchise_code=? AND version_id=? AND external_id=?',[
            $index===0?'Z tab near':'A tab far', $index===0?49.195:50.08, $index===0?16.61:14.41,
            $row['franchise_code'],$row['version_id'],$row['external_id'],
        ]);
    }
    check($r->places('tab',1,'CZ',['pid'],null,$rankingFix)[0]['name']==='Z tab near', 'static fallback orders by distance in SQL before LIMIT');
    check($other->places('tab',1,'CZ',['pid'],null,$rankingFix)===[], 'GPS-ranked fallback remains tenant isolated');
    $r->providerSuccess('pid');
    $rankingHttp->responses['pid']=new HttpResponse(503,'');
    $ranked=$rankingService->places('tab',1,'CZ',null,$rankingFix);
    check($ranked['partial'] && $ranked['places'][0]['name']==='Z tab near' && $ranked['places'][0]['source_mode']==='fallback', 'failed provider uses the same nearest-first order in catalogue fallback');
} finally {
    foreach ($savedRankingStops as $row) {
        $r->execute('UPDATE transport_stop SET name=?,lat=?,lon=? WHERE franchise_code=? AND version_id=? AND external_id=?',[
            $row['name'],$row['lat'],$row['lon'],$row['franchise_code'],$row['version_id'],$row['external_id'],
        ]);
    }
    $r->providerSuccess('pid');
}
