<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Database\Database;
use App\Modules\Http\HttpModule;
use App\Modules\Etymolog\Providers\{WikidataProvider, WikisourceProvider, WikipediaNamesProvider, WiktionaryProvider, PolandPeselProvider, CsuBabyNamesProvider, ErbenFolkloreProvider, CzechNamedaysProvider};

final class EtymologModule
{
    public static function sync(Database $db, string $tenant): EtymologSyncService
    {
        $http = HttpModule::client();
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

    public static function background(Database $db, string $tenant): EtymologBackgroundService
    {
        return new EtymologBackgroundService(new EtymologBatchRepository($db, $tenant), self::sync($db, $tenant), (new EtymologWorkerLauncher($tenant))->launch(...));
    }
}
