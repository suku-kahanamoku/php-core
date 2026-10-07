<?php

declare(strict_types=1);

require __DIR__ . '/contracts.php';

use App\Modules\Http\{HttpModule, HttpRequest};

$processes = [];
try {
    foreach (['java-router.php', 'http-router.php'] as $file) {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($file === 'java-router.php') {
            putenv('JAVA_GATEWAY_TEST_JAVA_URL=http://' . $address);
        }
        $process = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/' . $file], [0 => ['pipe', 'r'], 1 => ['file', getenv('JAVA_GATEWAY_TEST_DIR') . '/http.log', 'a'], 2 => ['file', getenv('JAVA_GATEWAY_TEST_DIR') . '/http.log', 'a']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start HTTP fixture.');
        }
        $processes[] = [$process, $pipes];
        $ready = false;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $response = HttpModule::client()->send(new HttpRequest('http://' . $address . '/health', timeoutMs: 100));
            if ($response->status === 200) {
                $ready = true;
                break;
            }
            usleep(20000);
        }
        check($ready, 'HTTP fixture starts: ' . $file);
    }
    $base = 'http://' . $address . '/api/transport/v1/';
    $request = static function (string $path, string $method = 'GET', ?array $body = null, array $headers = []) use ($base) {
        return HttpModule::client()->send(new HttpRequest($base . $path, $method, array_merge(['Host: tram.test', 'X-Internal-Key: java-gateway-test-internal-key'], $headers), $body, timeoutMs: 3000));
    };
    check($request('coverage', headers: ['X-Internal-Key: wrong'])->status === 401, 'PHP gateway rejects invalid internal key');
    foreach (['coverage', 'attributions', 'journeys/test', 'journeys/test/geometry', 'stops/test', 'stops/test/departures', 'trips/test', 'trips/test/realtime', 'trips/test/observation'] as $path) {
        $response = $request($path);
        check($response->status === 200 && $response->json()['data']['path'] === '/transport/v1/' . $path, 'Authenticated GET route forwards: ' . $path);
    }
    $body = ['q' => ['state' => 'SK', 'latitude' => 49.2, 'longitude' => 16.6, 'observed_at' => '2026-10-03T12:00:00Z']];
    $estimated = $request('trips/estimated/observation')->json()['data'];
    check($estimated['status'] === 'estimated' && $estimated['position'] === null && $estimated['delay_seconds'] === null
        && $estimated['estimated_progress']['fraction'] === 0.5
        && $estimated['estimated_progress']['from_stop_id'] === 'A'
        && $estimated['estimated_progress']['valid_until'] === '2026-10-04T10:00:30Z',
        'Backend timetable progress and its original expiry survive PHP forwarding without fake GPS or delay');
    foreach (['places/search', 'cities/search', 'journeys/search', 'trips/test/tracking'] as $path) {
        $response = $request($path, 'POST', $body);
        check($response->status === 200 && $response->json()['data']['body'] === $body, 'POST JSON is forwarded unchanged: ' . $path);
    }
    $compressedCities = $request('cities/search', 'POST', ['q' => ['state' => 'CZ', 'name' => ['$regex' => '__compressed_catalogue__']]]);
    $expectedCities = [];
    for ($index = 0; $index < 1500; ++$index) {
        $expectedCities[] = ['id' => 'fixture-' . $index, 'name' => 'Žďár ' . $index, 'state' => 'CZ'];
    }
    check($compressedCities->status === 200
        && $compressedCities->json() === ['success' => true, 'data' => ['data' => $expectedCities, 'partial' => false]],
        'Gateway negotiates gzip and forwards the complete decoded Unicode catalogue unchanged');
    $stop = ['id' => 'metadata', 'name' => 'Hub', 'state' => 'CZ', 'city' => 'Brno',
        'modes' => ['bus', 'tram', 'trolleybus'], 'transport_scope' => 'mixed'];
    $places = $request('places/search', 'POST', ['q' => ['name' => ['$regex' => 'Hub'], 'state' => 'CZ'],
        'projection' => 'id,name,state,city,modes,transport_scope']);
    check($places->status === 200 && $places->json()['data']['data'] === [$stop], 'Stop modes and scope survive HTTP autocomplete forwarding');
    check($request('stops/metadata')->json()['data']['result'] === $stop, 'Stop detail preserves transport metadata');
    check($request('trips/metadata')->json()['data']['result']['stops'][0]['stop'] === $stop, 'Trip stop preserves transport metadata');
    check($request('journeys/metadata')->json()['data']['legs'][0]['from'] === $stop, 'Stored journey preserves transport metadata');
    $journeys = $request('journeys/search', 'POST', ['from-dest' => ['type' => 'stop', 'id' => 'metadata']])->json()['data'];
    check($journeys['journeys'][0]['legs'][0]['to'] === $stop && $journeys['resolved_places']['from'] === $stop,
        'Search journey and resolved places preserve transport metadata');
    check($request('coverage', headers: ['Host: other.test'])->status === 503, 'Unconfigured tenant cannot reach Java or fall back to PHP');
    check($request('coverage', 'POST')->status === 405, 'Only registered methods are exposed');
    check($request('sync', 'POST')->status === 404, 'Java administration is not exposed');
    check($request('trips/test?url=http://other')->status === 422, 'Client cannot select upstream target');
    check($request('trips/missing')->status === 404, 'Upstream not_found status survives forwarding');
    $limited = $request('trips/limited');
    check($limited->status === 429 && $limited->retryAfter === 11, 'Upstream backpressure survives forwarding');
    $invalid = $request('trips/invalid-response');
    check($invalid->status === 502 && !str_contains($invalid->body, 'upstream-test-secret'), 'Malformed upstream response never leaks raw body');
} finally {
    foreach (array_reverse($processes) as [$process, $pipes]) {
        proc_terminate($process);
        fclose($pipes[0]);
        proc_close($process);
    }
    putenv('JAVA_GATEWAY_TEST_JAVA_URL');
}
echo "PASS $checks Java gateway checks with database access forbidden\n";
