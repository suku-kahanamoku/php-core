<?php

declare(strict_types=1);
require dirname(__DIR__, 4).'/vendor/autoload.php';
require __DIR__.'/fixtures.php';
use App\Modules\Transport\{ConfigurationService,TransportException,ResourceIdCodec,ProviderRegistry,ResourceService,JourneyService,GeometryMapper};
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\DTO\{JourneyQuery,ProviderDefinition};
use App\Modules\Http\HttpResponse;
use App\Modules\Transport\Import\{FeedSyncService,ServiceTimeService,GraphService};
use App\Modules\Transport\Repositories\TransportRepository;
use App\Modules\Transport\Providers\TransmodelProvider;

error_reporting(E_ALL);
set_error_handler(static function (int $n, string $s, string $f, int $l): bool {
    if (!(error_reporting() & $n)) {
        return false;
    } throw new ErrorException($s, 0, $n, $f, $l);
});
$checks = 0;
function check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL '.$name);
    } ++$checks;
    echo "PASS $name\n";
}
function fails(callable $fn, string $reason): void
{
    try {
        $fn();
    } catch (TransportException $e) {
        check($e->reason === $reason, 'reject '.$reason);
        return;
    } throw new RuntimeException('Expected '.$reason);
}
$dsn = getenv('TRANSPORT_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=transport_test;')) {
    throw new RuntimeException('Isolated transport_test DSN required.');
}
$server = new PDO(explode(';dbname=', $dsn)[0], 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE transport_test CHARACTER SET utf8mb4');
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec("SET time_zone='+00:00'");
$root = dirname(__DIR__, 4);
foreach (['schema', 'tram_schema', 'tram_seed'] as $file) {
    $sql = file_get_contents($root.'/migrations/'.$file.'.sql');
    $db->exec($sql);
    $db->exec($sql);
}
check(true, 'consolidated schemas and seed apply twice');
$r = new TransportRepository($db, 'tram');
$other = new TransportRepository($db, 'other');
$config = json_decode(file_get_contents($root.'/config/transport.example.json'), true);
ConfigurationService::apply($r, $config);
ConfigurationService::apply($r, $config);
ConfigurationService::apply($other, $config);
check(count($r->providers()) === 3, 'provider configuration is idempotent');
check((int)$r->rows("SELECT COUNT(*) n FROM enumeration WHERE franchise_code='tram' AND type='transport_mode'")[0]['n'] === 12, 'transport modes use existing enumeration');
$input = ['from-dest' => ['type' => 'coordinates','lat' => 50.075,'lon' => 14.42],'to-dest' => ['type' => 'coordinates','lat' => 50.082,'lon' => 14.44],'from-date' => '2026-10-06T09:00:00+02:00'];
$q = JourneyQuery::fromArray($input);
$liveLocationInput = array_replace($input, ['from-dest' => [
    'type' => 'current_location','lat' => 50.075,'lon' => 14.42,
    'observed-at' => gmdate('Y-m-d\TH:i:s\Z'),
]]);
$liveLocationQuery = JourneyQuery::fromArray($liveLocationInput);
check($liveLocationQuery->from['type'] === 'current_location' && isset($liveLocationQuery->from['observed_at']),
    'fresh user GPS fix is accepted only as current_location');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['from-dest' => [
    'type' => 'current_location','lat' => 50.075,'lon' => 14.42,
    'observed-at' => gmdate('Y-m-d\TH:i:s\Z', time() - 60),
]])), 'stale_location');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['from-dest' => [
    'type' => 'current_location','lat' => 50.075,'lon' => 14.42,
    'observed-at' => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
]])), 'stale_location');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['from-dest' => [
    'type' => 'current_location','lat' => 50.075,'lon' => 14.42,
]])), 'invalid_place');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['from-dest' => [
    'type' => 'coordinates','lat' => 50.075,'lon' => 14.42,
    'observed-at' => gmdate('Y-m-d\TH:i:s\Z'),
]])), 'invalid_place');

fails(fn () => JourneyQuery::fromArray($input + ['to-date' => '2026-10-06T10:00:00+02:00']), 'invalid_date');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['from-date' => '2026-02-30T10:00:00Z'])), 'invalid_date');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['modes' => ['spaceship']])), 'invalid_modes');
fails(fn () => JourneyQuery::fromArray(array_replace($input, ['limit' => 1000])), 'invalid_limit');
fails(fn () => JourneyQuery::fromArray($input + ['provider-url' => 'http://localhost']), 'invalid_query');
$id = ResourceIdCodec::encode('tram', 'pid', 'stop', 'S1');
check(ResourceIdCodec::decode($id, 'tram', 'stop')['external'] === 'S1', 'resource ID round trip');
fails(fn () => ResourceIdCodec::decode($id, 'other', 'stop'), 'not_found');
check(ServiceTimeService::seconds('25:35:00') === 92100, 'GTFS time beyond midnight');
check(ServiceTimeService::instant('2026-03-29', 10800, 'Europe/Prague')->format('c') === '2026-03-29T03:00:00+02:00', 'GTFS noon-minus-twelve DST spring semantics');
check(ServiceTimeService::instant('2026-10-25', 10800, 'Europe/Prague')->format('c') === '2026-10-25T03:00:00+01:00', 'GTFS DST autumn semantics');
check(count(GeometryMapper::polyline('_p~iF~ps|U_ulLnnqC_mqNvxq`@')['coordinates']) === 3, 'polyline geometry');
fails(fn () => GeometryMapper::polyline('~'), 'invalid_geometry');
$dir = getenv('TRANSPORT_TEST_DIR');
gtfsFixture($dir.'/feed.zip');
$sync = new FeedSyncService($r, $dir.'/archives');
$import = $sync->sync('pid', $dir.'/feed.zip');
$version = $import['version_id'];
check($import['status'] === 'ready' && $r->activeFeed('pid') === null, 'import is staged before graph activation');
check($sync->sync('pid', $dir.'/feed.zip')['status'] === 'unchanged', 'checksum skips unchanged feed');
check((int)$r->rows('SELECT departure_seconds FROM transport_stop_time WHERE franchise_code=? AND version_id=? AND trip_id=? AND sequence=1', ['tram',$version,'Tnight'])[0]['departure_seconds'] === 86700, 'import preserves 24h stop times');
gtfsFixture($dir.'/bad.zip', ['stop_times.txt' => "trip_id,arrival_time,departure_time,stop_id,stop_sequence\nT1,10:00:00,10:00:00,UNKNOWN,1\n"]);
try {
    $sync->sync('pid', $dir.'/bad.zip');
    throw new RuntimeException('Expected FK failure');
} catch (PDOException $e) {
    check($e->getCode() === '23000', 'reject orphan stop reference');
}
check((int)$r->rows('SELECT COUNT(*) n FROM transport_feed_version WHERE franchise_code=?', ['tram'])[0]['n'] === 1, 'failed import rolls back all snapshot records');
check($r->rows('SELECT status FROM transport_sync_run WHERE franchise_code=? ORDER BY id DESC LIMIT 1', ['tram'])[0]['status'] === 'failed', 'failed import records sanitized error status');
final class FakeHttp implements HttpClient
{
    public array $requests = [];
    public array $payloads = [];
    public array $urlResponses = [];
    public function __construct(public array $responses)
    {
    }
    public function send(\App\Modules\Http\HttpRequest $request): HttpResponse
    {
        return $this->sendAll([$request], $request->timeoutMs, 1)[0];
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        $out = [];
        foreach ($requests as $k => $q) {
            $this->requests[] = $k;
            $this->payloads[] = $q;
            $out[$k] = $this->urlResponses[$q->url] ?? $this->responses[$k] ?? new HttpResponse(503, '');
        }return $out;
    }
}
$locationResources = new ResourceService(new ProviderRegistry([]), new FakeHttp([]), $r);
$freshFix = JourneyQuery::fromArray(array_replace($input, ['from-dest' => [
    'type' => 'current_location','lat' => 50.075,'lon' => 14.42,
    'observed-at' => gmdate('Y-m-d\TH:i:s\Z'),
]]))->from;
check($locationResources->resolve($freshFix) === $freshFix, 'fresh user location resolves without a catalogue read');
fails(fn () => $locationResources->resolve(array_replace($freshFix, [
    'observed_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 60),
])), 'stale_location');
$probe = new FakeHttp(['0' => new HttpResponse(200, json_encode(['data' => ['quay' => ['latitude' => 50.075,'longitude' => 14.42]]])),'1' => new HttpResponse(200, json_encode(['data' => ['quay' => ['latitude' => 50.082,'longitude' => 14.44]]]))]);
$manager = new GraphService($r, $probe);
$manager->export($version, $dir.'/graph');
file_put_contents($dir.'/graph/graph.obj', 'test graph fixture');
file_put_contents($dir.'/graph/build-receipt.json', json_encode(['manifest_sha256' => hash_file('sha256', $dir.'/graph/manifest.json'),'graph_sha256' => hash_file('sha256', $dir.'/graph/graph.obj')]));
fails(fn () => (new GraphService($other, $probe))->activate($version, $dir.'/graph/manifest.json', 'http://127.0.0.1:19991/otp/transmodel/v3'), 'not_found');
$manager->activate($version, $dir.'/graph/manifest.json', 'http://127.0.0.1:19991/otp/transmodel/v3');
check((int)$r->activeFeed('pid')['id'] === $version, 'graph activation switches feed pointer');
check($r->stop('pid', 'S1')['name'] === 'Start' && $other->stop('pid', 'S1') === null, 'stop reads are tenant isolated');
$snapshotStop = $r->stop('pid', 'S1');
check($snapshotStop['snapshot_version'] === $version && str_ends_with($snapshotStop['snapshot_at'], 'Z'),
    'fallback catalogue identifies its UTC import snapshot');
$otpRow = array_values(array_filter($r->providers(), static fn ($row) => $row['code'] === 'pid-otp'))[0];
check(str_ends_with(json_decode($otpRow['config'], true)['snapshot_at'], 'Z'),
    'OTP fallback configuration exposes UTC snapshot age');

check(count($r->places('st', 10, 'CZ')) === 1, 'local autocomplete uses coverage and prefix');
check($r->trip('pid', 'T1', '2026-10-05') === null, 'calendar removal excludes trip');
check(count($r->trip('pid', 'TX', '2026-10-05')['stops']) === 2, 'calendar addition includes trip');
check($r->trip('pid', 'Tnight', '2026-10-06')['stops'][0]['scheduled_departure'] === '2026-10-07T00:05:00+02:00', 'trip service day survives midnight');
$cache = $r->cacheJourney(['legs' => []]);
check($r->journey($cache['id'])['id'] === $cache['id'], 'journey detail cache');
$privateJourney = $r->cacheJourney(['source' => ['provider' => 'entur','position' => [14.42,50.075]],'position' => [14.42,50.075], 'legs' => [[
    'mode' => 'walk','from' => ['id' => null,'name' => 'Private origin','lat' => 50.075,'lon' => 14.42],
    'to' => ['id' => null,'name' => 'Private destination','lat' => 50.082,'lon' => 14.44],
    'geometry' => ['type' => 'LineString','coordinates' => [[14.42,50.075],[14.44,50.082]]],
    'position' => [14.43,50.076],
    'line' => ['name' => 'Test','position' => [14.43,50.076]],
    'operator' => ['name' => 'Operator','position' => [14.43,50.076]],
]]]);
$storedPrivate = $r->journey($privateJourney['id']);
check($storedPrivate['legs'][0]['from']['lat'] === null && $storedPrivate['legs'][0]['geometry'] === null && !str_contains(json_encode($storedPrivate), '50.075') && !str_contains(json_encode($storedPrivate), '50.076') && !str_contains(json_encode($storedPrivate), 'Private origin'), 'journey detail excludes request and vehicle positions');
$publicJourney = $r->cacheJourney(['legs' => [[
    'mode' => 'tram','from' => ['id' => 'public-stop-a','name' => 'A','lat' => 50.1,'lon' => 14.1],
    'to' => ['id' => 'public-stop-b','name' => 'B','lat' => 50.2,'lon' => 14.2],
    'geometry' => ['type' => 'LineString','coordinates' => [[14.1,50.1],[14.2,50.2]]],
]]], publicStopsOnly: true);
check($r->journey($publicJourney['id'])['legs'][0]['geometry']['type'] === 'LineString', 'public-stop journey keeps planned transit geometry');
fails(fn () => $other->journey($cache['id']), 'expired_journey');
$r->execute('UPDATE transport_journey_cache SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE franchise_code=?', ['tram']);
fails(fn () => $r->journey($cache['id']), 'expired_journey');
check($r->acquireProvider('pid'), 'provider request admission');
$r->providerFailure('pid');
$r->providerFailure('pid');
$r->providerFailure('pid');
check(!$r->acquireProvider('pid'), 'circuit opens after repeated failures');
check($other->acquireProvider('pid'), 'provider circuit isolated by tenant');
$r->execute('UPDATE transport_provider SET open_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
check($r->acquireProvider('pid') && !$r->acquireProvider('pid'), 'single half-open recovery probe');
$r->providerSuccess('pid');
// A working place API must not be mixed with the imported catalogue.
$pidPlaces = new App\Modules\Transport\Providers\PidProvider(
    new ProviderDefinition('tram', 'pid', 'pid', ['url' => 'https://api.golemio.cz'], [['country' => 'CZ','bbox' => [12,48,19,52]]]),
    'fixture-only-token',
);
$placesHttp = new FakeHttp(['pid' => new HttpResponse(200, json_encode(['features' => [
    ['properties' => ['stop_id' => 'REMOTE','stop_name' => 'Station'], 'geometry' => ['coordinates' => [14.42,50.075]]],
]]))]);
$placesService = new ResourceService(new ProviderRegistry([$pidPlaces]), $placesHttp, $r);
$livePlaces = $placesService->places('St', 10, 'CZ');
check(count($livePlaces['places']) === 1 && $livePlaces['places'][0]['name'] === 'Station' && $livePlaces['places'][0]['source_mode'] === 'live', 'successful online places exclude imported catalogue');
$placesHttp->responses['pid'] = new HttpResponse(503, '');
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
$fallbackPlaces = $placesService->places('St', 10, 'CZ');
check(count($fallbackPlaces['places']) === 1 && $fallbackPlaces['places'][0]['name'] === 'Start' && $fallbackPlaces['places'][0]['source_mode'] === 'fallback', 'failed online places use only matching provider catalogue');
$r->providerSuccess('pid');
// Complete journey orchestration: primary outage, backup result, empty result, all unavailable.
$coverage = [['country' => 'CZ','bbox' => [12,48,19,52]]];
$primary = new TransmodelProvider(new ProviderDefinition('tram', 'entur', 'entur', ['url' => 'https://example.test'], $coverage));
$backup = new TransmodelProvider(new ProviderDefinition('tram', 'pid-otp', 'otp_transmodel', ['url' => 'http://localhost','graph_ready' => true], $coverage, 'fallback', ['entur']));
$payload = ['data' => ['trip' => ['tripPatterns' => [['duration' => 600,'legs' => [['mode' => 'tram','distance' => 2000,'realtime' => false,'serviceDate' => '2026-10-06','aimedStartTime' => '2026-10-06T10:00:00+02:00','aimedEndTime' => '2026-10-06T10:10:00+02:00','fromPlace' => ['name' => 'Start','latitude' => 50.075,'longitude' => 14.42],'toPlace' => ['name' => 'End','latitude' => 50.082,'longitude' => 14.44],'serviceJourney' => ['id' => 'pid:T1']]]]]]]];
$http = new FakeHttp(['entur' => new HttpResponse(503, ''),'pid-otp' => new HttpResponse(200, json_encode($payload))]);
$registry = new ProviderRegistry([$primary,$backup]);
$resources = new ResourceService($registry, $http, $r);
$journeys = new JourneyService($registry, $http, $r, $resources);
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$result = $journeys->search($q);
check(count($result['journeys']) === 1 && $result['partial'] && $result['journeys'][0]['source']['mode'] === 'fallback', 'failed primary falls back to valid graph');
$http->responses['entur'] = new HttpResponse(200, '{"data":{"trip":{"tripPatterns":[]}}}');
$http->requests = [];
$result = $journeys->search($q);
check($result['journeys'] === [] && !$result['partial'] && !in_array('pid-otp', $http->requests, true), 'empty successful result is not an outage');
$http->responses = ['entur' => new HttpResponse(503, ''),'pid-otp' => new HttpResponse(503, '')];
fails(fn () => $journeys->search($q), 'sources_unavailable');
$badQ = $q->withPlaces(['type' => 'coordinates','lat' => 35,'lon' => 139], $q->to);
fails(fn () => $journeys->search($badQ), 'unsupported_coverage');
gtfsFixture($dir.'/backwards.zip', ['stop_times.txt' => "trip_id,arrival_time,departure_time,stop_id,stop_sequence\nT1,10:00:00,10:00:00,S1,1\nT1,09:55:00,09:55:00,S2,2\n"]);
fails(fn () => $sync->sync('pid', $dir.'/backwards.zip'), 'invalid_gtfs_relations');
check((int)$r->activeFeed('pid')['id'] === $version, 'invalid chronology preserves active data');
// Source-specific realtime correctness and cross-process import lock.
$pid = new App\Modules\Transport\Providers\PidProvider(
    new ProviderDefinition('tram', 'pid', 'pid', ['url' => 'https://api.golemio.cz','realtime_max_age' => 90], $coverage),
    'fixture-only-token',
);
$position = ['geometry' => ['type' => 'Point','coordinates' => [14.42,50.075]],'properties' => [
    'trip' => ['start_timestamp' => '2026-10-06T08:00:00Z'],
    'last_position' => ['origin_timestamp' => gmdate('Y-m-d\TH:i:s\Z'),'tracking' => true,'is_canceled' => false,'delay' => ['actual' => 30],'bearing' => 180,'speed' => 32],
]];
$enrichmentHttp = new FakeHttp(['pid-departures' => new HttpResponse(200, json_encode([
    'stops' => [['stop_id' => 'S1','stop_name' => 'Start','stop_lat' => 50.075,'stop_lon' => 14.42]],
    'departures' => [[
        'stop' => ['id' => 'S1'],'trip' => ['id' => 'T1','is_canceled' => false],
        'route' => ['type' => 0,'short_name' => '22'],
        'departure_timestamp' => ['scheduled' => '2026-10-06T10:00:00+02:00','predicted' => '2026-10-06T10:03:00+02:00'],
        'arrival_timestamp' => ['scheduled' => null,'predicted' => null],
        'delay' => ['is_available' => true],
    ]],
]))]);
$pidEnrichmentProvider = new App\Modules\Transport\Providers\PidProvider(
    new ProviderDefinition('tram', 'pid', 'pid', ['url' => 'https://api.golemio.cz'], [['country' => 'CZ','bbox' => [12,48,19,52]]]),
    'fixture-only-token',
);
$otpEnrichmentProvider = new TransmodelProvider(new ProviderDefinition('tram', 'pid-otp', 'otp_transmodel',
    ['url' => 'http://127.0.0.1','source_provider' => 'pid','otp_feed_id' => 'pid'], [['country' => 'CZ','bbox' => [12,48,19,52]]], 'fallback', ['pid']));
$enrichmentJourney = ['source' => ['provider' => 'pid-otp','mode' => 'fallback'],'legs' => [[
    'trip_id' => ResourceIdCodec::encode('tram','pid-otp','trip','pid:T1','2026-10-06'),
    'from' => ['id' => ResourceIdCodec::encode('tram','pid-otp','stop','pid:S1')],
    'scheduled_departure' => '2026-10-06T10:00:00+02:00','expected_departure' => null,'realtime' => false,
]]];
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
$enriched = (new App\Modules\Transport\PidJourneyEnrichmentService(
    new ProviderRegistry([$pidEnrichmentProvider,$otpEnrichmentProvider]), $enrichmentHttp, $r,
))->enrich([$enrichmentJourney]);
check($enriched[0]['legs'][0]['expected_departure'] === '2026-10-06T10:03:00+02:00' && $enriched[0]['source']['realtime_provider'] === 'pid', 'PID board enriches matched journey leg online');
check(!isset(App\Modules\Transport\JourneyCacheMapper::sanitize($enriched[0])['source']['realtime_provider']) && App\Modules\Transport\JourneyCacheMapper::sanitize($enriched[0])['legs'][0]['expected_departure'] === null, 'cached detail excludes transient realtime data');
$pidTripPayload = [
    'trip_id' => 'T1','route_id' => 'R1','route' => ['route_type' => 0,'route_short_name' => '22','route_long_name' => 'Test tram'],
    'stops' => [
        ['stop_id' => 'S1','stop_name' => 'Start','stop_lat' => 50.075,'stop_lon' => 14.42],
        ['stop_id' => 'S2','stop_name' => 'End','stop_lat' => 50.082,'stop_lon' => 14.44],
    ],
    'stop_times' => [
        ['trip_id' => 'T1','stop_id' => 'S1','stop_sequence' => 1,'arrival_time' => '10:00:00','departure_time' => '10:00:00'],
        ['trip_id' => 'T1','stop_id' => 'S2','stop_sequence' => 2,'arrival_time' => '10:10:00','departure_time' => '10:10:00'],
    ],
];
$pidOnlineTimes = [['trip_id' => 'T1','stop_id' => 'S1','stop_sequence' => 1,
    'arrival_time' => '10:00:00','departure_time' => '10:00:00']];
$onlineHttp = new FakeHttp([
    'origin|2026-10-06' => new HttpResponse(200, json_encode($pidOnlineTimes)),
    'destination|2026-10-06' => new HttpResponse(200, json_encode([
        ['trip_id' => 'T1','stop_id' => 'S2','stop_sequence' => 2,'arrival_time' => '10:10:00','departure_time' => '10:10:00'],
    ])),
    'origin|2026-10-05' => new HttpResponse(200, '[]'),
    'destination|2026-10-05' => new HttpResponse(200, '[]'),
    '2026-10-06|T1' => new HttpResponse(200, json_encode($pidTripPayload)),
    'pid-departures' => new HttpResponse(200, '{"departures":[],"stops":[]}'),
    'pid-otp' => new HttpResponse(200, json_encode($payload)),
]);
foreach (['S1' => [14.42,50.075], 'S2' => [14.44,50.082]] as $stopId => $coordinates) {
    $url = $pid->resourceRequest('stop', ['external' => $stopId])->url;
    $onlineHttp->urlResponses[$url] = new HttpResponse(200, json_encode([
        'properties' => ['stop_id' => $stopId,'stop_name' => $stopId],
        'geometry' => ['coordinates' => $coordinates],
    ]));
}
$stopQuery = JourneyQuery::fromArray([
    'from-dest' => ['type' => 'stop','id' => ResourceIdCodec::encode('tram','pid','stop','S1')],
    'to-dest' => ['type' => 'stop','id' => ResourceIdCodec::encode('tram','pid','stop','S2')],
    'from-date' => '2026-10-06T09:00:00+02:00','state' => 'CZ',
]);
$onlineRegistry = new ProviderRegistry([$pid,$otpEnrichmentProvider]);
$onlineResources = new ResourceService($onlineRegistry, $onlineHttp, $r);
$onlineJourneys = new JourneyService($onlineRegistry, $onlineHttp, $r, $onlineResources);
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$otpStop = ResourceIdCodec::encode('tram','pid-otp','stop','pid:S1');
$resolvedOtpStop = $onlineResources->resolve(['type' => 'stop','id' => $otpStop]);
check($resolvedOtpStop['provider'] === 'pid' && $resolvedOtpStop['external'] === 'S1',
    'old OTP stop ID resolves through current online PID source');
$onlineResult = $onlineJourneys->search($stopQuery);
check(count($onlineResult['journeys']) === 1 && $onlineResult['journeys'][0]['source']['provider'] === 'pid'
    && $onlineResult['journeys'][0]['source']['mode'] === 'live' && $onlineResult['partial']
    && !in_array('pid-otp', $onlineHttp->requests, true), 'PID stop-to-stop search composes online direct trip without OTP');
$arriveQuery = JourneyQuery::fromArray([
    'from-dest' => ['type' => 'stop','id' => ResourceIdCodec::encode('tram','pid','stop','S1')],
    'to-dest' => ['type' => 'stop','id' => ResourceIdCodec::encode('tram','pid','stop','S2')],
    'to-date' => '2026-10-06T10:30:00+02:00','state' => 'CZ',
]);
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$arriveResult = $onlineJourneys->search($arriveQuery);
check(count($arriveResult['journeys']) === 1 && $arriveResult['journeys'][0]['legs'][0]['scheduled_arrival'] === '2026-10-06T10:10:00+02:00',
    'PID online search supports arrive-by time');
fails(fn () => $onlineJourneys->search($q), 'unsupported_capability');
$directOriginTimes = $onlineHttp->responses['origin|2026-10-06'];
$directDestinationTimes = $onlineHttp->responses['destination|2026-10-06'];
$directTrip = $onlineHttp->responses['2026-10-06|T1'];
$transferOrigin = [
    'trip_id' => 'Ttransfer1','route_id' => 'R1','route' => ['route_type' => 0,'route_short_name' => '22'],
    'stop_times' => [
        ['trip_id' => 'Ttransfer1','stop_id' => 'S1','stop_sequence' => 1,'arrival_time' => '10:00:00','departure_time' => '10:00:00'],
        ['trip_id' => 'Ttransfer1','stop_id' => 'SX','stop_sequence' => 2,'arrival_time' => '10:10:00','departure_time' => '10:10:00'],
    ],
];
$transferDestination = [
    'trip_id' => 'Ttransfer2','route_id' => 'R1','route' => ['route_type' => 0,'route_short_name' => '24'],
    'stop_times' => [
        ['trip_id' => 'Ttransfer2','stop_id' => 'SX','stop_sequence' => 1,'arrival_time' => '10:15:00','departure_time' => '10:15:00'],
        ['trip_id' => 'Ttransfer2','stop_id' => 'S2','stop_sequence' => 2,'arrival_time' => '10:25:00','departure_time' => '10:25:00'],
    ],
];
$onlineHttp->responses['origin|2026-10-06'] = new HttpResponse(200, json_encode([
    ['trip_id' => 'Ttransfer1','stop_id' => 'S1','stop_sequence' => 1,'arrival_time' => '10:00:00','departure_time' => '10:00:00'],
]));
$onlineHttp->responses['destination|2026-10-06'] = new HttpResponse(200, json_encode([
    ['trip_id' => 'Ttransfer2','stop_id' => 'S2','stop_sequence' => 2,'arrival_time' => '10:25:00','departure_time' => '10:25:00'],
]));
$onlineHttp->responses['2026-10-06|Ttransfer1'] = new HttpResponse(200, json_encode($transferOrigin));
$onlineHttp->responses['2026-10-06|Ttransfer2'] = new HttpResponse(200, json_encode($transferDestination));
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$transferResult = $onlineJourneys->search($stopQuery);
check(count($transferResult['journeys']) === 1 && $transferResult['journeys'][0]['transfers'] === 1
    && count($transferResult['journeys'][0]['legs']) === 2 && $transferResult['journeys'][0]['source']['mode'] === 'live',
    'PID online search composes verified one-stop transfer');
$transferDestination['stop_times'][0]['departure_time'] = '10:12:00';
$onlineHttp->responses['2026-10-06|Ttransfer2'] = new HttpResponse(200, json_encode($transferDestination));
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$tooShortTransfer = $onlineJourneys->search($stopQuery);
check($tooShortTransfer['journeys'] === [], 'PID rejects transfer shorter than three minutes');
$onlineHttp->responses['origin|2026-10-06'] = $directOriginTimes;
$onlineHttp->responses['destination|2026-10-06'] = $directDestinationTimes;
$onlineHttp->responses['2026-10-06|T1'] = $directTrip;
$nightTrip = [
    'trip_id' => 'Tnight','route_id' => 'R1','route' => ['route_type' => 0,'route_short_name' => '22'],
    'stop_times' => [
        ['trip_id' => 'Tnight','stop_id' => 'S1','stop_sequence' => 1,'arrival_time' => '24:05:00','departure_time' => '24:05:00'],
        ['trip_id' => 'Tnight','stop_id' => 'S2','stop_sequence' => 2,'arrival_time' => '24:15:00','departure_time' => '24:15:00'],
    ],
];
$onlineHttp->responses['origin|2026-10-06'] = new HttpResponse(200, json_encode([$nightTrip['stop_times'][0]]));
$onlineHttp->responses['destination|2026-10-06'] = new HttpResponse(200, json_encode([$nightTrip['stop_times'][1]]));
$onlineHttp->responses['origin|2026-10-07'] = new HttpResponse(200, '[]');
$onlineHttp->responses['destination|2026-10-07'] = new HttpResponse(200, '[]');
$onlineHttp->responses['2026-10-06|Tnight'] = new HttpResponse(200, json_encode($nightTrip));
$nightQuery = JourneyQuery::fromArray([
    'from-dest' => ['type' => 'stop','id' => ResourceIdCodec::encode('tram','pid','stop','S1')],
    'to-dest' => ['type' => 'stop','id' => ResourceIdCodec::encode('tram','pid','stop','S2')],
    'from-date' => '2026-10-07T00:00:00+02:00','state' => 'CZ',
]);
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$nightResult = $onlineJourneys->search($nightQuery);
check(count($nightResult['journeys']) === 1 && $nightResult['journeys'][0]['legs'][0]['service_date'] === '2026-10-06'
    && $nightResult['journeys'][0]['legs'][0]['scheduled_departure'] === '2026-10-07T00:05:00+02:00',
    'PID online search preserves previous service day after midnight');
$onlineHttp->responses['origin|2026-10-06'] = $directOriginTimes;
$onlineHttp->responses['destination|2026-10-06'] = $directDestinationTimes;
$onlineHttp->responses['origin|2026-10-06'] = new HttpResponse(200, '[]');
$onlineHttp->requests = [];
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$onlineEmpty = $onlineJourneys->search($stopQuery);
check($onlineEmpty['journeys'] === [] && !in_array('pid-otp', $onlineHttp->requests, true), 'empty PID online result never triggers local OTP');
$onlineHttp->responses['origin|2026-10-06'] = new HttpResponse(503, '');
$onlineHttp->requests = [];
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=?', ['tram']);
$onlineFallback = $onlineJourneys->search($stopQuery);
check(count($onlineFallback['journeys']) === 1 && $onlineFallback['journeys'][0]['source']['mode'] === 'fallback'
    && in_array('pid-otp', $onlineHttp->requests, true), 'PID API failure invokes local OTP fallback');
$r->providerSuccess('pid');
$pidTrip = $pid->resourceResult('trip', new HttpResponse(200, json_encode($pidTripPayload)), ['external' => 'T1','date' => '2026-10-06']);
check($pidTrip['stops'][0]['scheduled_arrival'] === '2026-10-06T10:00:00+02:00'
    && $pidTrip['stops'][0]['stop']['name'] === 'Start' && $pidTrip['stops'][0]['stop']['lat'] === 50.075,
    'PID online trip maps stop details and scheduled service-day times');
$pidHttp = new FakeHttp(['resource' => new HttpResponse(200, json_encode($pidTripPayload)), 'trip' => new HttpResponse(200, json_encode($pidTripPayload))]);
$pidResources = new ResourceService(new ProviderRegistry([$pid]), $pidHttp, $r);
$pidTripId = ResourceIdCodec::encode('tram', 'pid', 'trip', 'T1', '2026-10-06');
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
check($pidResources->resource('trip', $pidTripId)['source']['mode'] === 'live', 'PID trip detail reads online before imported catalogue');
$pidHttp->responses['resource'] = new HttpResponse(503, '');
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
$fallbackTrip = $pidResources->resource('trip', $pidTripId);
check($fallbackTrip['source']['mode'] === 'fallback' && $fallbackTrip['source']['snapshot_version'] === $version
    && str_ends_with($fallbackTrip['source']['snapshot_at'], 'Z'), 'PID trip detail uses timestamped catalogue only after online failure');
$r->providerSuccess('pid');
$args = ['expected_start' => '2026-10-06T10:00:00+02:00'];
$live = $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), $args);
check($live['realtime'] && !$live['stale'] && $live['position'] !== null && $live['delay_seconds'] === 30
    && $live['bearing'] === 180 && $live['speed_kmh'] === 32, 'PID current position is marked realtime');
$pidHttp->responses['resource'] = new HttpResponse(200, json_encode($position));
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
check($pidResources->resource('realtime', $pidTripId)['result']['realtime'], 'PID realtime verifies service instance using online trip detail');
$pidHttp->responses['trip'] = new HttpResponse(503, '');
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
fails(fn () => $pidResources->resource('realtime', $pidTripId), 'source_unavailable');
$r->providerSuccess('pid');
$position['properties']['last_position']['origin_timestamp'] = gmdate('Y-m-d\TH:i:s\Z', time() + 15);
$futurePoint = $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), $args);
check(!$futurePoint['realtime'] && $futurePoint['position'] === null,
    'vehicle observation with a future timestamp is unavailable');
$position['properties']['last_position']['origin_timestamp'] = gmdate('Y-m-d\TH:i:s\Z', time() - 31);
$overLimit = $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), $args);
check(!$overLimit['realtime'] && $overLimit['position'] === null,
    'vehicle observation older than 30 seconds is unavailable even with a higher provider setting');
$position['properties']['last_position']['origin_timestamp'] = gmdate('Y-m-d\TH:i:s\Z', time() - 600);
$stale = $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), $args);
check(!$stale['realtime'] && $stale['stale'] && $stale['position'] === null
    && $stale['observed_at'] === null && $stale['delay_seconds'] === null
    && $stale['bearing'] === null && $stale['speed_kmh'] === null,
    'PID stale observation exposes no last-known vehicle position');
$pidHttp->responses['trip'] = new HttpResponse(200, json_encode($pidTripPayload));
$pidHttp->responses['resource'] = new HttpResponse(200, json_encode($position));
$r->execute('UPDATE transport_provider SET next_request_at=NULL WHERE franchise_code=? AND code=?', ['tram','pid']);
check($pidResources->resource('realtime', $pidTripId)['result']['position'] === null,
    'public realtime endpoint never returns stale vehicle coordinates');
$position['properties']['last_position']['origin_timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
$position['properties']['last_position']['tracking'] = false;
$untracked = $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), $args);
check(!$untracked['realtime'] && $untracked['position'] === null && $untracked['speed_kmh'] === null,
    'untracked vehicle has no usable position despite recent timestamp');
$position['properties']['last_position']['tracking'] = true;
$position['geometry']['coordinates'] = [190,50.075];
$invalidPoint = $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), $args);
check(!$invalidPoint['realtime'] && $invalidPoint['position'] === null,
    'invalid vehicle coordinates are never returned as realtime');

fails(fn () => $pid->resourceResult('realtime', new HttpResponse(200, json_encode($position)), ['expected_start' => '2026-10-07T10:00:00+02:00']), 'instance_unverified');
$second = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$lock = 'tram:'.substr(hash('sha256', 'tram:pid'), 0, 50);
$stmt = $second->prepare('SELECT GET_LOCK(?,0)');
$stmt->execute([$lock]);
fails(fn () => $sync->sync('pid', $dir.'/feed.zip'), 'sync_locked');
$stmt = $second->prepare('SELECT RELEASE_LOCK(?)');
$stmt->execute([$lock]);
$receiptPath = $dir.'/graph/build-receipt.json';
$receipt = file_get_contents($receiptPath);
file_put_contents($receiptPath, '{}');
fails(fn () => $manager->activate($version, $dir.'/graph/manifest.json', 'http://127.0.0.1:19991/otp/transmodel/v3'), 'invalid_manifest');
file_put_contents($receiptPath, $receipt);
check((int)$r->activeFeed('pid')['id'] === $version, 'failed activation preserves active snapshot');
$r->providerSuccess('entur');
$r->providerSuccess('pid-otp');
$http->responses = ['entur' => new HttpResponse(200, '{"data":{"trip":{"tripPatterns":[{"legs":null}]}}}'),'pid-otp' => new HttpResponse(200, json_encode($payload))];
$result = $journeys->search($q);
check($result['partial'] && count($result['journeys']) === 1, 'malformed provider response is isolated and falls back');
// HTTP contract and transport client behavior through a real local server.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY,'-S',$address,__DIR__.'/http-router.php'], [0 => ['pipe','r'],1 => ['file',$dir.'/http.log','a'],2 => ['file',$dir.'/http.log','a']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start HTTP fixture.');
}
try {
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $ready = \App\Modules\Http\HttpModule::client()->send(new \App\Modules\Http\HttpRequest('http://'.$address.'/fixture/json', timeoutMs: 100));
        if ($ready->status === 200) {
            break;
        }
        usleep(20000);
    }
    $base = 'http://'.$address;
    $request = static function (string $path, string $method = 'GET', ?array $body = null, array $headers = []) use ($base): array {
        $response = \App\Modules\Http\HttpModule::client()->send(new \App\Modules\Http\HttpRequest(
            $base.'/api/transport/v1/'.$path,
            $method,
            array_merge(['Host: tram.test','Content-Type: application/json'], $headers),
            $body,
            timeoutMs: 3000,
        ));
        return [$response->status, json_decode($response->body, true)];
    };
    check($request('coverage')[0] === 401, 'HTTP rejects missing internal key');
    check($request('coverage', headers:['Authorization: Bearer fake'])[0] === 401, 'Bearer cannot replace internal key');
    $auth = ['X-Internal-Key: transport-test-internal-key'];
    [$status,$data] = $request('coverage', headers:$auth);
    check($status === 200 && count($data['data']['providers']) === 3, 'HTTP coverage contract');
    check($request('coverage', 'POST', headers:$auth)[0] === 405, 'HTTP rejects unregistered mutation');
    [$status,$data] = $request('journeys/search', 'POST', [], $auth);
    check($status === 422 && $data['errors']['code'] === 'invalid_date', 'HTTP validates search request');
    $client = new App\Modules\Http\HttpService();
    $results = $client->sendAll(['json' => new App\Modules\Http\HttpRequest($base.'/fixture/json'),'large' => new App\Modules\Http\HttpRequest($base.'/fixture/large', maxBytes:100),'rate' => new App\Modules\Http\HttpRequest($base.'/fixture/rate'),'redirect' => new App\Modules\Http\HttpRequest($base.'/fixture/redirect')]);
    check($results['json']->json()['ok'] === true, 'Guzzle JSON transport');
    check($results['large']->error !== null, 'Guzzle enforces response size limit');
    check($results['rate']->status === 429 && $results['rate']->retryAfter === 120, 'Guzzle preserves upstream rate limit');
    check($results['redirect']->status === 302, 'Guzzle does not follow untrusted redirects');
    $start = microtime(true);
    $result = $client->sendAll(['slow' => new App\Modules\Http\HttpRequest($base.'/fixture/slow')], 100)['slow'];
    check($result->error !== null && microtime(true) - $start < 0.5, 'Guzzle respects total deadline');
} finally {
    proc_terminate($process);
    fclose($pipes[0]);
    proc_close($process);
}
// Preserve build inputs for optional real OTP test; no connection to the application database.
if ($export = getenv('TRANSPORT_TEST_ARTIFACT_DIR')) {
    $manager->export($version, $export);
}
if ($realFeed = getenv('TRANSPORT_TEST_PID_ARCHIVE')) {
    $started = microtime(true);
    $result = $sync->sync('pid', $realFeed);
    check($result['status'] === 'ready', 'current real PID GTFS imports successfully');
    check((int)$r->activeFeed('pid')['id'] === $version, 'real import leaves active graph unchanged');
    echo 'PID import: '.json_encode($result['counts']).' in '.round(microtime(true) - $started, 1)." seconds\n";
}
require __DIR__.'/online-selection.php';
require __DIR__.'/nearest-stop.php';
echo "PASS $checks checks on isolated MySQL\n";
