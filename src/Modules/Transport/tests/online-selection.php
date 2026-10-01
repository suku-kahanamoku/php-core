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
require dirname(__DIR__).'/Integrations/Spojenka/tests/contract.php';
require dirname(__DIR__).'/Integrations/IdsJmk/tests/contract.php';
