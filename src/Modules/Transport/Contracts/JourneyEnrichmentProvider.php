<?php
declare(strict_types=1);
namespace App\Modules\Transport\Contracts;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Core\ProviderRegistry;

interface JourneyEnrichmentProvider extends Provider
{
    /** A failure must leave the original planned journeys usable. No persistence. */
    public function enrichJourneys(array $journeys, ProviderRegistry $registry, HttpClient $http): array;
}
