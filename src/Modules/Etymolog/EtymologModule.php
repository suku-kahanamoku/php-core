<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Database\Database;
use App\Modules\Http\HttpModule;
use App\Modules\Etymolog\Providers\{WikidataProvider, WikisourceProvider, WikipediaNamesProvider, WiktionaryProvider, PolandPeselProvider, CsuBabyNamesProvider, ErbenFolkloreProvider, CzechNamedaysProvider};

final class EtymologModule
{
    public static function sync(Database $db, string $tenant, bool $httpStep = false): EtymologSyncService
    {
        $http = HttpModule::client();
        if ($httpStep) { $http = new Providers\SyncBudgetProvider($http); }
        $registry = new ProviderRegistry([
            'wikipedia-names' => new WikipediaNamesProvider($http),
            'erben-folklore' => new ErbenFolkloreProvider($http),
            'czech-namedays' => new CzechNamedaysProvider($http),
            'wikidata' => new WikidataProvider($http, $_ENV['ETYMOLOG_WIKIDATA_USER_AGENT'] ?? 'Etymolog/1.0 (php-core; Wikidata name catalog)'),
            'wikisource' => new WikisourceProvider($http),
            'wiktionary-fr' => new WiktionaryProvider($http, 'fr'),
            'wiktionary-cs' => new WiktionaryProvider($http, 'cs'),
            'wiktionary' => new WiktionaryProvider($http),
            'csu-baby-names' => new CsuBabyNamesProvider($http),
            'poland-pesel' => new PolandPeselProvider($http),
        ]);
        return new EtymologSyncService(new EtymologRepository($db, $tenant, 'sync-jobs'), new EtymologSyncRepository($db, $tenant), $registry, new EtymologStoryRepository($db, $tenant), new EtymologExternalRepository($db, $tenant), new EtymologCalendarRepository($db, $tenant));
    }

    public static function httpWorker(Database $db, string $tenant): EtymologHttpWorkerService
    {
        return new EtymologHttpWorkerService(new EtymologBatchRepository($db, $tenant, 900, 900), self::sync($db, $tenant, true));
    }

    public static function background(Database $db, string $tenant): EtymologBackgroundService
    {
        $dispatch = $_ENV['ETYMOLOG_SYNC_DISPATCH'] ?? 'process';
        $launcher = $dispatch === 'cloudflare'
            ? new Providers\CloudflareDispatchProvider(HttpModule::client(), $_ENV['ETYMOLOG_SYNC_WORKER_URL'] ?? '', $_ENV['ETYMOLOG_SYNC_SECRET'] ?? '')
            : new EtymologWorkerLauncher($tenant, $dispatch);
        return new EtymologBackgroundService(new EtymologBatchRepository($db, $tenant, in_array($dispatch, ['cron','cloudflare'], true) ? 900 : 120, $dispatch === 'cloudflare' ? 900 : 120), self::sync($db, $tenant), $launcher->launch(...));
    }
}
