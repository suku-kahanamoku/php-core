<?php

declare(strict_types=1);

header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/health') {
    echo '{"ok":true}';
    exit;
}
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer java-gateway-test-java-token-123456789') {
    http_response_code(401);
    echo '{"success":false,"errors":{"code":"unauthorized"}}';
    exit;
}
if ($path === '/transport/v1/trips/limited') {
    http_response_code(429);
    header('Retry-After: 11');
    echo '{"success":false,"errors":{"code":"planner_busy"}}';
    exit;
}
if ($path === '/transport/v1/trips/invalid-response') {
    echo '{"secret":"upstream-test-secret"}';
    exit;
}
if ($path === '/transport/v1/trips/missing') {
    http_response_code(404);
    echo '{"success":false,"errors":{"code":"not_found"}}';
    exit;
}
$stop = ['id' => 'metadata', 'name' => 'Hub', 'state' => 'CZ', 'city' => 'Brno',
    'modes' => ['bus', 'tram', 'trolleybus'], 'transport_scope' => 'mixed'];
$body = json_decode(file_get_contents('php://input'), true);
if ($path === '/transport/v1/cities/search'
    && ($body['q']['name']['$regex'] ?? null) === '__compressed_catalogue__') {
    if (!str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
        http_response_code(406);
        echo '{"success":false,"errors":{"code":"compression_required"}}';
        exit;
    }
    $cities = [];
    for ($index = 0; $index < 1500; ++$index) {
        $cities[] = ['id' => 'fixture-' . $index, 'name' => 'Žďár ' . $index, 'state' => 'CZ'];
    }
    $encoded = gzencode(json_encode(['success' => true, 'data' => ['data' => $cities, 'partial' => false]], JSON_THROW_ON_ERROR));
    header('Content-Encoding: gzip');
    header('Content-Length: ' . strlen($encoded));
    header('Vary: Accept-Encoding');
    echo $encoded;
    exit;
}
if ($path === '/transport/v1/trips/estimated/observation') {
    echo json_encode(['success' => true, 'data' => [
        'status' => 'estimated', 'position' => null, 'delay_seconds' => null, 'cancelled' => null,
        'observed_at' => '2026-10-04T10:00:00Z', 'valid_until' => '2026-10-04T10:00:30Z',
        'estimated_progress' => ['from_index' => 0, 'to_index' => 1, 'from_stop_id' => 'A', 'to_stop_id' => 'B',
            'from_departure' => '2026-10-04T09:50:00Z', 'to_arrival' => '2026-10-04T10:10:00Z',
            'fraction' => 0.5, 'at_stop' => false, 'observed_at' => '2026-10-04T10:00:00Z', 'valid_until' => '2026-10-04T10:00:30Z'],
    ]], JSON_THROW_ON_ERROR);
    exit;
}
$metadataResponse = match ($path) {
    '/transport/v1/stops/metadata' => ['result' => $stop],
    '/transport/v1/trips/metadata' => ['result' => ['stops' => [['stop' => $stop]]]],
    '/transport/v1/journeys/metadata' => ['legs' => [['mode' => 'tram', 'from' => $stop, 'to' => $stop]]],
    '/transport/v1/places/search' => ($body['q']['name']['$regex'] ?? null) === 'Hub'
        ? ['data' => [$stop], 'partial' => false] : null,
    '/transport/v1/journeys/search' => ($body['from-dest']['id'] ?? null) === 'metadata'
        ? ['journeys' => [['legs' => [['mode' => 'tram', 'from' => $stop, 'to' => $stop]]]],
            'resolved_places' => ['from' => $stop, 'to' => $stop], 'partial' => false] : null,
    default => null,
};
if ($metadataResponse !== null) {
    echo json_encode(['success' => true, 'data' => $metadataResponse], JSON_THROW_ON_ERROR);
    exit;
}
echo json_encode(['success' => true, 'data' => [
    'path' => $path,
    'method' => $_SERVER['REQUEST_METHOD'],
    'body' => $body,
    'query' => $_GET,
]], JSON_THROW_ON_ERROR);
