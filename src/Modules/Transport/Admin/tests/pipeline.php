<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/Modules/Http/Contracts/HttpClient.php';
require $root . '/Modules/Http/HttpRequest.php';
require $root . '/Modules/Http/HttpResponse.php';
require $root . '/Modules/Transport/Gateway/JavaTransportException.php';
require $root . '/Modules/Transport/Admin/LocalPipelineService.php';
require $root . '/Modules/Transport/TransportModule.php';
require $root . '/Modules/Router/Request.php';
require $root . '/Modules/Transport/Admin/LocalPipelineApi.php';

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\TransportModule;
use App\Modules\Transport\Gateway\JavaTransportException;
use App\Modules\Router\Request;
use App\Modules\Transport\Admin\LocalPipelineApi;

function ensure(bool $ok): void { if (!$ok) { throw new RuntimeException('Pipeline admin assertion failed.'); } }
function rejected(callable $call): void {
    try { $call(); } catch (JavaTransportException) { return; }
    throw new RuntimeException('Expected rejected pipeline operation.');
}
$http = new class implements HttpClient {
    public array $requests = [];
    public array $payload = ['id' => '00000000-0000-0000-0000-000000000001', 'action' => 'sync_build', 'status' => 'queued'];
    public function send(HttpRequest $request): HttpResponse {
        $this->requests[] = $request;
        return new HttpResponse(202, json_encode($this->payload, JSON_THROW_ON_ERROR));
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array { throw new RuntimeException('Batch not expected.'); }
};
$env = ['TRANSPORT_LOCAL_PIPELINE_ENABLED' => '1', 'TRANSPORT_JAVA_TENANT' => 'tram',
    'TRANSPORT_JAVA_URL' => 'https://tram.example.test', 'TRANSPORT_LOCAL_PIPELINE_TOKEN' => 'synthetic-admin-token-not-a-secret'];
rejected(fn() => TransportModule::localPipeline('another-tenant', $env, $http));
rejected(fn() => TransportModule::localPipeline('tram', array_replace($env, ['TRANSPORT_LOCAL_PIPELINE_ENABLED' => '0']), $http));
$splitEnv = array_replace($env, ['TRANSPORT_JAVA_URL' => 'http://127.0.0.1:18095', 'TRANSPORT_LOCAL_PIPELINE_URL' => 'https://tram.example.test']);
ensure(TransportModule::localPipeline('tram', $splitEnv, $http)->status()['status'] === 'queued');
$http->requests = [];
$pipeline = TransportModule::localPipeline('tram', $env, $http);
rejected(fn() => $pipeline->submit('rm -rf /'));
ensure(count($http->requests) === 0);
ensure($pipeline->submit('sync_build')['status'] === 'queued');
ensure($http->requests[0]->url === 'https://tram.example.test/admin/local/jobs');
ensure($http->requests[0]->headers['Authorization'] === 'Bearer ' . $env['TRANSPORT_LOCAL_PIPELINE_TOKEN']);
$http->payload['action'] = 'deploy';
ensure($pipeline->submit('deploy')['action'] === 'deploy');
$pipeline->status();
ensure($http->requests[2]->method === 'GET');
$http->payload['lease'] = 'must-not-reach-admin-ui';
rejected(fn() => $pipeline->status());
$http->payload = ['status' => 'idle', 'runner' => ['online' => true]];
$authorized = false;
$api = new LocalPipelineApi(static function () use (&$authorized): void {
    if (!$authorized) throw new JavaTransportException('forbidden', 'Admin required.', 403);
}, static fn(string $tenant) => TransportModule::localPipeline($tenant, $env, $http));
function pipelineRequest(string $method = 'GET', array $body = [], array $query = [], bool $internal = true, string $tenant = 'tram'): Request {
    $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
    foreach (['method' => $method, 'uri' => '/local-pipeline', 'body' => $body,
        'query' => $query, 'franchiseCode' => $tenant] as $property => $value) {
        (new ReflectionProperty(Request::class, $property))->setValue($request, $value);
    }
    $request->internalAuthenticated = $internal;
    return $request;
}
$before = count($http->requests);
rejected(fn() => $api->execute(pipelineRequest(internal: false)));
rejected(fn() => $api->execute(pipelineRequest()));
$authorized = true;
rejected(fn() => $api->execute(pipelineRequest(tenant: 'other')));
rejected(fn() => $api->execute(pipelineRequest('DELETE')));
rejected(fn() => $api->execute(pipelineRequest(query: ['url' => 'https://evil.test'])));
rejected(fn() => $api->execute(pipelineRequest('POST', ['action' => 'deploy', 'command' => 'x'])));
rejected(fn() => $api->execute(pipelineRequest('POST', ['action' => 'rm -rf /'])));
ensure(count($http->requests) === $before);
ensure($api->execute(pipelineRequest())['status'] === 'idle');
$http->payload = ['id' => '00000000-0000-0000-0000-000000000001', 'action' => 'deploy', 'status' => 'queued'];
ensure($api->execute(pipelineRequest('POST', ['action' => 'deploy']))['action'] === 'deploy');
echo "Local pipeline PHP control tests passed.\n";
