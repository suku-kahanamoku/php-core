<?php

declare(strict_types=1);
require dirname(__DIR__, 4).'/vendor/autoload.php';
require __DIR__.'/database.php';
use App\Modules\Etymolog\{EtymologRepository, EtymologSyncRepository, EtymologSyncService, ProviderRegistry, SyncException, EtymologException};
use App\Modules\Etymolog\Providers\WikidataProvider;
use App\Modules\Http\{HttpModule, HttpRequest, HttpResponse};
use App\Modules\Http\Contracts\HttpClient;

error_reporting(E_ALL);
set_error_handler(static function (int $n, string $s, string $f, int $l): bool {
    if (!(error_reporting() & $n)) { return false; }
    throw new ErrorException($s, 0, $n, $f, $l);
});
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL '.$label); }
    ++$checks; echo 'PASS '.$label.PHP_EOL;
}
$dsn = getenv('ETYMOLOG_TEST_DSN');
if (!$dsn || !preg_match('~^mysql:unix_socket=/tmp/etymolog-test\.[^/]+/mysql\.sock;dbname=etymolog_test;charset=utf8mb4$~D', $dsn)) {
    throw new RuntimeException('Disposable DB required');
}
$server = new PDO(explode(';dbname=', $dsn)[0], 'root', '');
$server->exec('CREATE DATABASE etymolog_test CHARACTER SET utf8mb4');
$db = testDatabase();
$root = dirname(__DIR__, 4);
$db->getPdo()->exec(file_get_contents($root.'/migrations/schema.sql'));
$migration = file_get_contents($root.'/migrations/etymolog_schema.sql');
$db->getPdo()->exec($migration);
$upgradeName = $db->insert('etymolog_name', ['franchise_code' => 'upgrade', 'name' => 'Before migration', 'kind' => 'given']);
$upgradeEntry = $db->insert('etymolog_entry', ['franchise_code' => 'upgrade', 'name_id' => $upgradeName, 'type' => 'legend', 'title' => 'Existing', 'body' => 'Preserve me']);
$db->getPdo()->exec($migration);
$preserved = $db->fetchOne('SELECT name_id,body FROM etymolog_entry WHERE id=?', [$upgradeEntry]);
check((int)$preserved['name_id'] === $upgradeName && $preserved['body'] === 'Preserve me', 'repeated consolidated schema preserves existing content and primary name FK');
foreach (['etymolog', 'other'] as $tenant) {
    foreach (['admin', 'user'] as $role) {
        $roleId = $db->insert('role', ['franchise_code' => $tenant, 'name' => $role, 'label' => $role]);
        $db->insert('user', ['franchise_code' => $tenant, 'role_id' => $roleId, 'first_name' => 'Test', 'last_name' => $role,
            'email' => $role.'@'.$tenant.'.test', 'password' => password_hash('Integration-only-123', PASSWORD_DEFAULT)]);
    }
}
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false); fclose($socket);
$directory = getenv('ETYMOLOG_TEST_DIR');
$process = proc_open([PHP_BINARY, '-S', $address, __DIR__.'/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/http.log', 'a'], 2 => ['file', $directory.'/http.log', 'a']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Cannot start HTTP test server'); }
$http = HttpModule::client();
$base = 'http://'.$address;
function api(string $method, string $path, ?array $body = null, ?string $token = null, string $host = 'ety.test', bool $internal = true): array {
    global $http, $base;
    $headers = ['Host' => $host, 'Accept' => 'application/json'];
    if ($internal) { $headers['X-Internal-Key'] = 'etymolog-integration-internal-key'; }
    if ($token !== null) { $headers['Authorization'] = 'Bearer '.$token; }
    $response = $http->send(new HttpRequest($base.'/api/'.$path, $method, $headers, $body, timeoutMs: 10000));
    return ['status' => $response->status, 'json' => json_decode($response->body, true), 'raw' => $response->body];
}
function status(array $response, int $expected, string $label): array {
    check($response['status'] === $expected, $label.' (HTTP '.$response['status'].')');
    return $response['json']['data'] ?? [];
}
try {
    for ($i = 0; $i < 100; ++$i) {
        if (api('GET', 'etymolog/names')['status'] === 401) { break; }
        usleep(20000);
    }
    status(api('GET', 'etymolog/names'), 401, 'anonymous reads denied');
    status(api('POST', 'etymolog/names', ['name' => 'No', 'kind' => 'surname']), 401, 'anonymous writes denied');
    status(api('GET', 'etymolog/names', host: 'unknown.test'), 403, 'unknown tenant denied');
    status(api('GET', 'etymolog/names', internal: false), 401, 'internal middleware preserved');
    $editor = status(api('POST', 'auth/login', ['email' => 'user@etymolog.test', 'password' => 'Integration-only-123']), 200, 'existing auth login editor')['token'];
    $admin = status(api('POST', 'auth/login', ['email' => 'admin@etymolog.test', 'password' => 'Integration-only-123']), 200, 'existing auth login admin')['token'];
    $other = status(api('POST', 'auth/login', ['email' => 'user@other.test', 'password' => 'Integration-only-123'], host: 'other.test'), 200, 'other tenant login')['token'];
    status(api('GET', 'etymolog/names', token: $editor, host: 'other.test'), 401, 'token cannot cross tenants');
    $name = status(api('POST', 'etymolog/names', ['name' => 'Novák', 'kind' => 'surname', 'language' => 'cs', 'summary' => 'Editorial'], $editor), 201, 'editor creates name');
    $id = (int)$name['id'];
    check($name['created_by'] !== null && $name['published'] === 0, 'audit actor and draft defaults');
    status(api('GET', 'etymolog/names/'.$id, token: $other, host: 'other.test'), 404, 'foreign detail hidden');
    status(api('PATCH', 'etymolog/names/'.$id, ['name' => 'Foreign'], $other, 'other.test'), 404, 'foreign update hidden');
    status(api('DELETE', 'etymolog/names/'.$id, token: $other, host: 'other.test'), 404, 'foreign delete hidden');
    status(api('PATCH', 'etymolog/names/'.$id, ['franchise_code' => 'other'], $editor), 422, 'tenant reassignment forbidden');
    status(api('POST', 'etymolog/names', ['name' => [], 'kind' => 'surname'], $editor), 422, 'invalid field type');
    status(api('POST', 'etymolog/variants', ['name_id' => $id, 'variant' => 'Foreign'], $other, 'other.test'), 422, 'cross tenant references rejected');
    $patched = status(api('PATCH', 'etymolog/names/'.$id, ['summary' => null], $editor), 200, 'PATCH explicit null');
    check($patched['name'] === 'Novák' && $patched['language'] === 'cs' && $patched['summary'] === null, 'PATCH omission preserves fields');
    $replaced = status(api('PUT', 'etymolog/names/'.$id, ['name' => 'Novák', 'kind' => 'surname'], $editor), 200, 'PUT replaces');
    check($replaced['language'] === null, 'PUT resets omitted optional fields');
    status(api('PUT', 'etymolog/names/'.$id, ['name' => 'Bad'], $editor), 422, 'PUT requires fields');
    $accentMatches = status(api('GET', 'etymolog/names?'.http_build_query(['q' => json_encode(['name' => 'novak'])]), token: $editor), 200, 'accent insensitive name search');
    check(count($accentMatches) === 1 && $accentMatches[0]['name'] === 'Novák', 'unaccented lowercase query finds original spelling');
    $q = http_build_query(['q' => json_encode(['name' => ['value' => 'Nov', 'operator' => 'start'], 'franchise_code' => 'other']), 'sort' => 'name DESC', 'projection' => 'name']);
    $list = status(api('GET', 'etymolog/names?'.$q, token: $editor), 200, 'search and projection with tenant filter attack');
    check(count($list) === 1 && $list[0]['name'] === 'Novák' && !isset($list[0]['kind']), 'projection and search results');
    status(api('GET', 'etymolog/names?q='.rawurlencode('{invalid'), token: $editor), 422, 'malformed q rejected');
    status(api('GET', 'etymolog/names?q='.rawurlencode('{"name":{"value":["x"]}}'), token: $editor), 422, 'nested scalar filter rejected');
    $source = status(api('POST', 'etymolog/sources', ['title' => 'Test source', 'url' => 'https://example.org'], $editor), 201, 'create source');
    $sourceId = (int)$source['id'];
    $entries = ['name_id' => $id, 'type' => 'etymology', 'title' => 'Výklad', 'body' => 'Testovací výklad'];
    $entry = status(api('POST', 'etymolog/entries', $entries, $editor), 201, 'create etymology');
    $entryId = (int)$entry['id'];
    status(api('PATCH', 'etymolog/entries/'.$entryId, ['published' => 1, 'certainty' => 'documented'], $editor), 422, 'published documented claim needs citation');
    status(api('POST', 'etymolog/entries', array_replace($entries, ['type' => 'fiction']), $editor), 422, 'fiction cannot masquerade as factual entry');
    $fixtureBodies = [
        'sources' => ['title' => 'Secondary'],
        'entries' => array_replace($entries, ['type' => 'fiction', 'certainty' => 'fiction', 'source_url' => 'https://example.org/fiction']),
        'entry-names' => ['entry_id' => $entryId, 'name_id' => $id],
        'variants' => ['name_id' => $id, 'variant' => 'Nowak', 'source_id' => $sourceId],
        'occurrences' => ['name_id' => $id, 'source_id' => $sourceId, 'country_code' => 'CZ', 'observed_year' => 1850, 'count' => 0],
        'citations' => ['entry_id' => $entryId, 'source_id' => $sourceId],
        'sync-jobs' => ['title' => 'Temporary job'],
    ];
    foreach ($fixtureBodies as $resource => $body) {
        $token = $resource === 'sync-jobs' ? $admin : $editor;
        $record = status(api('POST', 'etymolog/'.$resource, $body, $token), 201, $resource.' create');
        $recordId = (int)$record['id'];
        status(api('GET', 'etymolog/'.$resource.'/'.$recordId, token: $token), 200, $resource.' detail');
        status(api('GET', 'etymolog/'.$resource, token: $token), 200, $resource.' list');
        $field = array_key_exists('title', $body) ? 'title' : (array_key_exists('variant', $body) ? 'variant' : 'notes');
        status(api('PATCH', 'etymolog/'.$resource.'/'.$recordId, [$field => 'Updated'], $token), 200, $resource.' patch');
        status(api('PUT', 'etymolog/'.$resource.'/'.$recordId, $body, $token), 200, $resource.' put');
        status(api('DELETE', 'etymolog/'.$resource.'/'.$recordId, token: $token), 200, $resource.' soft delete');
        status(api('GET', 'etymolog/'.$resource.'/'.$recordId, token: $token), 404, $resource.' deleted hidden');
    }
    $citation = status(api('POST', 'etymolog/citations', ['entry_id' => $entryId, 'source_id' => $sourceId], $editor), 201, 'create citation');
    status(api('PATCH', 'etymolog/entries/'.$entryId, ['published' => 1, 'certainty' => 'documented'], $editor), 200, 'publish documented entry');
    status(api('DELETE', 'etymolog/citations/'.$citation['id'], token: $editor), 409, 'cannot remove published evidence');
    status(api('DELETE', 'etymolog/names/'.$id, token: $editor), 409, 'parent deletion blocked by children');
    status(api('DELETE', 'etymolog/sources/'.$sourceId, token: $editor), 409, 'source deletion blocked by evidence');
    status(api('POST', 'etymolog/sync-jobs', ['title' => 'No'], $editor), 403, 'sync config admin only');
    $disposable = status(api('POST', 'etymolog/names', ['name' => 'Disposable', 'kind' => 'given'], $editor), 201, 'create disposable');
    status(api('DELETE', 'etymolog/names/'.$disposable['id'].'?force=true', token: $editor), 403, 'hard delete requires admin');
    status(api('DELETE', 'etymolog/names/'.$disposable['id'].'?force=true', token: $admin), 200, 'admin hard delete');
    try {
        $db->insert('etymolog_variant', ['franchise_code' => 'other', 'name_id' => $id, 'variant' => 'No']);
        throw new LogicException('Cross tenant FK unexpectedly accepted');
    } catch (RuntimeException $e) {
        check($e->getPrevious() instanceof PDOException, 'composite FK independently enforces tenant');
    }

    $job = status(api('POST', 'etymolog/sync-jobs', ['title' => 'Wikidata', 'batch_size' => 1, 'interval_seconds' => 300], $admin), 201, 'create persistent sync job');
    $jobId = (int)$job['id'];
    $jobs = new EtymologRepository($db, 'etymolog', 'sync-jobs');
    $sync = new EtymologSyncRepository($db, 'etymolog');
    $fake = new class implements HttpClient {
        public array $responses = [];
        public array $requests = [];
        public function send(HttpRequest $request): HttpResponse {
            $this->requests[] = $request;
            return array_shift($this->responses) ?? throw new RuntimeException('Unexpected HTTP request');
        }
        public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array { throw new LogicException('Unused'); }
    };
    $provider = new WikidataProvider($fake);
    $service = new EtymologSyncService($jobs, $sync, new ProviderRegistry(['wikidata' => $provider]));
    $jsonResponse = fn (array $v) => new HttpResponse(200, json_encode($v, JSON_THROW_ON_ERROR));
    $entity = ['id' => 'Q123', 'lastrevid' => 1, 'labels' => ['cs' => ['language' => 'cs', 'value' => 'Novotný']], 'claims' => ['P138' => [['mainsnak' => ['datavalue' => ['value' => ['id' => 'Q1']]], 'references' => [['hash' => 'test']]]]]];
    $entity['claims']['P31'] = [['mainsnak' => ['snaktype' => 'value', 'datavalue' => ['value' => ['id' => 'Q101352']]]]];
    $entity['claims']['P407'] = [['mainsnak' => ['snaktype' => 'value', 'datavalue' => ['value' => ['id' => 'Q9056']]]]];
    $discovery = $jsonResponse(['continue' => ['sroffset' => 1], 'query' => ['search' => [['title' => 'Q123']]]]);
    $fake->responses = [$discovery, $jsonResponse(['entities' => ['Q123' => $entity]])];
    $result = $service->run($jobId);
    check($result['processed'] === 1 && $result['cursor'] === '1', 'import commits cursor and record');
    $imported = $db->fetchOne("SELECT * FROM etymolog_name WHERE franchise_code='etymolog' AND import_key='wikidata:surname:Q123'");
    check($imported['country_code'] === null && $imported['language'] === 'cs' && $imported['published'] === 0, 'import keeps stated language without inferring country or publishing');
    $snapshot = $sync->imports((int)$imported['id'])[0];
    check($snapshot['license'] === 'CC0-1.0' && isset($snapshot['payload']['claims']['P138'][0]['references']), 'license and statement references retained');
    check($service->run($jobId)['status'] === 'idle', 'cron respects due time');
    status(api('PATCH', 'etymolog/names/'.$imported['id'], ['name' => 'Edited', 'summary' => 'Manual'], $editor), 200, 'edit imported name');
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL,`cursor`=NULL WHERE id=?', [$jobId]);
    $entity['lastrevid'] = 2;
    $fake->responses = [$discovery, $jsonResponse(['entities' => ['Q123' => $entity]])];
    $service->run($jobId);
    $updated = $db->fetchOne('SELECT * FROM etymolog_name WHERE id=?', [$imported['id']]);
    check($updated['name'] === 'Edited' && $updated['summary'] === 'Manual' && count($sync->imports((int)$imported['id'])) === 1, 'repeat import idempotent and preserves editorial changes');
    check($sync->imports((int)$imported['id'])[0]['revision'] === '2', 'snapshot updates to newer revision');
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$jobId]);
    $fake->responses = [new HttpResponse(429, '{}', retryAfter: 7200)];
    try { $service->run($jobId); throw new LogicException('Expected rate limit'); }
    catch (SyncException $e) { check($e->reason === 'upstream_rate_limited', '429 recorded and propagated'); }
    $failed = $jobs->findById($jobId);
    check($failed['cursor'] === '1' && $failed['last_status'] === 'failed' && strtotime($failed['next_run_at'].' UTC') > time()+7000, 'failed batch preserves cursor and Retry-After');
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$jobId]);
    $fake->responses = [new HttpResponse(200, '{broken')];
    try { $service->run($jobId); throw new LogicException('Expected invalid response'); }
    catch (SyncException $e) { check($e->reason === 'invalid_upstream_json' && $jobs->findById($jobId)['cursor'] === '1', 'malformed upstream never advances cursor'); }
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$jobId]);
    $fake->responses = [$jsonResponse(['query' => ['search' => []]])];
    check($service->run($jobId)['status'] === 'complete' && $jobs->findById($jobId)['cursor'] === null, 'completed pass resets cursor for periodic refresh');
    status(api('DELETE', 'etymolog/names/'.$imported['id'], token: $editor), 200, 'soft delete imported name');
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$jobId]);
    $fake->responses = [$discovery, $jsonResponse(['entities' => ['Q123' => $entity]])];
    $service->run($jobId);
    check((int)$db->fetchOne('SELECT deleted FROM etymolog_name WHERE id=?', [$imported['id']])['deleted'] === 1, 'sync cannot resurrect deleted name');
    status(api('GET', 'etymolog/names/'.$imported['id'].'/imports', token: $editor), 404, 'deleted provenance hidden');
    status(api('GET', 'etymolog/sync-jobs/'.$jobId.'/runs', token: $admin), 200, 'admin can inspect runs');
    status(api('GET', 'etymolog/sync-jobs/'.$jobId.'/runs', token: $editor), 403, 'run history admin only');
    check((new EtymologSyncRepository($db, 'other'))->imports((int)$imported['id']) === [], 'snapshot repository tenant isolated');
    $soft = status(api('POST', 'etymolog/names', ['name' => 'Archived', 'kind' => 'given'], $editor), 201, 'create archive fixture');
    status(api('DELETE', 'etymolog/names/'.$soft['id'], token: $editor), 200, 'archive fixture');
    status(api('DELETE', 'etymolog/names/'.$soft['id'].'?force=true', token: $admin), 200, 'admin can purge an archived record');
    // An actual SQL failure after the first item must roll back both data and progress.
    $db->query('UPDATE etymolog_sync_job SET next_run_at=NULL WHERE id=?', [$jobId]);
    $brokenProvider = new class implements App\Modules\Etymolog\Contracts\NameProvider {
        public function batch(string $language, string $kind, ?string $cursor, int $limit): array {
            $item = ['external_id' => 'Q777', 'name' => 'Rollback', 'revision' => '1', 'source_url' => 'https://www.wikidata.org/wiki/Q777', 'payload' => []];
            return ['items' => [$item, array_replace($item, ['external_id' => 'Q778', 'name' => str_repeat('a', 256)])], 'cursor' => '3', 'complete' => false];
        }
    };
    $atomicService = new EtymologSyncService($jobs, $sync, new ProviderRegistry(['wikidata' => $brokenProvider]));
    try { $atomicService->run($jobId); throw new LogicException('Expected SQL failure'); }
    catch (SyncException $e) {
        check($e->reason === 'sync_failed' && $jobs->findById($jobId)['cursor'] === '1', 'SQL failure preserves cursor');
    }
    check(!$db->fetchOne("SELECT id FROM etymolog_name WHERE import_key='wikidata:surname:Q777'"), 'failed batch rolls back earlier imported items');
    status(api('GET', 'etymolog/names?projection=%5Bbroken', token: $editor), 422, 'malformed projection rejected');
    $secondEntry = status(api('POST', 'etymolog/entries', $entries, $editor), 201, 'create second entry');
    status(api('PATCH', 'etymolog/citations/'.$citation['id'], ['entry_id' => (int)$secondEntry['id']], $editor), 409, 'cannot move published evidence');
    $fake->responses = [new HttpResponse(200, '{"error":{"code":"maxlag"}}')];
    try { $provider->batch('cs', 'surname', null, 1); throw new LogicException('Expected maxlag'); }
    catch (SyncException $e) { check($e->reason === 'upstream_rate_limited', 'HTTP 200 API error is not empty success'); }
    $fake->responses = [$discovery, $jsonResponse(['entities' => ['Q123' => array_replace($entity, ['claims' => []])]])];
    $stale = $provider->batch('cs', 'surname', null, 1);
    check($stale['items'] === [] && $stale['cursor'] === '1', 'stale search result is not imported as a name');
    try { $provider->batch('cs', 'surname', '10000', 1); throw new LogicException('Expected window limit'); }
    catch (SyncException $e) { check($e->reason === 'search_window_exceeded', 'search window is never silently truncated'); }
    // Independently held lock simulates overlapping cron/API mutations.
    $second = new PDO($dsn, 'root', '');
    $lock = 'etymolog:'.substr(hash('sha256', 'etymolog'), 0, 48);
    $stmt = $second->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    try { $service->run($jobId); throw new LogicException('Expected busy'); }
    catch (EtymologException $e) { check($e->status === 409, 'overlapping sync prevented by tenant lock'); }
    finally { $stmt=$second->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lock]); }
    check(count($fake->requests) > 0 && str_starts_with($fake->requests[0]->url, 'https://www.wikidata.org/w/api.php?') && $fake->requests[0]->redirectHosts === [], 'provider fixed endpoint and no redirect');
    // The consolidated seed loads every provider up front; later explicit fixture
    // jobs coexist with these seeded jobs (the API permits separate jobs per rule).
    $seed = file_get_contents($root.'/migrations/etymolog_seed.sql');
    $db->getPdo()->exec($seed);
    $db->getPdo()->exec($seed);
    check((int)$db->fetchOne("SELECT COUNT(*) n FROM role WHERE franchise_code='etymolog'")['n'] === 2, 'tenant auth seed idempotent');
    check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_sync_job WHERE franchise_code='etymolog' AND kind='given' AND provider='wikidata'")['n'] === 1, 'tenant sync seed idempotent');
    require __DIR__.'/stories.php';
    require __DIR__.'/sources.php';
    require __DIR__.'/culture.php';
    require __DIR__.'/public.php';
    require __DIR__.'/background.php';
    require __DIR__.'/wikipedia.php';
    require __DIR__.'/http-worker.php';
    require __DIR__.'/publication.php';
    require __DIR__.'/name-identity.php';
    echo "Checks: $checks passed\n";
} finally {
    proc_terminate($process); fclose($pipes[0]); proc_close($process);
}
