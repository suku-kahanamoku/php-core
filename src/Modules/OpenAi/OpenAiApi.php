<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;
use App\Utils\RateLimiter;

/**
 * Publikuje uzce omezeny endpoint pro zahajeni Rokid AI relace.
 *
 * Endpoint nevytvari lokalni WebSocket server. Po overeni aplikacniho klice
 * vyda kratkodoby OpenAI client secret, se kterym mobil navaze primy Realtime
 * WebSocket bez zverejneni hlavniho `OPENAI_API_KEY`.
 */
final class OpenAiApi
{
    private OpenAiRealtimeService $service;
    private RateLimiter $rateLimiter;
    private string $franchiseCode;

    /**
     * Pripravi API vrstvu pro aktualniho tenanta.
     *
     * @param Database $db Databaze pouzita sdilenym rate limiterem.
     * @param string $franchiseCode Tenant vyreseny z duveryhodneho hostu.
     * @param OpenAiRealtimeService|null $service Volitelna testovaci sluzba.
     */
    public function __construct(
        Database $db,
        string $franchiseCode,
        ?OpenAiRealtimeService $service = null,
    ) {
        $this->service = $service ?? new OpenAiRealtimeService();
        $this->rateLimiter = new RateLimiter($db, $franchiseCode);
        $this->franchiseCode = $franchiseCode;
    }

    /**
     * Zaregistruje vytvoreni Realtime relace jako jediny verejny kontrakt modulu.
     *
     * @param Router $router Router endpointu `/api/openai`.
     */
    public function registerRoutes(Router $router): void
    {
        $router->post('/realtime-session', [$this, 'createRealtimeSession']);
    }

    /**
     * Overi tenant a aplikacni klic, aplikuje limit a vrati docasny token.
     *
     * @param Request $request Pozadavek s hlavickou `X-Rokid-Key`.
     */
    public function createRealtimeSession(Request $request): void
    {
        $this->requireAllowedFranchise();
        $this->rateLimiter->hit(
            'rokid-ai-session',
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            10,
            60,
        );
        $this->requireRokidKey($request);

        try {
            $session = $this->service->createClientSecret();
        } catch (OpenAiConfigurationException) {
            Response::error('AI service is not configured.', 503);
        } catch (OpenAiUpstreamException) {
            Response::error('AI service is temporarily unavailable.', 502);
        }

        Response::success($session, 'Realtime session created.');
    }

    /** Vyzaduje serverem povoleny tenant, aby endpoint nesdilely ostatni CRM. */
    private function requireAllowedFranchise(): void
    {
        $allowed = trim((string) ($_ENV['ROKID_AI_FRANCHISE_CODE'] ?? ''));
        if ($allowed === '' || !hash_equals($allowed, $this->franchiseCode)) {
            Response::forbidden('AI service is not available for this tenant.');
        }
    }

    /**
     * Overi oddeleny rotovatelny klic Rokid aplikace konstantnim porovnanim.
     *
     * @param Request $request Pozadavek obsahujici `X-Rokid-Key`.
     */
    private function requireRokidKey(Request $request): void
    {
        $configured = trim((string) ($_ENV['ROKID_AI_CLIENT_KEY'] ?? ''));
        $provided = trim((string) $request->header('X-Rokid-Key', ''));
        if (
            $configured === ''
            || $provided === ''
            || !hash_equals($configured, $provided)
        ) {
            Response::unauthorized('Rokid client authentication required.');
        }
    }
}
