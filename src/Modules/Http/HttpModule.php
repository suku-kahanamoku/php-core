<?php

declare(strict_types=1);

namespace App\Modules\Http;

use App\Modules\Http\Contracts\HttpClient;

/**
 * Composition root pro odchozí transporty. Doménové služby přijímají injektované kontrakty.
 *
 * Podle konvencí projektu je `HttpModule::client()` jediný povolený zdroj HTTP
 * klienta a `HttpModule::smtp()` jediný povolený zdroj SMTP spojení. Volání
 * vytvářející konkrétní implementaci (`new HttpService()`, `new SmtpService()`)
 * patří výhradně do composition rootu, ne do domény.
 */
final class HttpModule
{
    private static ?HttpClient $http = null;
    public static function asyncClient(): \App\Modules\Http\Contracts\AsyncHttpClient
    {
        return new AsyncHttpService(self::client());
    }
    public static function websocket(): WebSocketService
    {
        return new WebSocketService();
    }

    /**
     * Vrátí sdílenou instanci HTTP klienta pro daný request.
     *
     * @return HttpClient Sdílená, bezpečná pro souběžné použití instance `HttpService`.
     */
    public static function client(): HttpClient
    {
        return self::$http ??= new HttpService();
    }

    /**
     * Vytvoří SMTP transport pro konkrétní okurka (tenantu).
     *
     * @param  string       $franchiseCode Kód okruku, podle kterého se hledá konfigurace SMTP.
     * @return SmtpService               Instance s credentials daného tenantu; nikdy se nesdílí mezi okurky.
     */
    public static function smtp(string $franchiseCode = ''): SmtpService
    {
        return new SmtpService($franchiseCode);
    }
}
