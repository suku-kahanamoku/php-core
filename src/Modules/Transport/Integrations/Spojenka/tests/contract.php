<?php
declare(strict_types=1);
use App\Modules\Transport\Core\ProviderSelectionService;
use App\Modules\Transport\Core\PlaceSearchService;
use App\Modules\Transport\Core\ProviderRegistry;
use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Http\HttpResponse;
use App\Modules\Transport\Model\PlaceQuery;
use App\Modules\Transport\Integrations\Spojenka\SpojenkaProvider;
use App\Modules\Transport\Integrations\Spojenka\SpojenkaMapper;
use App\Modules\Transport\Contracts\Provider;

$sp = new SpojenkaProvider(new ProviderDefinition('tram','spojenka','spojenka',['url'=>'https://spojenka.d3s.mff.cuni.cz/api','native_stop_search'=>true],[$cz]));
$request = $sp->resourceRequest('places',['query'=>'Václav','limit'=>20,'city'=>'Brno']);
check(str_contains($request->url,'name=Brno+vaclav') && str_contains($request->url,'limit=100'), 'adapter translates common query into online API request');
$stations = [
    ['persistentId'=>'crz:1','name'=>'Brno, Václavská','latitude'=>49.2,'longitude'=>16.6,'placeHierarchy'=>[['type'=>'MUNICIPALITY','name'=>'Brno']]],
    ['persistentId'=>'crz:2','name'=>'Praha, Václavské náměstí','latitude'=>null,'longitude'=>null,'placeHierarchy'=>[['type'=>'MUNICIPALITY','name'=>'Praha']]],
];
$mapped = $sp->resourceResult('places',new HttpResponse(200,json_encode($stations)),['query'=>'Vaclav','limit'=>20,'city'=>'Brno']);
check(count($mapped)===1 && $mapped[0]['name']==='Brno, Václavská', 'online results obey municipality filter');
$mapper = new SpojenkaMapper('tram','spojenka');
check($mapper->station($stations[1])['lat']===null, 'valid stations without coordinates do not discard entire source');
fails(fn ()=>$mapper->station(array_replace($stations[0],['latitude'=>999])),'invalid_upstream');
fails(fn ()=>$sp->resourceResult('places',new HttpResponse(503,'{}'),['query'=>'Va','limit'=>20]),'upstream_unavailable');
$native = $sp->definition();
$nativeQuery = JourneyQuery::fromArray(['from-dest'=>['type'=>'coordinates','lat'=>49.2,'lon'=>16.6],'to-dest'=>['type'=>'coordinates','lat'=>49.3,'lon'=>16.7],'from-date'=>'2026-10-06T10:00:00+02:00']);
check($native->covers($nativeQuery->withPlaces(['provider'=>'spojenka','external'=>'crz:1'],['provider'=>'spojenka','external'=>'crz:2'])), 'verified provider station identities can route without coordinates');
check(!$native->covers($nativeQuery->withPlaces(['provider'=>'foreign','external'=>'crz:1'],['provider'=>'spojenka','external'=>'crz:2'])), 'native station shortcut cannot use a foreign provider identity');
$search = $sp->searchRequest($nativeQuery);
check($search->method==='POST' && $search->body['maxTransfers']===5 && $search->body['type']==='DEPARTURE', 'online planner receives routing options');
$stationRef = static fn($id,$name)=>['stopPostRef'=>['stationRef'=>['id'=>$id,'name'=>$name],'postCode'=>'A']];
$fixtureTrip = ['connection'=>['persistentId'=>'service-1','originDeparture'=>'2026-10-06T09:50:00+02:00','line'=>['means'=>'TRAM','ids'=>[['localLineCode'=>'12']]]],
    'from'=>$stationRef('crz:1','Brno, Grohova'),'to'=>$stationRef('crz:2','Brno, Nové sady'),
    'departure'=>'2026-10-06T10:00:00+02:00','arrival'=>'2026-10-06T10:10:00+02:00','kmDistance'=>2];
$fixtureResponse = ['journeySets'=>[['journeys'=>[['journey'=>['departure'=>'2026-10-06T09:58:00+02:00','arrival'=>'2026-10-06T10:12:00+02:00','trips'=>[$fixtureTrip]]]]]]];
$mappedJourney = $mapper->journeys($fixtureResponse,$nativeQuery)[0];
check(array_column($mappedJourney['legs'],'mode')===['walk','tram','walk'] && $mappedJourney['duration_seconds']===840, 'access and egress walking remain part of complete journey');
check($mappedJourney['transfers']===0 && $mappedJourney['legs'][1]['realtime']===false, 'walking does not count as a transfer or imply live vehicle data');
$fixtureResponse['journeySets'][0]['journeys'][0]['journey']['trips'][0]['connection']['line']['means']='SPACESHIP';
fails(fn ()=>$mapper->journeys($fixtureResponse,$nativeQuery),'invalid_upstream');
check(array_keys($selector->select('places','CZ',null,$fix))===['national','brno'], 'explicit country can combine with GPS city-area selection');
check(array_keys($selector->select('places','NO',null,$fix))===['norway'], 'explicit foreign country overrides conflicting device position');
check($mapper->station($stations[0])['city']==='Brno', 'municipality is mapped from structured station hierarchy');
$cityQuery = $nativeQuery->withPlaces(['city'=>'Brno','id'=>'a'],['city'=>'Brno','id'=>'b']);
$cityJourneys = [['legs'=>[['from'=>['id'=>'a'],'to'=>['id'=>'b']]]]];
check(\App\Modules\Transport\Core\JourneyAreaService::city($cityQuery,$cityJourneys)==='Brno', 'local journey infers municipality from verified stops');
check(\App\Modules\Transport\Core\JourneyAreaService::city($cityQuery->withPlaces(['city'=>'Praha','id'=>'a'],['city'=>'Brno','id'=>'b']),$cityJourneys)===null, 'intercity journey uses all timetables');
$outsideCity = [['legs'=>[['from'=>['id'=>'a'],'to'=>['id'=>'x','name'=>'Kuřim, nádraží']],['from'=>['id'=>'x','name'=>'Kuřim, nádraží'],'to'=>['id'=>'b']]]]];
check(\App\Modules\Transport\Core\JourneyAreaService::city($cityQuery,$outsideCity)===null, 'journey via another municipality does not infer a city scope');
check(\App\Modules\Transport\Core\JourneyAreaService::city($nativeQuery,$cityJourneys)===null, 'coordinates without verified city do not fabricate a municipality');

$legendTrip = ['connection'=>['persistentId'=>'not-a-public-number','originDeparture'=>'2026-10-06T10:00:00+02:00',
    'numbers'=>[['registryName'=>'CISJR','number'=>'1093']],
    'line'=>['ids'=>[['localLineCode'=>'35']], 'timetableNotes'=>[['defaultLanguage'=>'cs','localizedText'=>['cs'=>'Poznámka linky www.idsjmk.cz']]]],
    'timetableNotes'=>[['defaultLanguage'=>'cs','localizedText'=>['cs'=>'Garantovaná návaznost dle jízdního řádu','en'=>'Timetable connection note']]],
    'vehiclePosition'=>['lat'=>50,'lon'=>14]], 'route'=>[]];
$legend = $mapper->trip($legendTrip,'fixture','2026-10-06')['metadata'];
check($legend['line']==='35' && $legend['number']==='1093', 'trip legend uses explicit public line and connection numbers');
check($legend['notes'][0]['scope']==='trip' && $legend['notes'][1]['scope']==='line' && $legend['notes'][0]['texts']['en']==='Timetable connection note', 'legend preserves localized trip and line notes with scope');
check(!isset($legend['operator'],$legend['operating_days'],$legend['vehiclePosition']) && $legend['service_date']==='2026-10-06', 'legend does not invent operators, operating calendars or telemetry');
unset($legendTrip['connection']['numbers']);
check($mapper->trip($legendTrip,'fixture','2026-10-06')['metadata']['number']===null, 'opaque identifiers never become passenger-facing trip numbers');

$legendTrip['route'] = [['stopPostRef'=>['stationRef'=>['id'=>'test','name'=>'Soukopova']], 'kmPosition'=>0.303,
    'features'=>['REQUEST_STOP'],'tariffZones'=>[['idsID'=>'IDSJMK','tariffZone'=>'100'],['idsID'=>'IDSJMK','tariffZone'=>'100'],['idsID'=>'OTHER','tariffZone'=>'A']]]];
$details = $mapper->trip($legendTrip,'fixture','2026-10-06')['stops'][0];
check($details['route_km']===0.303 && $details['request_stop']===true && count($details['tariff_zones'])===2, 'trip stops preserve source kilometres, request-stop flag and distinct tariff zones');
$legendTrip['route'][0]['kmPosition'] = 0;
check($mapper->trip($legendTrip,'fixture','2026-10-06')['stops'][0]['route_km']===0.0, 'zero kilometrage remains a known value');
$legendTrip['route'][0]['kmPosition'] = -1;
unset($legendTrip['route'][0]['features'],$legendTrip['route'][0]['tariffZones']);
$details = $mapper->trip($legendTrip,'fixture','2026-10-06')['stops'][0];
check($details['route_km']===null && $details['request_stop']===null && $details['tariff_zones']===[], 'unknown stop attributes and invalid kilometrage are not fabricated');

$equipmentTrip = $legendTrip;
$equipmentTrip['connection']['features'] = ['BICYCLE_TRANSPORT','WIFI','TOILETS','BICYCLE_TRANSPORT','FUTURE_UNKNOWN'];
$equipmentTrip['connection']['accessibility'] = 'BARRIER_FREE';
$equipmentTrip['connection']['reservations'] = ['bicycle'=>'MANDATORY','passenger'=>'AVAILABLE','luggage'=>'NONE','secret'=>'MANDATORY'];
$equipmentTrip['connection']['line']['timetableNotes'][] = ['localizedText'=>['cs'=>'Grafikony: PD: T2610 SN: T2609 Pz: P2610']];
$equipment = $mapper->trip($equipmentTrip,'fixture','2026-10-06')['metadata'];
check($equipment['features'] === ['BICYCLE_TRANSPORT','WIFI','TOILETS'] && $equipment['accessibility']==='accessible', 'trip mapper includes documented equipment, deduplicates and rejects unknown features');
check($equipment['reservations']===['bicycle'=>'mandatory','passenger'=>'available'], 'trip reservation policies remain distinct from transport permissions');
check(end($equipment['notes'])['category']==='technical' && $equipment['notes'][0]['category']==='passenger', 'internal timetable variants are classified separately from passenger notes');
$equipmentTrip['connection']['accessibility']='NONE';
$equipmentTrip['connection']['features']=null;
check($mapper->trip($equipmentTrip,'fixture','2026-10-06')['metadata']['accessibility']===null && $mapper->trip($equipmentTrip,'fixture','2026-10-06')['metadata']['features']===[], 'missing equipment never invents availability or a prohibition');

check(!\App\Modules\Transport\Core\JourneyAreaService::isIntercity($nativeQuery,$cityJourneys), 'unknown city metadata does not assert an intercity route');
check(\App\Modules\Transport\Core\JourneyAreaService::isIntercity($cityQuery->withPlaces(['city'=>'Praha','id'=>'a'],['city'=>'Brno','id'=>'b']),$cityJourneys), 'verified distinct endpoint cities mark intercity routes');
check(!\App\Modules\Transport\Core\JourneyAreaService::isIntercity($cityQuery,$cityJourneys), 'local journeys do not clear the selected city');

// Static rows must be deliverable without any per-stop coordinate HTTP requests.
$noCoordinateHttp = new class implements \App\Modules\Http\Contracts\HttpClient {
    public function send(\App\Modules\Http\HttpRequest $request): \App\Modules\Http\HttpResponse { throw new \RuntimeException('Unexpected enrichment request'); }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array { throw new \RuntimeException('Unexpected enrichment requests'); }
};
$staticTrip = $mapper->trip($legendTrip, 'fixture', '2026-10-06');
check($sp->enrichResource('trip', $staticTrip, ['stop_coordinates' => false], $noCoordinateHttp) === $staticTrip, 'fast trip keeps static rows and metadata without waiting for coordinate enrichment');
