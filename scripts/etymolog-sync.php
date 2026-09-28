<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';

use App\Modules\Database\Database;
use App\Modules\Etymolog\{EtymologRepository, EtymologSyncRepository, EtymologSyncService, ProviderRegistry, EtymologStoryRepository, EtymologExternalRepository, EtymologCalendarRepository, SyncException, EtymologException};
use App\Modules\Etymolog\Providers\{WikidataProvider, WikisourceProvider, WiktionaryProvider, PolandPeselProvider, CsuBabyNamesProvider, ErbenFolkloreProvider, CzechNamedaysProvider};
use App\Modules\Http\HttpModule;

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
set_exception_handler(static function (Throwable $e): void {
    $reason = $e instanceof SyncException ? $e->reason : ($e instanceof EtymologException ? $e->getMessage() : 'etymolog_command_failed');
    fwrite(STDERR, $reason.PHP_EOL);
    exit(1);
});
$options = getopt('', ['tenant:', 'job:']);
$tenant = $options['tenant'] ?? '';
$allowed = [];
foreach (explode(',', $_ENV['FRANCHISE_CODES'] ?? '') as $entry) {
    $parts = explode(':', trim($entry));
    $allowed[] = trim((string)end($parts));
}
if (!is_string($tenant) || $tenant === '' || strlen($tenant) > 64 || !in_array($tenant, $allowed, true)) {
    fwrite(STDERR, "Supply --tenant=<existing FRANCHISE_CODES alias>.\n");
    exit(2);
}
$job = $options['job'] ?? null;
if ($job !== null && (filter_var($job, FILTER_VALIDATE_INT) === false || (int)$job < 1 || (int)$job > 2147483647)) {
    fwrite(STDERR, "Invalid --job.\n");
    exit(2);
}
$db = Database::getInstance();
$http = HttpModule::client();
$registry = new ProviderRegistry([
    'erben-folklore' => new ErbenFolkloreProvider($http),
    'czech-namedays' => new CzechNamedaysProvider($http),
    'wikidata' => new WikidataProvider($http, $_ENV['ETYMOLOG_WIKIDATA_USER_AGENT'] ?? 'Etymolog/1.0 (php-core; Wikidata name catalog)'),
    'wikisource' => new WikisourceProvider($http),
    'wiktionary' => new WiktionaryProvider($http),
    'csu-baby-names' => new CsuBabyNamesProvider($http),
    'poland-pesel' => new PolandPeselProvider($http),
]);
$service = new EtymologSyncService(new EtymologRepository($db, $tenant, 'sync-jobs'), new EtymologSyncRepository($db, $tenant), $registry, new EtymologStoryRepository($db, $tenant), new EtymologExternalRepository($db, $tenant), new EtymologCalendarRepository($db, $tenant));
echo json_encode($service->run($job === null ? null : (int)$job), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
