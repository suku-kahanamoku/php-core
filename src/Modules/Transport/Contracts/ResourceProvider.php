<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Http\HttpRequest;
use App\Modules\Http\HttpResponse;

/**
 * Poskytovatel jednotlivých zdrojových operací (zastávky, linky, plány).
 *
 * Operace je vždy z výčtu deklarovaných schopností; vstup se validuje před
 * sestavením požadavku a odpověď se převádí na jednotný tvar.
 */
interface ResourceProvider extends Provider
{
    /**
     * @param  string               $operation Podporovaná operace.
     * @param  array<string, mixed> $input     Vstup operace.
     * @return HttpRequest                     Požadavek pro sdílený HTTP klient.
     * @throws TransportException              Při nepodporované operaci nebo neplatném vstupu.
     */
    public function resourceRequest(string $operation, array $input): HttpRequest;

    /**
     * @param  string               $operation Podporovaná operace.
     * @param  HttpResponse         $result    Odpověď poskytovatele.
     * @param  array<string, mixed> $input     Vstup operace.
     * @return array<string, mixed>           Normalizovaný výsledek operace.
     * @throws TransportException             Při neplatné nebo neočekávané odpovědi.
     */
    public function resourceResult(string $operation, HttpResponse $result, array $input): array;
}
