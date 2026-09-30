<?php

declare(strict_types=1);

require dirname(__DIR__, 4).'/vendor/autoload.php';

use App\Modules\Http\HttpModule;
use App\Modules\Http\HttpRequest;

$dsn = getenv('SRY_TEST_DSN') ?: '';
if (!preg_match('~^mysql:unix_socket=/tmp/sry-db\.[^/;]+/mysql\.sock;dbname=sry_test;charset=utf8mb4$~D', $dsn)) {
    throw new RuntimeException('Disposable sry_test database required.');
}
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    throw new RuntimeException('Cannot reserve fixture port.');
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, __DIR__.'/http-router.php'], [
    0 => ['pipe', 'r'],
    1 => ['file', getenv('SRY_TEST_LOG') ?: '/dev/null', 'a'],
    2 => ['file', getenv('SRY_TEST_LOG') ?: '/dev/null', 'a'],
], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot launch fixture HTTP server.');
}
$checks = 0;
function verify(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL '.$label);
    }
    ++$checks;
    echo 'PASS '.$label.PHP_EOL;
}
try {
    $base = 'http://'.$address.'/api/sry';
    $request = static function (string $method, string $path, ?array $body = null, ?string $token = null, string $host = 'sry.test') use ($base): array {
        $headers = ['Host' => $host, 'Accept' => 'application/json', 'Accept-Language' => 'cs'];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        $response = HttpModule::client()->send(new HttpRequest($base.$path, $method, $headers, $body, timeoutMs: 3000));
        if ($response->error !== null) {
            throw new RuntimeException('Fixture HTTP transfer failed: '.$response->error);
        }
        return [$response->status, $response->body === '' ? null : json_decode($response->body, true, 32, JSON_THROW_ON_ERROR)];
    };
    $status = 0;
    for ($attempt = 0; $attempt < 100; ++$attempt) {
        try {
            [$status] = $request('GET', '/auth/me');
            if ($status === 401) {
                break;
            }
        } catch (Throwable) {
        }
        usleep(20000);
    }
    verify($status === 401, 'fixture started and Sry rejects missing session');
    $preflight = HttpModule::client()->send(new HttpRequest(
        $base.'/auth/me', 'OPTIONS', [
            'Host' => 'sry.test',
            'Origin' => 'https://app.sorry-jako.eu',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type,accept-language',
        ], timeoutMs: 3000,
    ));
    verify(
        $preflight->status === 204
        && stripos($preflight->header('Access-Control-Allow-Headers'), 'Accept-Language') !== false,
        'browser preflight permits mobile language header',
    );
    [$status, $payload] = $request('GET', '/auth/me', host: 'other.test');
    verify($status === 403 && ($payload['code'] ?? '') === 'forbidden', 'Sry tenant boundary over HTTP');
    [$status, $payload] = $request('POST', '/auth/signup', ['name' => 'HTTP parent', 'email' => 'wire-'.bin2hex(random_bytes(5)).'@example.test', 'password' => 'a-long-password']);
    $session = $payload['data'] ?? [];
    $token = $session['token'] ?? '';
    verify($status === 200 && ($payload['success'] ?? false) === true && preg_match('/^[a-f0-9]{64}$/D', $token) === 1 && ($session['member']['role'] ?? '') === 'admin', 'mobile signup envelope and session');
    [$status, $payload] = $request('GET', '/auth/me', token: $token);
    verify($status === 200 && ($payload['data']['id'] ?? null) === $session['member']['id'], 'mobile session restore');
    [$status, $payload] = $request('GET', '/family', token: $token);
    verify($status === 200 && is_array($payload['data']['members'] ?? null) && is_string($payload['data']['today'] ?? null), 'mobile family payload');
    [$status, $payload] = $request('POST', '/family', ['name' => 'HTTP child'], $token);
    $child = $payload['data'] ?? [];
    verify($status === 200 && is_int($child['id'] ?? null) && ($child['role'] ?? '') === 'user', 'mobile child creation');
    $emailChild = 'child-'.bin2hex(random_bytes(5)).'@example.test';
    [$status, $payload] = $request('POST', '/family', [
        'name' => 'HTTP child with account', 'email' => $emailChild, 'password' => 'child-password-123',
    ], $token);
    $accountChild = $payload['data'] ?? [];
    verify($status === 200 && is_int($accountChild['id'] ?? null), 'mobile child credentials persisted');
    [$status, $payload] = $request('POST', '/auth/login', [
        'email' => $emailChild, 'password' => 'child-password-123',
    ]);
    $childToken = $payload['data']['token'] ?? '';
    verify(
        $status === 200 && ($payload['data']['member']['id'] ?? null) === $accountChild['id']
        && ($payload['data']['member']['role'] ?? '') === 'user',
        'child can sign in with credentials supplied by mobile',
    );
    [$status, $payload] = $request('PATCH', '/family/'.$child['id'], ['daily_target' => 15, 'wifi_allowed' => true, 'data_allowed' => false], $token);
    verify($status === 200 && ($payload['data']['daily_target'] ?? null) === 15, 'mobile child policy update');
    [$status, $payload] = $request('GET', '/catalog', token: $token);
    verify($status === 200 && is_array($payload['data']['categories'] ?? null) && is_array($payload['data']['enumerations'] ?? null), 'mobile catalog payload');
    $categories = $payload['data']['categories'] ?? [];
    verify(count($categories) === 4, 'published categories retained through HTTP filter');
    [$status, $payload] = $request('POST', '/tasks', [
        'member_id' => $accountChild['id'], 'title' => 'HTTP task', 'description' => '',
        'points' => 10, 'due_date' => $request('GET', '/family', token: $token)[1]['data']['today'],
        'category_id' => $categories[0]['id'],
    ], $token);
    $taskId = $payload['data']['id'] ?? null;
    verify($status === 200 && is_int($taskId), 'parent creates categorized child task over HTTP');
    [$status, $payload] = $request('GET', '/tasks/'.$taskId, token: $childToken);
    verify($status === 200 && ($payload['data']['id'] ?? null) === $taskId, 'child reads assigned task over HTTP');
    [$status, $payload] = $request('GET', '/tasks/'.$taskId, token: $token);
    verify($status === 200 && ($payload['data']['id'] ?? null) === $taskId, 'parent reads assigned task over HTTP');
    [$status, $payload] = $request('POST', '/auth/signup', [
        'name' => 'Other HTTP parent',
        'email' => 'other-'.bin2hex(random_bytes(5)).'@example.test',
        'password' => 'a-long-password',
    ]);
    $otherToken = $payload['data']['token'] ?? '';
    verify($status === 200 && preg_match('/^[a-f0-9]{64}$/D', $otherToken) === 1, 'second family signup over HTTP');
    [$status, $payload] = $request('GET', '/tasks/'.$taskId, token: $otherToken);
    verify($status === 404 && ($payload['code'] ?? '') === 'notFound', 'other family cannot read task over HTTP');
    [$status, $payload] = $request('GET', '/tasks', token: $token);
    verify($status === 200 && is_array($payload['data'] ?? null), 'mobile task list payload');
    [$status, $payload] = $request('POST', '/invitations', ['child_id' => $child['id']], $token);
    verify($status === 200 && preg_match('/^[a-f0-9]{64}$/D', $payload['data']['token'] ?? '') === 1, 'mobile pairing invitation');
    $invitationToken = $payload['data']['token'];
    [$status, $payload] = $request('POST', '/auth/join', ['token' => $invitationToken, 'name' => 'Ignored']);
    verify($status === 200 && ($payload['data']['member']['id'] ?? null) === $child['id'], 'QR invitation links existing child over HTTP');
    [$status, $payload] = $request('POST', '/auth/join', ['token' => $invitationToken, 'name' => 'Replay']);
    verify($status === 410 && ($payload['code'] ?? '') === 'invitationInvalid', 'QR invitation rejects replay over HTTP');
    [$status, $payload] = $request('POST', '/push', ['token' => 'ExpoPushToken[http-fixture]', 'language' => 'cs'], $token);
    verify($status === 200 && ($payload['data']['updated'] ?? null) === true, 'mobile push registration');
    [$status, $payload] = $request('POST', '/chat', [
        'recipient_id' => $session['member']['id'], 'body' => 'Finished my task',
    ], $childToken);
    verify($status === 200 && is_int($payload['data']['id'] ?? null), 'child sends chat message over HTTP');
    [$status, $payload] = $request('GET', '/chat', token: $token);
    verify($status === 200 && ($payload['data'][0]['body'] ?? '') === 'Finished my task', 'parent receives chat message over HTTP');
    [$status, $payload] = $request('GET', '/notifications', token: $childToken);
    $notification = $payload['data'][0] ?? [];
    verify($status === 200 && is_int($notification['id'] ?? null), 'child receives task notification over HTTP');
    [$status, $payload] = $request('POST', '/notifications/'.$notification['id'].'/read', [], $childToken);
    verify($status === 200 && ($payload['data']['updated'] ?? false) === true, 'child marks notification read over HTTP');
    [$status, $payload] = $request('POST', '/media', ['mime' => 'image/jpeg', 'size' => 12], $childToken);
    verify(
        $status === 200
        && is_int($payload['data']['id'] ?? null)
        && str_starts_with($payload['data']['url'] ?? '', 'https://media.example/media?ticket='),
        'child receives signed upload URL over HTTP',
    );
    [$status, $payload] = $request('GET', '/media/'.$payload['data']['id'], token: $childToken);
    verify($status === 404 && ($payload['code'] ?? '') === 'notFound', 'unfinished media remains private over HTTP');
    [$status, $payload] = $request('POST', '/auth/reset-password', ['email' => 'missing@example.test']);
    verify($status === 200 && ($payload['data']['requested'] ?? false) === true, 'unknown reset address gets generic reply without SMTP');
    [$status, $payload] = $request('GET', '/realtime', token: $token);
    verify($status === 200 && str_starts_with($payload['data']['url'] ?? '', 'wss://media.example/connect?ticket='), 'mobile realtime ticket URL');
    [$status, $payload] = $request('POST', '/auth/logout', token: $token);
    verify($status === 200 && ($payload['data']['logged_out'] ?? null) === true, 'mobile logout');
    [$status, $payload] = $request('GET', '/auth/me', token: $token);
    verify($status === 401 && ($payload['code'] ?? '') === 'unauthorized', 'logout revokes HTTP session');
    echo "Sry HTTP contract: $checks assertions passed".PHP_EOL;
} finally {
    proc_terminate($process);
    fclose($pipes[0]);
    proc_close($process);
}
