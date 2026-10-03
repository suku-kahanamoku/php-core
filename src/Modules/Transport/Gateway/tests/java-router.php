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
echo json_encode(['success' => true, 'data' => [
    'path' => $path,
    'method' => $_SERVER['REQUEST_METHOD'],
    'body' => json_decode(file_get_contents('php://input'), true),
    'query' => $_GET,
]], JSON_THROW_ON_ERROR);
