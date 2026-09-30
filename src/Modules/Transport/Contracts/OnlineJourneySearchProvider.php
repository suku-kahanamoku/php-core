<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Model\JourneyQuery;

/** Provider composing journeys from several bounded live API requests. */
interface OnlineJourneySearchProvider extends Provider
{
    public function supportsQuery(JourneyQuery $query): bool;

    /** @return array{journeys: list<array<string, mixed>>, limited: bool} */
    public function searchOnline(JourneyQuery $query, HttpClient $http, int $budgetMs): array;
}
