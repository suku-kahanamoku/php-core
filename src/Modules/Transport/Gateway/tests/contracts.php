<?php

declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Gateway\{JavaTransportApi, JavaTransportService};

$javaHttp = new class implements HttpClient {
    public array $requests = [];
    public HttpResponse $response;
    public function __construct() { $this->response = new HttpResponse(200, '{"success":true,"data":{"journeys":[],"partial":false}}'); }
    public function send(HttpRequest $request): HttpResponse { $this->requests[] = $request; return $this->response; }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array { return array_map($this->send(...), $requests); }
};
$javaToken = str_repeat('test-java-', 4);
$javaGateway = new JavaTransportService($javaHttp, 'http://127.0.0.1:18091', $javaToken);
$javaBody = ['from-dest' => ['type' => 'current_location', 'lat' => 49.2, 'lon' => 16.6], 'from-date' => '2026-10-02T10:00:00Z'];
$forwarded = $javaGateway->forward('POST', '/v1/journeys/search', $javaBody);
check($forwarded['payload'] === ['success' => true, 'data' => ['journeys' => [], 'partial' => false]] && $javaHttp->requests[0]->body === $javaBody, 'Java gateway forwards JSON without routing or response mapping');
check($javaHttp->requests[0]->url === 'http://127.0.0.1:18091/transport/v1/journeys/search' && $javaHttp->requests[0]->headers['Authorization'] === 'Bearer ' . $javaToken && $javaHttp->requests[0]->timeoutMs === 24000, 'Java gateway uses injected HttpModule client and bounded per-request credentials');
$javaCredits = ['success' => true, 'data' => [['id' => 'osm_pbf:geofabrik', 'feed_id' => null, 'name' => 'ODbL', 'attribution' => 'OpenStreetMap contributors', 'license_url' => 'https://www.openstreetmap.org/copyright', 'source_url' => null, 'published_at' => null, 'updated_at' => null, 'requirements' => []]]];
$javaHttp->response = new HttpResponse(200, json_encode($javaCredits, JSON_THROW_ON_ERROR));
check($javaGateway->forward('GET', '/v1/attributions')['payload'] === $javaCredits && $javaHttp->requests[1]->url === 'http://127.0.0.1:18091/transport/v1/attributions', 'Java gateway forwards complete active-graph attribution metadata unchanged');
fails(fn() => $javaGateway->forward('GET', '/v1/attributions', [], ['limit' => 20]), 'invalid_query');
fails(fn() => $javaGateway->forward('GET', '/v1/attributions', [], ['unexpected' => 1]), 'invalid_query');
fails(fn() => $javaGateway->forward('POST', '/v1/attributions'), 'invalid_query');
fails(fn() => $javaGateway->forward('POST', '/v1/sync'), 'invalid_query');
fails(fn() => $javaGateway->forward('GET', '/v1/stops/../../sync'), 'invalid_query');
fails(fn() => $javaGateway->forward('GET', '/v1/coverage', [], ['url' => 'http://other']), 'invalid_query');
$javaHttp->response = new HttpResponse(404, '{"success":false,"errors":{"code":"not_found"}}');
check($javaGateway->forward('GET', '/v1/trips/test')['status'] === 404, 'Java gateway preserves error status and envelope');
$javaHttp->response = new HttpResponse(0, '', 'network_error');
fails(fn() => $javaGateway->forward('GET', '/v1/coverage'), 'source_unavailable');
$javaHttp->response = new HttpResponse(200, '{"token":"must-not-leak"}');
fails(fn() => $javaGateway->forward('GET', '/v1/coverage'), 'invalid_upstream');
fails(fn() => new JavaTransportService($javaHttp, 'http://user@127.0.0.1:18091', $javaToken), 'invalid_configuration');
$javaEnv = ['TRANSPORT_JAVA_ENABLED' => '1', 'TRANSPORT_JAVA_TENANT' => 'tram', 'TRANSPORT_JAVA_URL' => 'http://127.0.0.1:18091', 'TRANSPORT_JAVA_TOKEN' => $javaToken];
check(\App\Modules\Transport\TransportModule::api('tram', $javaEnv, $javaHttp) instanceof JavaTransportApi, 'Java gateway activates only for configured tenant without transport repository');
fails(fn() => \App\Modules\Transport\TransportModule::api('other', $javaEnv, $javaHttp), 'invalid_configuration');
fails(fn() => \App\Modules\Transport\TransportModule::api('tram', array_replace($javaEnv, ['TRANSPORT_JAVA_ENABLED' => '0']), $javaHttp), 'invalid_configuration');
fails(fn() => \App\Modules\Transport\TransportModule::api('', $javaEnv, $javaHttp), 'invalid_configuration');
$javaHttp->response = new HttpResponse(429, '{"success":false,"errors":{"code":"planner_busy"}}', retryAfter: 7);
$busy = $javaGateway->forward('GET', '/v1/coverage');
check($busy['status'] === 429 && $busy['retry_after'] === 7 && $busy['payload']['errors']['code'] === 'planner_busy', 'Java rate limits and retry delay are preserved');
$javaHttp->response = new HttpResponse(200, '{"success":true,"data":{"stops":[{"scheduled_departure":"12:00","expected_departure":"12:08"}]}}');
$times = $javaGateway->forward('GET', '/v1/trips/test', [], ['stop_coordinates' => 0]);
check($times['payload']['data']['stops'][0] === ['scheduled_departure' => '12:00', 'expected_departure' => '12:08'], 'Gateway never recalculates scheduled or expected times');
check(str_ends_with($javaHttp->requests[array_key_last($javaHttp->requests)]->url, '?stop_coordinates=0'), 'Gateway forwards allowed resource query');
echo "PASS $checks Java gateway contract checks\n";
unset($javaHttp, $javaGateway, $javaEnv, $javaBody, $javaToken, $javaCredits, $forwarded);
