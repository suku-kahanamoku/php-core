<?php

declare(strict_types=1);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/slow') {
    usleep(300000);
}
if ($path === '/large' || $path === '/gzip') {
    $body = str_repeat('x', 100000);
    if ($path === '/gzip') {
        header('Content-Encoding: gzip');
        $body = gzencode($body);
    }
    echo $body;
    exit;
}
if ($path === '/redirect') {
    header('Location: /echo', true, 302);
    exit;
}
if ($path === '/rate') {
    http_response_code(429);
    header('Retry-After: 120');
}
if ($path === '/invalid') {
    echo 'not json';
    exit;
}
$files = [];
foreach ($_FILES as $name => $file) {
    $files[$name] = ['name' => $file['name'], 'type' => $file['type'], 'content' => file_get_contents($file['tmp_name'])];
}
header('Content-Type: application/json');
echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'headers' => getallheaders(), 'body' => file_get_contents('php://input'), 'post' => $_POST, 'files' => $files], JSON_THROW_ON_ERROR);
