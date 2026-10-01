<?php
declare(strict_types=1);
namespace App\Modules\Transport;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Api\TransportApi;
use App\Modules\Transport\Core\{IntegrationRegistry,ProviderRegistry,JourneyService,ResourceService};
use App\Modules\Transport\Integrations\Golemio\GolemioModule;
use App\Modules\Transport\Integrations\Spojenka\SpojenkaModule;
use App\Modules\Transport\Integrations\Entur\EnturModule;
use App\Modules\Transport\Integrations\OpenTripPlanner\OpenTripPlannerModule;
use App\Modules\Transport\Persistence\TransportRepository;

/** The only composition root that imports concrete integration modules. */
final class TransportModule
{
    public static function integrations(): IntegrationRegistry
    {
        return new IntegrationRegistry([new GolemioModule(), new SpojenkaModule(), new EnturModule(), new OpenTripPlannerModule()]);
    }

    public static function registry(TransportRepository $repository, array $env): ProviderRegistry
    {
        return ProviderRegistry::build($repository->providers(), $repository->tenant, $env, self::integrations(), $repository);
    }

    public static function api(TransportRepository $repository, array $env, HttpClient $http): TransportApi
    {
        $registry = self::registry($repository, $env);
        $resources = new ResourceService($registry, $http, $repository);
        return new TransportApi(new JourneyService($registry, $http, $repository, $resources), $resources, $registry, $repository, new \App\Modules\Transport\Tracking\TripTrackingService($registry, $resources, $repository->tenant, $env));
    }
    public static function importers(TransportRepository $repository): \App\Modules\Transport\Import\ImporterRegistry
    {
        return new \App\Modules\Transport\Import\ImporterRegistry([
            fn () => new \App\Modules\Transport\Import\Gtfs\GtfsImportService($repository->db, $repository->tenant),
        ]);
    }

    public static function feedSync(TransportRepository $repository, string $storage, HttpClient $http): \App\Modules\Transport\Import\FeedSyncService
    {
        return new \App\Modules\Transport\Import\FeedSyncService($repository, $storage, $http, self::importers($repository));
    }

}
