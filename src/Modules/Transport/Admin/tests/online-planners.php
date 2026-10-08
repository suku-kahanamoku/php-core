<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/Modules/Http/Contracts/HttpClient.php';
require $root . '/Modules/Http/HttpRequest.php';
require $root . '/Modules/Http/HttpResponse.php';
require $root . '/Modules/Transport/Gateway/JavaTransportException.php';
require $root . '/Modules/Transport/Admin/OnlinePlannerService.php';
require $root . '/Modules/Transport/TransportModule.php';
require $root . '/Modules/Router/Request.php';
require $root . '/Modules/Transport/Admin/OnlinePlannerApi.php';

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\TransportModule;
use App\Modules\Transport\Gateway\JavaTransportException;
use App\Modules\Router\Request;
use App\Modules\Transport\Admin\OnlinePlannerApi;

function ensure(bool $ok): void { if (!$ok) { throw new RuntimeException('Pipeline admin assertion failed.'); } }
function rejected(callable $call): void {
    try { $call(); } catch (JavaTransportException) { return; }
    throw new RuntimeException('Expected rejected pipeline operation.');
}
$http = new class implements HttpClient {
    public array $requests = [];
    public array $payload = ['enabled' => true];
    public function send(HttpRequest $request): HttpResponse {
        $this->requests[] = $request;
        return new HttpResponse(202, json_encode($this->payload, JSON_THROW_ON_ERROR));
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array { throw new RuntimeException('Batch not expected.'); }
};
$env = ['TRANSPORT_ONLINE_CONTROL_ENABLED' => '1', 'TRANSPORT_JAVA_TENANT' => 'tram',
    'TRANSPORT_JAVA_URL' => 'https://tram.example.test', 'TRANSPORT_ONLINE_CONTROL_TOKEN' => 'synthetic-admin-token-not-a-secret'];
rejected(fn() => TransportModule::onlinePlanners('another-tenant', $env, $http));
rejected(fn() => TransportModule::onlinePlanners('tram', array_replace($env, ['TRANSPORT_ONLINE_CONTROL_ENABLED' => '0']), $http));
$splitEnv = array_replace($env, ['TRANSPORT_JAVA_URL' => 'http://127.0.0.1:18095', 'TRANSPORT_ONLINE_CONTROL_URL' => 'https://tram.example.test']);
ensure(TransportModule::onlinePlanners('tram', $splitEnv, $http)->onlinePlanners()['enabled'] === true);
$http->requests = [];
$pipeline = TransportModule::onlinePlanners('tram', $env, $http);
$authorized = false;
$api = new OnlinePlannerApi(static function () use (&$authorized): void {
    if (!$authorized) throw new JavaTransportException('forbidden', 'Admin required.', 403);
}, static fn(string $tenant) => TransportModule::onlinePlanners($tenant, $env, $http));
function plannerRequest(string $method = 'GET', array $body = [], array $query = [], bool $internal = true, string $tenant = 'tram', string $uri = '/online-planners'): Request {
    $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
    foreach (['method' => $method, 'uri' => $uri, 'body' => $body,
        'query' => $query, 'franchiseCode' => $tenant] as $property => $value) {
        (new ReflectionProperty(Request::class, $property))->setValue($request, $value);
    }
    $request->internalAuthenticated = $internal;
    return $request;
}
$before = count($http->requests);
rejected(fn() => $api->execute(plannerRequest(internal: false)));
rejected(fn() => $api->execute(plannerRequest()));
$authorized = true;
rejected(fn() => $api->execute(plannerRequest(tenant: 'other')));
rejected(fn() => $api->execute(plannerRequest('DELETE')));
rejected(fn() => $api->execute(plannerRequest(query: ['url' => 'https://evil.test'])));
rejected(fn() => $api->execute(plannerRequest('POST', ['action' => 'deploy', 'command' => 'x'])));
rejected(fn() => $api->execute(plannerRequest('POST', ['action' => 'rm -rf /'])));
ensure(count($http->requests) === $before);
rejected(fn() => $api->execute(plannerRequest(uri: '/local-pipeline')));
$http->payload = ['enabled' => true, 'token' => 'must-not-enter-browser'];
ensure($api->execute(plannerRequest(uri: '/online-planners')) === ['enabled' => true]);
$http->payload = ['enabled' => false];
ensure($api->execute(plannerRequest('POST', ['enabled' => false], uri: '/online-planners')) === ['enabled' => false]);
$last = $http->requests[array_key_last($http->requests)];
ensure($last->url === 'https://tram.example.test/admin/online-planners');
ensure($last->body === ['enabled' => false]);
$before = count($http->requests);
foreach ([['enabled' => 'false'], ['enabled' => 0], ['enabled' => false, 'url' => 'https://evil.test'], []] as $body) {
    rejected(fn() => $api->execute(plannerRequest('POST', $body, uri: '/online-planners')));
}
rejected(fn() => $api->execute(plannerRequest(uri: '/online-planners', internal: false)));
$authorized = false;
rejected(fn() => $api->execute(plannerRequest(uri: '/online-planners')));
ensure(count($http->requests) === $before);
$http->payload = ['enabled' => 'false'];
rejected(fn() => $pipeline->onlinePlanners());
echo "Online planner PHP control tests passed.\n";
