<?php

declare(strict_types=1);

use App\Modules\Transport\{ProviderSelectionService,PlaceSearchService,ProviderRegistry};
use App\Modules\Transport\DTO\{ProviderDefinition,JourneyQuery};
use App\Modules\Http\HttpResponse;
use App\Modules\Transport\DTO\PlaceQuery;
use App\Modules\Transport\Providers\{SpojenkaProvider,SpojenkaMapper};
use App\Modules\Transport\Contracts\Provider;

$source = static function (string $code, array $coverage, array $capabilities = ['places','journeys']): Provider {
    return new class($code,$coverage,$capabilities) implements Provider {
        public function __construct(private string $code, private array $coverage, private array $operations) {}
        public function definition(): ProviderDefinition { return new ProviderDefinition('tram',$this->code,'test',[],$this->coverage); }
        public function capabilities(): array { return $this->operations; }
    };
};
$cz = ['country'=>'CZ','bbox'=>[12,48,19,52]];
$prague = ['country'=>'CZ','cities'=>['Praha'],'bbox'=>[14.2,49.9,14.7,50.2]];
$brno = ['country'=>'CZ','cities'=>['Brno'],'bbox'=>[16.4,49.0,16.9,49.4]];
$selector = new ProviderSelectionService(new ProviderRegistry([
    $source('national',[$cz]),$source('prague',[$prague]),$source('brno',[$brno]),
    $source('norway',[['country'=>'NO','bbox'=>[4,58,32,71]]]),$source('positions',[$cz],['realtime']),
]));
check(array_keys($selector->select('places','CZ')) === ['national','prague','brno'], 'country selects all capable national and city providers');
check(array_keys($selector->select('places','CZ','brnó')) === ['national','brno'], 'explicit city keeps national source and excludes other cities');
$fix = ['type'=>'current_location','lat'=>49.195,'lon'=>16.61,'observed_at'=>gmdate(DATE_RFC3339)];
check(array_keys($selector->select('places',null,null,$fix)) === ['national','brno'], 'fresh GPS selects matching geographic coverage');
check(array_keys($selector->select('places','CZ','Praha',$fix)) === ['national','prague'], 'explicit destination region overrides device position');
check(array_keys($selector->select('realtime','CZ','Brno')) === ['positions'], 'capability selection excludes unsupported sources');
fails(fn ()=>$selector->select('places',null,null,array_replace($fix,['observed_at'=>gmdate(DATE_RFC3339,time()-60)])), 'stale_location');
$pq = PlaceQuery::parse(['q'=>['name'=>['$regex'=>'Vaclav'],'state'=>'CZ','city'=>'Brno'],'limit'=>20]);
check($pq['query']==='Vaclav' && $pq['city']==='Brno' && $pq['limit']===20 && $pq['sort']==='', 'q contract preserves city and relevance ordering');
fails(fn()=>PlaceQuery::parse(['q'=>['name'=>'bad']]),'invalid_query');
fails(fn()=>PlaceQuery::parse(['q'=>['name'=>['$regex'=>'Va']],'page'=>2]),'invalid_query');
fails(fn()=>PlaceQuery::parse(['q'=>['name'=>['$regex'=>'Va'],'latitude'=>49,'longitude'=>16]]),'invalid_place');
$rows = [['id'=>'b','name'=>'Brno, Václavská'],['id'=>'p','name'=>'Praha, Václavské náměstí'],['id'=>'x','name'=>'Praha, Karlovo náměstí']];
check(count(PlaceSearchService::rank($rows,'Vaclav',20))===2, 'substring search ignores diacritics across cities');
check(count(PlaceSearchService::rank($rows,'Brno Vaclav',20))===1, 'city plus name works without a comma');
check(PlaceSearchService::rank($rows,'.*',20)===[], 'regex-looking input remains literal');
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
