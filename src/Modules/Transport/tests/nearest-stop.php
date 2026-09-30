<?php

declare(strict_types=1);

use App\Modules\Transport\Core\NearestStopService;
use App\Modules\Transport\Core\ProviderRegistry;
use App\Modules\Transport\Core\ResourceService;
use App\Modules\Transport\Core\JourneyService;
use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Integrations\Spojenka\SpojenkaProvider;
use App\Modules\Http\HttpResponse;

$nearProvider = new SpojenkaProvider(new ProviderDefinition('tram','pid','spojenka',
    ['url'=>'https://spojenka.d3s.mff.cuni.cz/api','native_stop_search'=>true], [['country'=>'CZ','bbox'=>[12,48,19,52]]]));
$nearRegistry = new ProviderRegistry([$nearProvider]);
$resetNearest = static function () use ($r): void {
    $r->execute("UPDATE transport_provider SET failure_count=0,open_until=NULL,probe_until=NULL,next_request_at=NULL WHERE franchise_code='tram' AND code='pid'");
};
$gps = ['type'=>'current_location','lat'=>50.0751,'lon'=>14.4201,'observed_at'=>gmdate(DATE_RFC3339)];
$stationNear = ['persistentId'=>'S3','name'=>'Nearest online stop','latitude'=>50.0752,'longitude'=>14.4202];
$stationFar = ['persistentId'=>'S2','name'=>'Farther online stop','latitude'=>50.082,'longitude'=>14.44];
$nearHttp = new FakeHttp(['pid'=>new HttpResponse(200,json_encode([$stationFar,$stationNear]))]);
$nearService = new NearestStopService($nearRegistry,$nearHttp,$r);
$resetNearest();
$chosen = $nearService->resolve($gps);
check($chosen['place']['external']==='S3' && $chosen['place']['type']==='stop', 'GPS chooses nearest online stop regardless of upstream order and closer local catalogue rows');
check($chosen['place']['name']==='Nearest online stop' && !isset($chosen['place']['observed_at']), 'resolved stop has its public name without device telemetry');
check(abs(NearestStopService::distance($gps,$gps)) < 0.001, 'coincident points have zero distance');
$resetNearest();
$nearHttp->responses['pid'] = new HttpResponse(200,'[]');
fails(fn ()=>$nearService->resolve($gps),'nearby_stop_not_found');
$resetNearest();
$nearHttp->responses['pid'] = new HttpResponse(200,json_encode([array_replace($stationFar,['latitude'=>49,'longitude'=>16])]));
fails(fn ()=>$nearService->resolve($gps),'nearby_stop_not_found');
$resetNearest();
$nearHttp->responses['pid'] = new HttpResponse(503,'');
$chosen = $nearService->resolve($gps);
check($chosen['partial'] && $chosen['place']['external']==='S1' && $chosen['place']['source_mode']==='fallback', 'only failed nearby source enables valid static snapshot fallback');
check($other->nearbyStops($gps,2000,['pid'])===[], 'nearest catalogue search is tenant-isolated');
$beforeCalls = count($nearHttp->requests);
fails(fn ()=>$nearService->resolve(array_replace($gps,['observed_at'=>gmdate(DATE_RFC3339,time()-60)])),'stale_location');
check(count($nearHttp->requests)===$beforeCalls, 'stale GPS is rejected before network calls');

// The planner must receive a station identity rather than the original GPS point.
$resetNearest();
$nearURL = $nearProvider->resourceRequest('nearby_stops',['location'=>$gps,'limit'=>50])->url;
$fixtureLeg = ['connection'=>['persistentId'=>'trip-1','originDeparture'=>'2026-10-06T10:00:00+02:00','line'=>['means'=>'TRAM']],
    'from'=>['stopPostRef'=>['stationRef'=>['id'=>'S3','name'=>'Nearest online stop']]],
    'to'=>['stopPostRef'=>['stationRef'=>['id'=>'S2','name'=>'Farther online stop']]],
    'departure'=>'2026-10-06T10:00:00+02:00','arrival'=>'2026-10-06T10:10:00+02:00'];
$nearHttp->urlResponses[$nearURL] = new HttpResponse(200,json_encode([$stationFar,$stationNear]));
$nearHttp->responses['resource'] = new HttpResponse(200,json_encode($stationFar));
$nearHttp->responses['pid'] = new HttpResponse(200,json_encode(['journeySets'=>[['journeys'=>[['journey'=>[
    'departure'=>$fixtureLeg['departure'],'arrival'=>$fixtureLeg['arrival'],'trips'=>[$fixtureLeg],
]]]]]]));
$nearQuery = JourneyQuery::fromArray(['from-dest'=>['type'=>'current_location','lat'=>$gps['lat'],'lon'=>$gps['lon'],'observed-at'=>$gps['observed_at']],
    'to-dest'=>['type'=>'stop','id'=>ResourceIdCodec::encode('tram','pid','stop','S2')],'from-date'=>'2026-10-06T09:59:00+02:00']);
$nearJourneyService = new JourneyService($nearRegistry,$nearHttp,$r,new ResourceService($nearRegistry,$nearHttp,$r));
$found = $nearJourneyService->search($nearQuery);
$sent = array_values(array_filter($nearHttp->payloads,static fn ($request)=>str_ends_with($request->url,'/journey/search')))[0];
check($sent->body['from']===['@type'=>'station','stationId'=>'S3'], 'routing starts at the selected nearest stop');
check($found['resolved_places']['from']['name']==='Nearest online stop' && count($found['journeys'])===1, 'search response exposes chosen stop to UI');
$cached = $r->journey($found['journeys'][0]['id']);
check($cached['legs'][0]['from']['lat']===null && !isset($cached['resolved_places']) && !str_contains(json_encode($cached),'50.0751'), 'GPS search and its resolution metadata are not persisted in journey cache');

$resetNearest();
$nearHttp->responses['pid'] = new HttpResponse(200,json_encode([$stationFar,$stationNear,$stationNear]));
$nearHttp->urlResponses = [];
$nearby = $nearService->search($gps);
check(array_column($nearby['places'],'name')===['Nearest online stop','Farther online stop'], 'nearby dropdown ranks and deduplicates online stops without local rows');
$resetNearest();
check(count($nearService->search($gps,1)['places'])===1, 'nearby dropdown respects limit');
$filter = ['q'=>['latitude'=>$gps['lat'],'longitude'=>$gps['lon'],'observed_at'=>$gps['observed_at']]];
$nearQuery = \App\Modules\Transport\Model\PlaceQuery::parse($filter);
check($nearQuery['query']===null && $nearQuery['location']['type']==='current_location', 'places filter accepts GPS-only nearby search');
fails(fn()=>\App\Modules\Transport\Model\PlaceQuery::parse(['q'=>['state'=>'CZ']]),'invalid_query');
fails(fn()=>\App\Modules\Transport\Model\PlaceQuery::parse(['q'=>['name'=>['$regex'=>'']]+$filter['q']]),'invalid_query');
$resetNearest();
$nearHttp->responses['pid'] = new HttpResponse(200,'[]');
check($nearService->search($gps)['places']===[], 'successful empty nearby search does not mix in local catalogue');
