<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Transport\DTO\JourneyQuery;
use App\Modules\Http\HttpRequest;
use App\Modules\Http\HttpResponse;

/**
 * Poskytovatel vyhledávání spojů.
 *
 * `searchRequest()` staví omezený požadavek přes sdílený HTTP klient,
 * `searchResult()` převádí odpověď na jednotný tvar; mapování neuhýbá mimo
 * deklarované schéma ani limity.
 */
interface JourneySearchProvider extends Provider
{
    /**
     * @param  JourneyQuery $query Normalizovaný dotaz na spoje.
     * @return HttpRequest         Požadavek pro sdílený HTTP klient.
     */
    public function searchRequest(JourneyQuery $query): HttpRequest;

    /**
     * @param  HttpResponse $result  Odpověď poskytovatele.
     * @return array<string, mixed> Normalizované výsledky vyhledávání.
     * @throws TransportException    Při neplatné nebo neočekávané odpovědi.
     */
    public function searchResult(HttpResponse $result): array;
}
