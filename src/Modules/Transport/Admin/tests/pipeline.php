<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/Modules/Http/Contracts/HttpClient.php';
require $root . '/Modules/Http/HttpRequest.php';
require $root . '/Modules/Http/HttpResponse.php';
require $root . '/Modules/Transport/Gateway/JavaTransportException.php';
require $root . '/Modules/Transport/Admin/LocalPipelineService.php';
require $root . '/Modules/Transport/TransportModule.php';

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\TransportModule;
use App\Modules\Transport\Gateway\JavaTransportException;

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
echo "Local pipeline PHP control tests passed.\n";
