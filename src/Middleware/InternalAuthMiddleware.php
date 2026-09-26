<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Utils\InternalAuth;

/** Authenticates the calling application before any API service or database is created. */
final class InternalAuthMiddleware
{
    public function __invoke(Request $request): void
    {
        // Preflight carries no credentials and never executes an API handler.
        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
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
