<?php
declare(strict_types=1);

use App\Modules\Transport\Model\{CityQuery,ProviderDefinition};
use App\Modules\Transport\Integrations\Spojenka\SpojenkaProvider;
use App\Modules\Transport\Core\{ProviderRegistry,ResourceService};
use App\Modules\Http\HttpResponse;

$cityQuery = CityQuery::parse(['q'=>['state'=>'CZ']]);
check($cityQuery['query']==='' && $cityQuery['limit']===50, 'city catalogue permits country-only q without invented search terms');
fails(fn()=>CityQuery::parse(['q'=>['state'=>'CZE']]), 'invalid_country');
fails(fn()=>CityQuery::parse(['q'=>['state'=>'CZ','name'=>'Tábor']]), 'invalid_query');
fails(fn()=>CityQuery::parse(['q'=>['state'=>'CZ','latitude'=>50]]), 'invalid_query');
fails(fn()=>CityQuery::parse(['q'=>['state'=>'CZ'],'limit'=>10001]), 'invalid_limit');
$cityProvider = new SpojenkaProvider(new ProviderDefinition('tram','pid','spojenka',['url'=>'https://spojenka.d3s.mff.cuni.cz/api'],[['country'=>'CZ','bbox'=>[12,48,19,52]]]));
$cityRequest = $cityProvider->resourceRequest('cities',['country'=>'CZ']);
check(str_contains($cityRequest->url,'typeMask%5B%5D=MUNICIPALITY') && str_contains($cityRequest->url,'limit=10000'), 'city adapter requests bounded complete municipality catalogue');
$cityFixtures = array_map(fn($name,$i)=>['id'=>['listId'=>'int','objectId'=>(string)$i],'type'=>'MUNICIPALITY','name'=>$name], ['Praha','Brno','Tábor','Třebíč','Tábor'], range(1,5));
$cityHttp = new FakeHttp(['pid'=>new HttpResponse(200,json_encode($cityFixtures))]);
$r->providerSuccess('pid');
$cityService = new ResourceService(new ProviderRegistry([$cityProvider]),$cityHttp,$r);
$allCities = $cityService->cities(CityQuery::parse(['q'=>['state'=>'CZ'],'limit'=>10000]));
check(array_column($allCities['data'],'name')===['Brno','Praha','Tábor','Třebíč'] && !$allCities['partial'], 'online catalogues deduplicate municipalities independently of stop result limits');
$filtered = $cityService->cities(CityQuery::parse(['q'=>['state'=>'CZ','name'=>['$regex'=>'tab']],'projection'=>'name,state']));
check($filtered['data']===[['name'=>'Tábor','state'=>'CZ']], 'city names ignore accents and respect projection');
$page = $cityService->cities(CityQuery::parse(['q'=>['state'=>'CZ'],'sort'=>[['name'=>-1]],'limit'=>1,'page'=>2]));
check($page['data'][0]['name']==='Tábor' && $page['total']===4 && $page['has_more'], 'city standard pagination and descending sort are deterministic');
$literal = $cityService->cities(CityQuery::parse(['q'=>['state'=>'CZ','name'=>['$regex'=>'.*']]]));
check($literal['data']===[], 'city regex syntax is a literal substring');
fails(fn()=>$cityProvider->resourceResult('cities',new HttpResponse(200,json_encode([['type'=>'STREET','name'=>'Wrong']])),['country'=>'CZ']), 'invalid_upstream');
fails(fn()=>(new ResourceService(new ProviderRegistry([]),$cityHttp,$r))->cities($cityQuery), 'cities_not_configured');
$savedCityData = $r->rows('SELECT franchise_code,version_id,external_id,data FROM transport_stop WHERE franchise_code=?', ['tram']);
try {
    $db->exec("UPDATE transport_stop SET data=JSON_SET(COALESCE(data,JSON_OBJECT()),'$.city','FallbackOnly') WHERE franchise_code='tram'");
    $r->providerSuccess('pid');
    $emptyCityService = new ResourceService(new ProviderRegistry([$cityProvider]),new FakeHttp(['pid'=>new HttpResponse(200,'[]')]),$r);
    check($emptyCityService->cities($cityQuery)['data']===[], 'successful empty city catalogue never consults local snapshots');
    $r->providerSuccess('pid');
    $failedCityService = new ResourceService(new ProviderRegistry([$cityProvider]),new FakeHttp(['pid'=>new HttpResponse(503,'')]),$r);
    $fallbackCities = $failedCityService->cities($cityQuery);
    check($fallbackCities['partial'] && $fallbackCities['data'][0]['name']==='FallbackOnly' && $fallbackCities['data'][0]['source_mode']==='fallback', 'failed city source uses active imported municipality metadata');
    check($r->cities('CZ',[])===[] && $r->cities('DE',['pid'])===[], 'city fallback is scoped by failed provider and country');
} finally {
    $restoreCityData = $db->prepare('UPDATE transport_stop SET data=? WHERE franchise_code=? AND version_id=? AND external_id=?');
    foreach ($savedCityData as $row) { $restoreCityData->execute([$row['data'],$row['franchise_code'],$row['version_id'],$row['external_id']]); }
}
