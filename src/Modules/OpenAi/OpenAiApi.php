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
    private OpenAiKnowledgeCatalogService $catalog;
    private RateLimiter $rateLimiter;

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
        ?OpenAiKnowledgeCatalogService $catalog = null,
    ) {
        $this->service = $service ?? new OpenAiRealtimeService();
        if ($catalog !== null) {
            $this->catalog = $catalog;
        } else {
            $recommender = null;
            if (filter_var($_ENV['OPENAI_VECTOR_STORE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL)) {
                $recommender = new OpenAiResponsesProductRecommender(
                    new OpenAiVectorStoreRepository($db, $franchiseCode),
                );
            }
            $this->catalog = new OpenAiKnowledgeCatalogService(
                new OpenAiCatalogRepository($db, $franchiseCode),
                $recommender,
            );
        }
        $this->rateLimiter = new RateLimiter($db, $franchiseCode);
    }

    /**
     * Zaregistruje vytvoreni Realtime relace a katalogove AI nastroje.
     *
     * @param Router $router Router endpointu `/api/openai`.
     */
    public function registerRoutes(Router $router): void
    {
        $router->post('/realtime-session', [$this, 'createRealtimeSession']);
        $router->post('/tool', [$this, 'executeTool']);
    }

    /**
     * Overi tenant a aplikacni klic, aplikuje limit a vrati docasny token.
     *
     * @param Request $request Pozadavek s hlavickou `X-Rokid-Key`.
     */
    public function createRealtimeSession(Request $request): void
    {
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

    /**
     * Overi Rokid klienta a provede jeden povoleny read-only katalogovy nastroj.
     *
     * @param Request $request JSON telo s `name` a objektovymi `arguments`.
     */
    public function executeTool(Request $request): void
    {
        $this->rateLimiter->hit(
            'rokid-ai-tool',
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            60,
            60,
        );
        $this->requireRokidKey($request);

        $name = trim((string) $request->get('name', ''));
        $arguments = $request->get('arguments', []);
        if ($name === '' || !is_array($arguments)) {
            Response::validationError([
                'name' => $name === '' ? 'Tool name is required.' : null,
                'arguments' => !is_array($arguments) ? 'Tool arguments must be an object.' : null,
            ]);
        }
        try {
            $result = $this->catalog->execute($name, $arguments);
        } catch (\InvalidArgumentException $error) {
            Response::validationError(['tool' => $error->getMessage()]);
        }
        Response::success($result, 'AI tool completed.');
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
