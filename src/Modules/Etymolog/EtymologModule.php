<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Database\Database;
use App\Modules\Http\HttpModule;
use App\Modules\Etymolog\Providers\{WikidataProvider, WikisourceProvider, WikipediaNamesProvider, WiktionaryProvider, PolandPeselProvider, CsuBabyNamesProvider, ErbenFolkloreProvider, CzechNamedaysProvider};

/**
 * Composition root modulu Etymolog: skládá synchronizační služby a poskytovatele.
 *
 * Všechny odchozí HTTP požadavky jdou přes `HttpModule::client()`, takže se
 * používá sdílený klient s konfigurovanými timeouty a hlavičkami. Dekorátory se
 * skládají v pevném pořadí: nejprve rozpočet HTTP kroku, pak Wikimedia pacing,
 * jinak by pomalé dotazy krok překročily.
 */
final class EtymologModule
{
    /**
     * Sestaví synchronizační služby pro daný okurk.
     *
     * @param  Database $db       Databazove pripojeni.
     * @param  string   $tenant   Kod okurku; vsechny repozitare jsou jim omezene.
     * @param  bool     $httpStep true pro workera, ktery kazdy krok omezuje rozpočtem.
     * @return EtymologSyncService  Sluzby synchronizace se zaregistrovanymi zdroji.
     */
    public static function sync(Database $db, string $tenant, bool $httpStep = false): EtymologSyncService
    {
        $http = HttpModule::client();
        if ($httpStep) { $http = new Providers\SyncBudgetProvider($http); }
        $http = new Providers\WikimediaHttpProvider($http); // Includes pacing time in the HTTP-step budget.
        $names = new EtymologDiscoveryRepository($db, $tenant);
        $snapshots = new EtymologSnapshotRepository(dirname(__DIR__, 3).'/temp/etymolog-snapshots', $tenant);
        $registry = new ProviderRegistry([
            'wikipedia-names' => new WikipediaNamesProvider($http, $names),
            'erben-folklore' => new ErbenFolkloreProvider($http, $names),
            'czech-namedays' => new CzechNamedaysProvider($http),
            'wikidata' => new WikidataProvider($http, $_ENV['ETYMOLOG_WIKIDATA_USER_AGENT'] ?? 'Etymolog/1.0 (https://etymolog.prasentace.cz; name history research)'),
            'wikisource' => new WikisourceProvider($http, $names),
            'wiktionary-fr' => new WiktionaryProvider($http, $names, 'fr'),
            'wiktionary-cs' => new WiktionaryProvider($http, $names, 'cs'),
            'wiktionary' => new WiktionaryProvider($http, $names),
            'csu-baby-names' => new CsuBabyNamesProvider($http, $snapshots),
            'poland-pesel' => new PolandPeselProvider($http, $snapshots),
        ]);
        return new EtymologSyncService(new EtymologRepository($db, $tenant, 'sync-jobs'), new EtymologSyncRepository($db, $tenant), $registry, new EtymologStoryRepository($db, $tenant), new EtymologExternalRepository($db, $tenant), new EtymologCalendarRepository($db, $tenant));
    }

    /**
     * Sestaví služby workera pro dávky spouštěné jedním HTTP požadavkem.
     *
     * @param  Database $db     Databazove pripojeni.
     * @param  string   $tenant Kod okurku.
     * @return EtymologHttpWorkerService  Worker s dávkou v plne transakci.
     */
    public static function httpWorker(Database $db, string $tenant): EtymologHttpWorkerService
    {
        return new EtymologHttpWorkerService(new EtymologBatchRepository($db, $tenant, 900, 900), self::sync($db, $tenant, true));
    }

    /**
     * Sestaví služby pro spuštění a průběh synchronizace na pozadí.
     *
     * Způsob spuštění volí proměnná `ETYMOLOG_SYNC_DISPATCH`: 'process' spouští
     * CLI proces, 'cloudflare' volá workera přes HTTP a 'cron' běží v plánované
     * úloze. Dlouhé čekání workera se u HTTP varianta prodlužuje, aby je
     * nepřerušila vypršelá fronta.
     *
     * @param  Database $db     Databazove pripojeni.
     * @param  string   $tenant Kod okurku.
     * @return EtymologBackgroundService  Sluzby spusteni a prubehu synchronizace.
     */
    public static function background(Database $db, string $tenant): EtymologBackgroundService
    {
        $dispatch = $_ENV['ETYMOLOG_SYNC_DISPATCH'] ?? 'process';
        $launcher = $dispatch === 'cloudflare'
            ? new Providers\CloudflareDispatchProvider(HttpModule::client(), $_ENV['ETYMOLOG_SYNC_WORKER_URL'] ?? '', $_ENV['ETYMOLOG_SYNC_SECRET'] ?? '')
            : new EtymologWorkerLauncher($tenant, $dispatch);
        return new EtymologBackgroundService(new EtymologBatchRepository($db, $tenant, in_array($dispatch, ['cron','cloudflare'], true) ? 900 : 120, $dispatch === 'cloudflare' ? 900 : 120), self::sync($db, $tenant), $launcher->launch(...));
    }
}
