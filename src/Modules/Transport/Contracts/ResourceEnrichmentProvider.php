<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Http\Contracts\HttpClient;

/** Optional bounded online enrichment of a resource, using the executor's quota/deadline. */
interface ResourceEnrichmentProvider extends ResourceProvider
{
    public function enrichResource(string $operation, array $result, array $input, HttpClient $http): array;
}
