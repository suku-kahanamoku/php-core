<?php

declare(strict_types=1);

namespace App\Utils;

use App\Modules\Router\Request;
use App\Modules\Router\Response;

/**
 * Pomocné funkce pro interní autentizaci volající aplikace.
 *
 * Volitelné globální helpery — nemají vlastní stav a používají se staticky
 * z middleware (`InternalAuthMiddleware`) před vytvořením jakékoli služby.
 */
final class InternalAuth
{
    /**
     * Ověří, zda požadavek obsahuje platný interní klíč.
     *
     * @param  Request $request Aktuální požadavek s hlavičkami.
     * @return bool             true pouze při shodě hlavičky `X-Internal-Key` s `INTERNAL_API_KEY`.
     */
    public static function check(Request $request): bool
    {
        $configured = trim((string) ($_ENV['INTERNAL_API_KEY'] ?? ''));
        $provided   = trim((string) $request->header('X-Internal-Key', ''));

        return $configured !== ''
            && $provided !== ''
            && hash_equals($configured, $provided);
    }

    /**
     * Vyžaduje platný interní klíč, jinak request ukončí s 401.
     *
     * @param  Request $request Aktuální požadavek.
     * @return void            Vedlejší efekt: při selhání ukončí request přes Response::unauthorized().
     */
    public static function require(Request $request): void
    {
        if (!self::check($request)) {
            Response::unauthorized('Internal authentication required.');
        }
    }
}
