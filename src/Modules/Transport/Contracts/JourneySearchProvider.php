<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Http\HttpRequest;
use App\Modules\Http\HttpResponse;

interface JourneySearchProvider extends Provider
{
    public function searchRequest(JourneyQuery $query): HttpRequest;
    public function searchResult(HttpResponse $result): array;
}
