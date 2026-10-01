<?php

declare(strict_types=1);

use App\Modules\Transport\TransportModule;
use App\Modules\Transport\Contracts\{IntegrationModule, Provider};
use App\Modules\Transport\Core\{ConfigurationService, ProviderRegistry, ProviderSelectionService, ResourceService, JourneyService, CountryConfigurationService, IntegrationRegistry, ProviderExecutionService};
use App\Modules\Transport\Model\{RequestBudget, ProviderDefinition, JourneyQuery};
use App\Modules\Transport\Persistence\{TransportRepository, ProviderQuotaRepository};
use App\Modules\Transport\Protocols\Transmodel\TransmodelProvider;
use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;

$modular = new TransportRepository($db, 'modular');
$countryConfig = ['countries' => ['CZ', 'NO'], 'providers' => [['code' => 'pid', 'published' => false]]];
ConfigurationService::apply($modular, $countryConfig, TransportModule::integrations(), TransportModule::importers($modular));
check(count($modular->providers()) === 2 && count($r->providers()) === 3, 'country composition and tenant override preserve other tenants');
$composed = CountryConfigurationService::compose(['countries' => ['NO'], 'providers' => [['code' => 'entur', 'config' => ['client_name' => 'test-client']]]]);
check($composed['providers'][0]['config']['client_name'] === 'test-client' && isset($composed['providers'][0]['config']['geocoder_url']), 'country override preserves unmodified provider configuration');
fails(fn() => CountryConfigurationService::compose(['countries' => ['../NO']]), 'invalid_configuration');
$slovakConfig = CountryConfigurationService::compose(['countries' => ['SK']]);
check(
    $slovakConfig['providers'][0]['code'] === 'dpb-otp' && $slovakConfig['providers'][0]['published'] === false
        && $slovakConfig['feeds'][0]['code'] === 'dpb' && $slovakConfig['feeds'][0]['config']['storage_allowed'] === true,
    'Slovak preset configures the commercial DPB GTFS feed without publishing an unready planner'
);
fails(fn() => CountryConfigurationService::compose(['countries' => ['NO', 'NO']]), 'invalid_configuration');
fails(fn() => CountryConfigurationService::compose(['providers' => [['code' => 'x'], ['code' => 'x']]]), 'invalid_configuration');

// An independent integration is installed through the contract, with no Core branches.
$fixtureModule = new class implements IntegrationModule {
    public function adapter(): string
    {
        return 'fixture_transmodel';
    }
    public function validate(ProviderDefinition $definition): void
    {
        ConfigurationService::url($definition->config['url'] ?? '');
    }
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        return new TransmodelProvider($definition);
    }
};
$fixtureModules = new IntegrationRegistry([$fixtureModule]);
$fixtureConfig = ['providers' => [['code' => 'fixture', 'adapter' => 'fixture_transmodel', 'published' => true, 'coverage' => $coverage, 'config' => ['url' => 'https://fixture.invalid']]]];
ConfigurationService::apply($modular, $fixtureConfig, $fixtureModules, TransportModule::importers($modular));
$fixtureRows = array_values(array_filter($modular->providers(), fn($p) => $p['code'] === 'fixture'));
$fixtureRegistry = ProviderRegistry::build($fixtureRows, 'modular', [], $fixtureModules, $modular);
check($fixtureRegistry->get('fixture') instanceof TransmodelProvider, 'external integration validates and constructs through one registry');
$badPolicy = $fixtureConfig;
$badPolicy['providers'][0]['config']['operations'] = ['realtime' => ['enabled' => true]];
fails(fn() => ConfigurationService::apply($modular, $badPolicy, $fixtureModules, TransportModule::importers($modular)), 'invalid_configuration');
$badPolicy['providers'][0]['config']['operations'] = ['journeys' => ['role' => 'fallback', 'fallback_for' => ['missing']]];
fails(fn() => ConfigurationService::apply($modular, $badPolicy, $fixtureModules, TransportModule::importers($modular)), 'invalid_configuration');
$badFeed = $fixtureConfig + ['feeds' => [['code' => 'f', 'provider' => 'fixture', 'url' => 'https://fixture.invalid/feed', 'timezone' => 'UTC', 'config' => ['storage_allowed' => true, 'format' => 'netex']]]];
fails(fn() => ConfigurationService::apply($modular, $badFeed, $fixtureModules, TransportModule::importers($modular)), 'unsupported_feed_format');
$freshImporters = TransportModule::importers($modular);
check($freshImporters->get('gtfs') !== $freshImporters->get('gtfs'), 'each import gets fresh transaction-local buffers');

$operationProvider = new TransmodelProvider(new ProviderDefinition('modular', 'policy', 'fixture_transmodel', [
    'url' => 'https://fixture.invalid',
    'operations' => ['journeys' => ['enabled' => false], 'departures' => ['role' => 'fallback', 'fallback_for' => ['fixture'], 'priority' => 7]],
], $coverage));
$opSelector = new ProviderSelectionService(new ProviderRegistry([$operationProvider]));
check($opSelector->select('journeys') === [] && count($opSelector->select('departures')) === 1
    && $operationProvider->definition()->roleFor('departures') === 'fallback' && $operationProvider->definition()->priority('departures') === 7, 'operation policies restrict capabilities independently');
$borderQuery = JourneyQuery::fromArray(array_replace($input, ['state' => 'DE', 'city' => 'Berlin']));
check(count((new ProviderSelectionService(new ProviderRegistry([$fixtureRegistry->get('fixture')])))->journeys($borderQuery)) === 1, 'route endpoints determine coverage independently of country and city UI hints');

require dirname(__DIR__) . '/Integrations/Entur/tests/contract.php';
require dirname(__DIR__) . '/Integrations/WienerLinien/tests/contract.php';
require dirname(__DIR__) . '/Integrations/OpenTripPlanner/tests/contract.php';

$modular->execute('INSERT INTO transport_provider(franchise_code,code,adapter,config,coverage,fallback_for,published) VALUES (?,?,?,?,?,?,1)', ['modular', 'enriching', 'fixture_transmodel', '{}', json_encode($coverage), '[]']);
$enrichingProvider = new class(new ProviderDefinition('modular', 'enriching', 'fixture_transmodel', ['url' => 'https://fixture.invalid'], $coverage)) implements \App\Modules\Transport\Contracts\ResourceEnrichmentProvider {
    public function __construct(private readonly ProviderDefinition $def) {}
    public function definition(): ProviderDefinition
    {
        return $this->def;
    }
    public function capabilities(): array
    {
        return ['places'];
    }
    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        return new HttpRequest('https://fixture.invalid/places');
    }
    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        return [['id' => \App\Modules\Transport\Model\ResourceIdCodec::encode('modular', 'enriching', 'stop', 'raw-1'), 'name' => null, 'lat' => 1.0, 'lon' => 2.0]];
    }
    public function enrichResource(string $operation, array $result, array $input, HttpClient $http): array
    {
        foreach ($result as &$row) {
            $row['name'] = 'Enriched Stop';
        }
        unset($row);
        return $result;
    }
};
$enrichingHttp = new class implements HttpClient {
    public function send(HttpRequest $request): HttpResponse
    {
        return new HttpResponse(200, '{}');
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        return array_map(static fn() => new HttpResponse(200, '{}'), $requests);
    }
};
$enrichedSearch = (new \App\Modules\Transport\Core\ResourceSearchService(new ProviderRegistry([$enrichingProvider]), $enrichingHttp, $modular))
    ->search('places', ['query' => 'x'], null, null, null, new RequestBudget());
check($enrichedSearch['rows'][0]['name'] === 'Enriched Stop', 'ResourceSearchService applies provider enrichment to place search results');

$envRegistry = TransportModule::registry($r, ['TRANSPORT_PID_TOKEN' => 'modularity-fixture-credential']);
$golemioDefinition = $envRegistry->get('pid')->definition();
check($golemioDefinition->config['quota']['limit'] === 20 && !str_contains(json_encode($golemioDefinition->publicData([])), 'modularity-fixture-credential'), 'Golemio quota is credential-scoped and credentials never enter public metadata');

// Two connections and two tenants consume the SAME rolling quota.
$quota = new ProviderQuotaRepository($db);
$quotaSecond = new ProviderQuotaRepository($second);
$qConfig = ['quota' => ['scope' => 'fixture-shared', 'limit' => 2, 'window_ms' => 1000]];
$qa = new ProviderDefinition('modular', 'a', 'fixture', $qConfig, $coverage);
$qb = new ProviderDefinition('other', 'b', 'fixture', $qConfig, $coverage);
check($quota->reserve($qa) === 0 && $quotaSecond->reserve($qb) === 0 && $quota->reserve($qa) > 0, 'rolling quota shared across tenant and connection boundaries');
$conflictingQuota = $qb->withConfig(['quota' => ['scope' => 'fixture-shared', 'limit' => 3, 'window_ms' => 1000]]);
fails(fn() => $quotaSecond->reserve($conflictingQuota), 'invalid_quota_configuration');
$quota->block($qa, 2);
check($quotaSecond->reserve($qb) > 1000, 'Retry-After cooldown is shared by the credential scope');

// The executor schedules independent multi-stage jobs into the same HTTP rounds.
$runnerConfig = ['providers' => []];
foreach (['stage-a', 'stage-b', 'stage-c', 'stage-d', 'stage-e', 'throttled', 'timed', 'timed-backup', 'batch-quota'] as $code) {
    $runnerConfig['providers'][] = ['code' => $code, 'adapter' => 'fixture_transmodel', 'published' => true, 'coverage' => $coverage, 'config' => ['url' => 'https://fixture.invalid']];
}
ConfigurationService::apply($modular, $runnerConfig, $fixtureModules, TransportModule::importers($modular));
$runnerHttp = new class implements HttpClient {
    public array $batches = [];
    public int $delayUs = 0;
    public function send(HttpRequest $request): HttpResponse
    {
        return $this->sendAll([$request])[0];
    }
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array
    {
        $this->batches[] = ['urls' => array_map(fn($r) => $r->url, $requests), 'timeouts' => array_map(fn($r) => $r->timeoutMs, $requests), 'budget' => $budgetMs, 'concurrency' => $concurrency];
        if ($this->delayUs) {
            usleep(min($this->delayUs, $budgetMs * 1000));
        }
        return array_map(fn($r) => new HttpResponse(200, json_encode(['url' => $r->url])), $requests);
    }
};
$stages = [];
foreach (['stage-a', 'stage-b'] as $code) {
    $stages[$code] = new TransmodelProvider(new ProviderDefinition('modular', $code, 'fixture_transmodel', [], $coverage));
}
$executor = new ProviderExecutionService($runnerHttp, $modular);
$stageResults = $executor->run($stages, static function ($provider, HttpClient $http): array {
    $code = $provider->definition()->code;
    $first = $http->sendAll(['same-key' => new HttpRequest('https://fixture.invalid/' . $code . '/1')])['same-key']->json();
    $second = $http->sendAll(['same-key' => new HttpRequest($first['url'] . '/2')])['same-key']->json();
    return $second;
}, new RequestBudget(1000));
check(count($runnerHttp->batches) === 2 && count($runnerHttp->batches[0]['urls']) === 2 && count($runnerHttp->batches[1]['urls']) === 2, 'multi-stage providers are batched together instead of executed serially');
check($stageResults['stage-a']->data['url'] === 'https://fixture.invalid/stage-a/1/2' && $stageResults['stage-b']->data['url'] === 'https://fixture.invalid/stage-b/1/2', 'colliding provider-local request keys preserve response ownership');
$before = count($runnerHttp->batches);
$executor->run($stages, fn($p, $http) => $http->send(new HttpRequest('https://fixture.invalid')), new RequestBudget(0));
check(count($runnerHttp->batches) === $before, 'expired request budget dispatches no HTTP calls');

$throttleDef = new ProviderDefinition('modular', 'throttled', 'fixture_transmodel', ['quota' => ['scope' => 'fixture-throttle', 'limit' => 1, 'window_ms' => 1000]], $coverage);
$quota->reserve($throttleDef);
$throttled = $executor->run(['throttled' => new TransmodelProvider($throttleDef)], fn($p, $http) => $http->send(new HttpRequest('https://fixture.invalid')), new RequestBudget(50))['throttled'];
check($throttled->status === 'throttled' && !$throttled->allowsFallback() && count($runnerHttp->batches) === $before, 'quota exhaustion is not a source outage and makes no network request');
check((int)$modular->rows('SELECT failure_count FROM transport_provider WHERE franchise_code=? AND code=?', ['modular', 'throttled'])[0]['failure_count'] === 0, 'quota exhaustion does not poison the source circuit');

$batchDefinition = new ProviderDefinition('modular', 'batch-quota', 'fixture_transmodel', ['quota' => ['scope' => 'fixture-batch-quota', 'limit' => 1, 'window_ms' => 1000]], $coverage);
$batchResult = $executor->run(['batch-quota' => new TransmodelProvider($batchDefinition)], fn($p, $http) => $http->sendAll([
    'one' => new HttpRequest('https://fixture.invalid/one'),
    'two' => new HttpRequest('https://fixture.invalid/two'),
]), new RequestBudget(50))['batch-quota'];
check($batchResult->status === 'throttled' && count(end($runnerHttp->batches)['urls']) === 1, 'quota is counted for every request inside a provider batch');

$runnerHttp->delayUs = 15000;
$stagedDeadline = $executor->run(['stage-a' => $stages['stage-a']], static function ($p, HttpClient $http): array {
    $http->sendAll(['first' => new HttpRequest('https://fixture.invalid/first')], 1000);
    return $http->sendAll(['second' => new HttpRequest('https://fixture.invalid/second')], 1000);
}, new RequestBudget(25))['stage-a'];
check(end($runnerHttp->batches)['budget'] < 20, 'later stages receive the remaining common deadline');
$runnerHttp->delayUs = 0;

// Independent batch deadlines and concurrency stay independent in the shared network round.
$runnerHttp->batches = [];
$bounded = $executor->run($stages, static function ($provider, HttpClient $http): array {
    $code = $provider->definition()->code;
    $requests = [];
    for ($i = 0; $i < 3; ++$i) {
        $requests[$code . '-' . $i] = new HttpRequest('https://fixture.invalid/' . $code . '/' . $i);
    }
    return $http->sendAll($requests, $code === 'stage-a' ? 100 : 1000, $code === 'stage-a' ? 1 : 2);
}, new RequestBudget(2000));
$firstBatch = $runnerHttp->batches[0];
check(
    count($firstBatch['urls']) === 3 && $firstBatch['timeouts']['stage-a-0'] <= 100
        && $firstBatch['timeouts']['stage-b-0'] > 100 && $firstBatch['budget'] > 1000
        && $bounded['stage-a']->succeeded() && $bounded['stage-b']->succeeded(),
    'each provider retains its own batch deadline and concurrency within a shared round'
);
$runnerHttp->batches = [];
$many = $stages;
foreach (['stage-c', 'stage-d', 'stage-e'] as $code) {
    $many[$code] = new TransmodelProvider(new ProviderDefinition('modular', $code, 'fixture_transmodel', [], $coverage));
}
$executor->run($many, static function ($provider, HttpClient $http): array {
    $code = $provider->definition()->code;
    return $http->sendAll([$code . '-1' => new HttpRequest('https://fixture.invalid/' . $code . '/1'), $code . '-2' => new HttpRequest('https://fixture.invalid/' . $code . '/2')]);
}, new RequestBudget(2000));
check(
    count($runnerHttp->batches[0]['urls']) === 4 && isset($runnerHttp->batches[1]['urls']['stage-e-1'])
        && max(array_map(fn($b) => count($b['urls']), $runnerHttp->batches)) <= 4,
    'large batches rotate fairly when more providers are waiting than parallel slots'
);

// Exact source mappings preserve renamed provider codes and the trip service day.
$mappedLive = new \App\Modules\Transport\Integrations\Golemio\PidProvider(new ProviderDefinition('modular', 'live-cz', 'pid', [
    'url' => 'https://api.golemio.cz',
    'schedule_provider' => 'schedule-cz',
    'otp_feed_id' => 'cz-feed',
], $coverage), 'fixture');
$mappedSchedule = new \App\Modules\Transport\Integrations\OpenTripPlanner\OtpProvider(new ProviderDefinition('modular', 'schedule-cz', 'otp_transmodel', [
    'url' => 'http://localhost/otp',
    'graph_ready' => true,
    'source_provider' => 'live-cz',
    'otp_feed_id' => 'cz-feed',
], $coverage, 'fallback', ['live-cz']));
// Circuit rows are deliberately separate from the production-like tram fixture.
foreach (['live-cz' => 'pid', 'schedule-cz' => 'otp_transmodel'] as $code => $adapter) {
    $modular->execute("INSERT INTO transport_provider(franchise_code,code,adapter,config,coverage,fallback_for,published) VALUES (?,?,?,?,?,?,1)", ['modular', $code, $adapter, '{}', json_encode($coverage), '[]']);
}
$mappedRegistry = new ProviderRegistry([$mappedLive, $mappedSchedule]);
$tripReference = ['provider' => 'schedule-cz', 'external' => 'cz-feed:T1', 'date' => '2026-10-06'];
$canonical = $mappedRegistry->canonicalReference('trip', $tripReference);
check($canonical === ['provider' => 'live-cz', 'external' => 'T1', 'date' => '2026-10-06'], 'explicit identity mapping works with renamed providers and preserves service day');
$disabledSchedule = new \App\Modules\Transport\Integrations\OpenTripPlanner\OtpProvider($mappedSchedule->definition()->withConfig(
    array_replace($mappedSchedule->definition()->config, ['operations' => ['trip' => ['enabled' => false]]])
));
fails(fn() => (new ProviderRegistry([$mappedLive, $disabledSchedule]))->canonicalReference('trip', $tripReference), 'unsupported_capability');
$departuresHttp = new FakeHttp([]);
$departuresHttp->urlResponses['http://localhost/otp'] = new HttpResponse(200, '{"data":{"stopPlace":{"estimatedCalls":[]}}}');
$mappedResources = new ResourceService($mappedRegistry, $departuresHttp, $modular);
$scheduledStop = \App\Modules\Transport\Model\ResourceIdCodec::encode('modular', 'schedule-cz', 'stop', 'cz-feed:S1');
$departures = $mappedResources->resource('departures', $scheduledStop, ['at' => '2026-10-06T10:00:00+02:00', 'limit' => 10]);
check(
    $departures['result'] === [] && $departures['partial'] && $departures['source']['provider'] === 'schedule-cz'
        && $departures['source']['mode'] === 'fallback' && count($departuresHttp->requests) === 2
        && $departuresHttp->payloads[1]->body['variables']['id'] === 'cz-feed:S1',
    'Golemio outage uses exact OTP departures backup without redirecting back to live source'
);

// Optional enrichment without matching IDs performs no network and cannot heal a failing source.
$modular->providerFailure('live-cz');
$beforeNoop = count($departuresHttp->requests);
$noop = (new ProviderExecutionService($departuresHttp, $modular))->run(
    ['live-cz' => $mappedLive],
    fn($provider, $http) => $provider->enrichJourneys([['legs' => [['trip_id' => null, 'from' => ['id' => null]]]]], $mappedRegistry, $http),
    new RequestBudget(1000)
);
check(
    $noop['live-cz']->succeeded() && count($departuresHttp->requests) === $beforeNoop
        && (int)$modular->rows('SELECT failure_count FROM transport_provider WHERE franchise_code=? AND code=?', ['modular', 'live-cz'])[0]['failure_count'] === 2,
    'enrichment without a network request does not reset source failures'
);

// An actual upstream deadline is an outage eligible for fallback, unlike local pre-dispatch exhaustion.
$timeoutHttp = new FakeHttp(['timed' => new HttpResponse(0, '', 'deadline_exceeded'), 'timed-backup' => new HttpResponse(200, '{"data":{"trip":{"tripPatterns":[]}}}')]);
$timed = new TransmodelProvider(new ProviderDefinition('modular', 'timed', 'fixture_transmodel', ['url' => 'https://fixture.invalid'], $coverage));
$timedBackup = new TransmodelProvider(new ProviderDefinition('modular', 'timed-backup', 'fixture_transmodel', ['url' => 'https://fixture.invalid'], $coverage, 'fallback', ['timed']));
$timedRegistry = new ProviderRegistry([$timed, $timedBackup]);
$timedResult = (new JourneyService($timedRegistry, $timeoutHttp, $modular, new ResourceService($timedRegistry, $timeoutHttp, $modular)))->search($q);
check($timedResult['partial'] && in_array('timed-backup', $timeoutHttp->requests, true), 'upstream timeout retains configured fallback');

// Prevent adapter-specific classes from creeping back into shared orchestration.
foreach (['Core', 'Model', 'Persistence', 'Protocols'] as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $folder));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        check(!str_contains(file_get_contents($file->getPathname()), 'App\\Modules\\Transport\\Integrations\\'), 'dependency boundary ' . $folder . '/' . $file->getFilename());
    }
}
