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
