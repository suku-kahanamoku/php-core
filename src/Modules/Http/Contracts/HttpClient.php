<?php

declare(strict_types=1);

namespace App\Modules\Http\Contracts;

use App\Modules\Http\{HttpRequest, HttpResponse};

/**
 * Kontrakt odchozího HTTP klienta.
 *
 * Implementace (`HttpService`) žije v modulu Http a je předávána doménovým
 * službám injekcí. Chyby sítě a limitů se nevyhazují jako výjimky — místo toho
 * se vrací `HttpResponse` s kódem 0 a vyplněným `error`.
 */
interface HttpClient
{
    /**
     * Odešle jeden požadavek.
     *
     * @param  HttpRequest $request Popis požadavku včetně per-request limitů a credentials.
     * @return HttpResponse          Odpověď; selhání transportu je vyjádřeno v `error`, ne výjimkou.
     */
    public function send(HttpRequest $request): HttpResponse;

    /**
     * @param  array<array-key,HttpRequest> $requests    Požadavky k odeslání.
     * @param  int                          $budgetMs    Společný časový rozpočet dávky v milisekundách.
     * @param  int                          $concurrency Maximální souběžnost (1–32).
     * @return array<array-key,HttpResponse>            Výsledky se stejnými klíči jako vstup.
     * @throws \InvalidArgumentException Pokud jsou limity neplatné nebo položka není `HttpRequest`.
     */
    public function sendAll(array $requests, int $budgetMs = 6000, int $concurrency = 4): array;
}
