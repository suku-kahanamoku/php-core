<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Utils\InternalAuth;

/**
 * Ověří volající aplikaci dříve, než vznikne jakákoli API služba nebo DB připojení.
 *
 * Interní API (klíč v hlavičce) je výrazně oddělené od endpointů pro klienta
 * (token v Authorization). Middleware běží jako první v globálním řetězci.
 */
final class InternalAuthMiddleware
{
    /**
     * Provede interní autentizaci, případně ukončí request chybou 401.
     *
     * @param  Request $request Aktuální požadavek.
     * @return void Vedlejší efekt: při úspěchu nastaví $request->internalAuthenticated,
     *                při selhání ukončí request (401 nebo 204 pro preflight).
     * @throws \Throwable Při chybě interní autentizace je request ukončen přes Response::unauthorized().
     */
    public function __invoke(Request $request): void
    {
        // Preflight carries no credentials and never executes an API handler.
        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // TRAM stores accounts only. Reject other modules before they can open PDO,
        // including the dedicated Rokid-key exception below.
        if ($request->franchiseCode === 'tram') {
            $entry = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
            $root = dirname(__DIR__, 2);
            if (!in_array($entry, [realpath($root . '/api/auth/index.php'),
                realpath($root . '/api/transport-admin/index.php')], true)) {
                Response::forbidden('This tenant only supports authentication and administrator authorization.');
            }
        }

        if ($this->isRokidEndpoint($request)) {
            $configured = trim((string) ($_ENV['ROKID_AI_CLIENT_KEY'] ?? ''));
            $provided = trim((string) $request->header('X-Rokid-Key', ''));
            if ($configured === '' || $provided === '' || !hash_equals($configured, $provided)) {
                Response::unauthorized('Rokid client authentication required.');
            }
            return;
        }

        InternalAuth::require($request);
        $request->internalAuthenticated = true;
    }

    /**
     * Rozhodne, zda pozadavek směřuje na veřejné AI rozhraní pro klienta Rokid.
     *
     * @param  Request $request Aktualni pozadavek.
     * @return bool true, pokud jde o entrypoint api/openai/index.php a cestu /realtime-session nebo /tool.
     */
    private function isRokidEndpoint(Request $request): bool
    {
        // Match the actual entrypoint as well as the method and module-relative route.
        // A similarly named route in another module must never bypass the internal key.
        return $request->method === 'POST'
            && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))
            === realpath(dirname(__DIR__, 2) . '/api/openai/index.php')
            && in_array($request->uri, ['/realtime-session', '/tool'], true);
    }
}
