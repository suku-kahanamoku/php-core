<?php

declare(strict_types=1);
use App\Modules\Transport\Tracking\{TrackingTicketService,TrackingObservationMapper,TrackingHubService,TripTrackingService};
use App\Modules\Transport\Core\JourneyTimingService;
use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Http\Contracts\AsyncHttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};

$ticketService = new TrackingTicketService(str_repeat('test-secret-', 4));
$tripId = ResourceIdCodec::encode('tram', 'pid', 'trip', 'trip-live', '2026-10-01');
$issued = $ticketService->issue('tram', $tripId, time());
check($ticketService->verify($issued['ticket'], time())['trip'] === $tripId, 'tracking ticket binds exact dated trip and tenant');
foreach ([$issued['ticket'].'x', $ticketService->issue('tram', $tripId, time() - 901)['ticket']] as $bad) {
    $rejected = false;
    try {
        $ticketService->verify($bad, time());
    } catch (Throwable) {
        $rejected = true;
    }
    check($rejected, 'tracking rejects forged or expired ticket');
}
$now = time();
$raw = ['realtime' => true,'position' => ['type' => 'Point','coordinates' => [14.4,50.1]],'observed_at' => gmdate(DATE_RFC3339, $now),'delay_seconds' => 480];
check(TrackingObservationMapper::map($raw, $now)['delay_seconds'] === 480, 'fresh provider observation exposes eight minute delay');
check(TrackingObservationMapper::map($raw, $now + 30)['position'] === null, 'GPS disappears at thirty seconds without another source response');
check(TrackingObservationMapper::map(array_replace($raw, ['observed_at' => gmdate(DATE_RFC3339, $now + 6)]), $now)['position'] === null, 'future GPS observation rejected');
check(TrackingObservationMapper::map(array_replace($raw, ['position' => ['type' => 'Point','coordinates' => [181,50]]]), $now)['position'] === null, 'invalid GPS coordinate rejected');
$fakeAsync = new class () implements AsyncHttpClient {
    public array $requests = [];
    public array $callbacks = [];
    public function send(HttpRequest $r): HttpResponse
    {
        throw new RuntimeException('Blocking call in gateway');
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        throw new RuntimeException('Blocking call in gateway');
    }
    public function sendAsync(HttpRequest $r, callable $complete): void
    {
        $this->requests[] = $r;
        $this->callbacks[] = $complete;
    }
};
$hub = new TrackingHubService($ticketService, $fakeAsync, 'http://127.0.0.1:9999', 'server-key', ['tram' => 'tram.test']);
$messages = [];
$closed = [];
foreach (['a','b'] as $peer) {
    $hub->message($peer, ['type' => 'subscribe','ticket' => $issued['ticket']], function ($m) use (&$messages, $peer) {
        $messages[$peer][] = $m;
    }, function () use (&$closed, $peer) {
        $closed[] = $peer;
    });
}
$hub->tick();
$hub->tick();
check(count($fakeAsync->requests) === 1, 'two subscribers share one pending nonblocking upstream request');
($fakeAsync->callbacks[0])(new HttpResponse(200, json_encode(['success' => true,'data' => TrackingObservationMapper::map($raw, time())])));
check(count($messages['a']) === 2 && count($messages['b']) === 2, 'gateway fans out observation to both subscribers');
$hub->closed('a');
$hub->closed('b');
$hub->message('c', ['type' => 'subscribe','ticket' => $issued['ticket']], function ($m) use (&$messages) {
    $messages['c'][] = $m;
}, static function () {
});
($fakeAsync->callbacks[0])(new HttpResponse(200, json_encode(['data' => TrackingObservationMapper::map($raw, time())])));
check(count($messages['c']) === 1, 'late callback cannot leak into a new subscription generation');
$other = $ticketService->issue('other', ResourceIdCodec::encode('other', 'pid', 'trip', 't', '2026-10-01'), time());
$hub->message('foreign', ['type' => 'subscribe','ticket' => $other['ticket']], static function () {
}, function () use (&$closed) {
    $closed[] = 'foreign';
});
check(in_array('foreign', $closed, true), 'gateway rejects tenant absent from server allowlist');
$leg = static fn ($dep, $arr, $expected = null, $mode = 'bus') => ['mode' => $mode,'scheduled_departure' => '2026-10-01T'.$dep.':00Z','scheduled_arrival' => '2026-10-01T'.$arr.':00Z','expected_departure' => $expected ? '2026-10-01T'.$expected.':00Z' : null,'expected_arrival' => null,'realtime' => $expected !== null];
$journey = ['legs' => [$leg('08:00', '08:20', '08:08'),$leg('08:25', '09:00')],'transfers' => 1];
check(JourneyTimingService::update($journey) === null, 'eight minute late feeder misses five minute scheduled connection');
$journey['legs'][1] = $leg('08:25', '09:00', '08:35');
$updated = JourneyTimingService::update($journey);
check($updated !== null && $updated['legs'][0]['arrival_estimated'] && $updated['legs'][1]['expected_departure'] === '2026-10-01T08:35:00Z', 'independently delayed second service remains reachable and is never shifted by first delay');
$walk = $leg('08:20', '08:24', null, 'walk');
$walkJourney = ['legs' => [$leg('08:00', '08:20', '08:08'),$walk,$leg('08:30', '09:00')],'transfers' => 1];
check(JourneyTimingService::update($walkJourney) === null, 'walking transfer starts after delayed arrival and retains full walking duration');
$walkJourney['legs'][2] = $leg('08:35', '09:00');
check(JourneyTimingService::update($walkJourney)['legs'][1]['expected_arrival'] === '2026-10-01T08:32:00+00:00', 'walking arrival carries delay once');
$journey['legs'][0]['expected_arrival'] = '2026-10-01T08:21:00Z';
$journey['legs'][1] = $leg('08:25', '09:00');
check(JourneyTimingService::update($journey) !== null, 'stop-specific arrival overrides constant-delay estimate');
$journey['legs'][1]['cancelled'] = true;
check(JourneyTimingService::update($journey) === null, 'cancelled services cannot remain viable search results');
$clean = \App\Modules\Transport\Model\JourneyCacheMapper::sanitize(['legs' => [array_replace($leg('08:00', '08:20', '08:08'), ['delay_seconds' => 480,'position' => $raw['position'],'observed_at' => $raw['observed_at'],'arrival_estimated' => true])]], true);
check(!str_contains(json_encode($clean), 'observed_at') && !str_contains(json_encode($clean), 'delay_seconds') && !str_contains(json_encode($clean), 'position'), 'tracking and delay estimates never enter journey SQL cache');
$minJourney = ['legs' => [$leg('08:00', '08:20', '08:03'),array_replace($leg('08:25', '09:00'), ['min_transfer_seconds' => 180])],'transfers' => 1];
check(JourneyTimingService::update($minJourney) === null, 'provider-specific three minute transfer floor survives delay enrichment');
$rankingQuery = new \App\Modules\Transport\Model\JourneyQuery([], [], new DateTimeImmutable('2026-10-01T07:00:00Z'), false, 'CZ', null, ['bus'], 5, 1);
$ranked = JourneyTimingService::rank([['key' => 'late','legs' => [$leg('08:00', '08:20', '08:10')]],['key' => 'earlier','legs' => [$leg('08:05', '08:25')]]], $rankingQuery);
check(count($ranked) === 1 && $ranked[0]['key'] === 'earlier', 'final LIMIT follows realtime ordering instead of keeping formerly fastest result');
$arrivalQuery = new \App\Modules\Transport\Model\JourneyQuery([], [], new DateTimeImmutable('2026-10-01T08:25:00Z'), true, 'CZ', null, ['bus'], 5, 10);
check(JourneyTimingService::rank([['legs' => [$leg('08:00', '08:20', '08:10')]]], $arrivalQuery) === [], 'arrival-by deadline is rechecked after applying delay');
$cached = \App\Modules\Transport\Model\JourneyCacheMapper::sanitize(['duration_seconds' => 9999,'legs' => [$leg('08:00', '08:20', '08:10')]]);
check($cached['duration_seconds'] === 1200, 'persistent cache duration also uses scheduled times only');

// An injected server double can drive the real hub without Workerman or network listeners.
$serverDouble = new class implements \App\Modules\Http\Contracts\WebSocketServer {
    public array $callbacks = [];
    public function run(string $listen, array $origins, callable $message, callable $closed, callable $tick, string $name = 'websocket', ?string $runtimeDirectory = null): void
    {
        $this->callbacks = [$message, $closed, $tick];
    }
};
$contractAsync = clone $fakeAsync;
$contractAsync->requests = $contractAsync->callbacks = [];
$contractHub = new TrackingHubService($ticketService, $contractAsync, 'http://127.0.0.1:9999', 'server-key', ['tram' => 'tram.test']);
$runServer = static function (\App\Modules\Http\Contracts\WebSocketServer $server) use ($contractHub): void {
    $server->run('websocket://127.0.0.1:8091', ['https://tram.test'], $contractHub->message(...), $contractHub->closed(...), $contractHub->tick(...));
};
$runServer($serverDouble);
[$onMessage, $onClosed, $onTick] = $serverDouble->callbacks;
$contractMessages = [];
$contractClosed = false;
$send = static function (array $value) use (&$contractMessages): void { $contractMessages[] = $value; };
$close = static function () use (&$contractClosed): void { $contractClosed = true; };
$onMessage('mock-peer', ['type' => 'subscribe', 'ticket' => $issued['ticket']], $send, $close);
$onTick();
check(count($contractAsync->requests) === 1 && $contractMessages[0]['data']['status'] === 'connecting', 'WebSocket mock starts a real tracking subscription via the contract callbacks');
($contractAsync->callbacks[0])(new HttpResponse(200, json_encode(['success' => true, 'data' => TrackingObservationMapper::map($raw, time())])));
check(end($contractMessages)['data']['status'] === 'live', 'WebSocket mock receives actual hub observations through its send callback');
$onMessage('mock-peer', ['type' => 'ping'], $send, $close);
check(end($contractMessages)['type'] === 'pong', 'WebSocket mock supports gateway heartbeat without transport-specific objects');
$onClosed('mock-peer');
$onTick();
check(count($contractAsync->requests) === 1, 'WebSocket close callback releases the last upstream watch');
$onMessage('invalid-peer', ['type' => 'subscribe', 'ticket' => 'invalid'], $send, $close);
check($contractClosed, 'WebSocket mock exposes policy close callback for invalid tracking tickets');
