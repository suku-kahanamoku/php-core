<?php
declare(strict_types=1);
namespace App\Modules\Transport\Contracts;

use App\Modules\Http\Contracts\HttpClient;

/** Optional bounded prerequisite calls, using only the executor-supplied client. */
interface ResourcePreparationProvider extends ResourceProvider
{
    public function prepareResource(string $operation, array $input, HttpClient $http): array;
}
