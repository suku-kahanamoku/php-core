<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Router\Request;

/**
 * Middleware, který na každý odpověď přidá CORS hlavičky.
 *
 * Autentizace používá explicitní tokeny, nikoli cross-origin cookies, takže
 * je povoleno `Access-Control-Allow-Origin: *`. Middleware neukončuje request
 * ani nemění stavový kód — pouze poskytne hlavičky, což dělají i předflight
 * (OPTIONS) dotazy, jež zpracují až další middleware.
 */
class CorsMiddleware
{
    /**
     * Přidá CORS hlavičky do odpovědi.
     *
     * @param  Request|null $request Aktuální požadavek; pro CORS není potřeba a lze předat null.
     * @return void Vedlejší efekt: volání header() pro Access-Control-Allow-Origin,
     *                -Allow-Methods a -Allow-Headers.
     */
    public function __invoke(?Request $request = null): void
    {
        // Authentication uses explicit tokens, not cross-origin cookies.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $allowedHeaders = 'Content-Type, Authorization, Accept-Language, X-Requested-With';
        header("Access-Control-Allow-Headers: {$allowedHeaders}");
    }
}
