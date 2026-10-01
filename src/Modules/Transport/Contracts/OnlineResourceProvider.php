<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Http\Contracts\HttpClient;

/** Resource requiring several online sources, within one executor quota/deadline. */
interface OnlineResourceProvider extends Provider
{
    public function resourceOnline(string $operation, array $input, HttpClient $http): array;
}
