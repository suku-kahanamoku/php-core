<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use Closure;

/**
 * Vytvari kratkodobe klientské tokeny pro OpenAI Realtime API.
 *
 * Hlavni `OPENAI_API_KEY` zustava pouze na serveru. Mobilni aplikace dostane
 * kratkodoby client secret svazany s textovym ceskym asistentem, PCM24 vstupem
 * a serverovym rozpoznavanim konce reci.
 */
final class OpenAiRealtimeService
{
    private const ENDPOINT = 'https://api.openai.com/v1/realtime/client_secrets';
    private const DEFAULT_MODEL = 'gpt-realtime';
    private const CLIENT_SECRET_TTL_SECONDS = 60;
    private const INPUT_AUDIO_RATE = 24000;

    private Closure $transport;
    private string $apiKey;
    private string $model;

    /**
     * Pripravi sluzbu s produkcnim cURL transportem nebo testovacim callbackem.
     *
     * @param Closure|null $transport Testovaci transport se signaturou
     *        `(string $apiKey, array $payload): array{status:int, body:string}`.
     * @param string|null $apiKey Volitelny testovaci klic; v produkci se nacita
     *        z promenne prostredi `OPENAI_API_KEY`.
     * @param string|null $model Volitelny model; jinak `OPENAI_REALTIME_MODEL`
     *        nebo bezpecna vychozi hodnota.
     */
    public function __construct(?Closure $transport = null, ?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = trim($apiKey ?? (string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        $this->model = trim($model ?? (string) ($_ENV['OPENAI_REALTIME_MODEL'] ?? '')) ?: self::DEFAULT_MODEL;
        $this->transport = $transport ?? Closure::fromCallable([$this, 'sendRequest']);
    }

    /**
     * Vytvori omezeny Realtime client secret pro mobilni aplikaci.
     *
     * @return array{client_secret:string, expires_at:int, model:string,
     *     input_audio_format:array{type:string, rate:int}, output_modalities:list<string>}
     * @throws OpenAiConfigurationException Pokud server nema hlavni API klic.
     * @throws OpenAiUpstreamException Pri chybe site nebo neplatne odpovedi OpenAI.
     */
    public function createClientSecret(): array
    {
        if ($this->apiKey === '') {
            throw new OpenAiConfigurationException('OPENAI_API_KEY is not configured.');
        }

        $payload = $this->sessionPayload();
        $result = ($this->transport)($this->apiKey, $payload);
        $status = (int) ($result['status'] ?? 0);
        $body = (string) ($result['body'] ?? '');

        if ($status < 200 || $status >= 300) {
            throw new OpenAiUpstreamException(
                'OpenAI client secret request failed.',
                $status,
            );
        }

        $decoded = json_decode($body, true);
        $secret = is_array($decoded) ? trim((string) ($decoded['value'] ?? '')) : '';
        $expiresAt = is_array($decoded) ? (int) ($decoded['expires_at'] ?? 0) : 0;
        if ($secret === '' || $expiresAt <= time()) {
            throw new OpenAiUpstreamException('OpenAI returned an invalid client secret.');
        }

        return [
            'client_secret' => $secret,
            'expires_at' => $expiresAt,
            'model' => $this->model,
            'input_audio_format' => [
                'type' => 'audio/pcm',
                'rate' => self::INPUT_AUDIO_RATE,
            ],
            'output_modalities' => ['text'],
        ];
    }

    /**
     * Sestavi relaci ticheho analyzatoru, ktery prubezne doporucuje produkty.
     *
     * @return array<string, mixed> JSON payload pro OpenAI client-secrets endpoint.
     */
    private function sessionPayload(): array
    {
        return [
            'expires_after' => [
                'anchor' => 'created_at',
                'seconds' => self::CLIENT_SECRET_TTL_SECONDS,
            ],
            'session' => [
                'type' => 'realtime',
                'model' => $this->model,
                'output_modalities' => ['text'],
                'instructions' => implode("\n", [
                    '# Role and objective',
                    'Silently analyze the live Czech conversation between a salesperson and a customer.',
                    'Identify the customer need and select one verified product from the published tenant catalog.',
                    'Never speak to the user or emit sales arguments, recommendations, or other free text.',
                    '',
                    '# Product selection',
                    'Distinguish the salesperson from the customer by meaning and prioritize the customer needs.',
                    'Track the category, concrete needs, desired properties, constraints, budget, and objections across the full conversation.',
                    'Select the primary product only from its description, category, and selection_attributes.',
                    'Consider product type, audience, needs, effects, finish, ingredients, fragrance notes and character, occasion, and constraints.',
                    'Never use a customer profile or purchase probability for primary selection; reserve them for future upsell.',
                    'Put only confirmed positive requirements in attributes. Put rejected properties, allergens, and avoided ingredients in excluded_attributes.',
                    '',
                    '# Missing information',
                    'If one important discriminator is missing, call show_customer_question with exactly one short question written in Czech.',
                    'Ask the question that best separates the current candidates, do not repeat it, then wait for more speech without calling another tool.',
                    'For fragrance, clarify recipient, liked or rejected character and notes, intensity, occasion, and budget.',
                    'For skincare, clarify skin type and sensitivity, primary need, texture or routine step, and avoided ingredients.',
                    'For makeup, clarify product type, shade or tone, coverage and finish, durability or water resistance, and sensitivity.',
                    'For body care, clarify skin need and type, format, fragrance, formulation constraints, and whether it is for daily use, travel, or a gift.',
                    '',
                    '# Tools',
                    'Do not call catalog tools until there is enough information to identify a concrete product.',
                    'Never invent products. Use only the provided tools and verify the selected product.',
                    'Do not call list_customer_profiles during primary selection; it is reserved for future upsell.',
                    'Search by confirmed attributes, then load the detail of exactly one best matching product before display.',
                    'If later dialogue rejects the displayed product, search again using the updated conversation context.',
                    'For every later search, include every previously displayed ID in excluded_product_ids and never select it again.',
                    'Call at most one tool per response.',
                ]),
                'max_output_tokens' => 64,
                'tool_choice' => 'auto',
                'tools' => $this->toolDefinitions(),
                'audio' => [
                    'input' => [
                        'format' => [
                            'type' => 'audio/pcm',
                            'rate' => self::INPUT_AUDIO_RATE,
                        ],
                        'turn_detection' => [
                            'type' => 'server_vad',
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> Pevne JSON definice read-only katalogovych nastroju. */
    private function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'name' => OpenAiCatalogService::LIST_PROFILES,
                'description' => 'Load published customer profiles for future upsell only. Never use them for primary product selection.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => OpenAiCatalogService::SEARCH_PRODUCTS,
                'description' => 'Search published products by concrete needs and product attributes from the conversation, without profile probability.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Product need, name, or properties.'],
                        'attributes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => OpenAiCatalogService::MAX_SEARCH_ATTRIBUTE_LENGTH],
                            'maxItems' => OpenAiCatalogService::MAX_SEARCH_ATTRIBUTES,
                            'description' => 'Confirmed positive requirements, such as fresh, for women, evening, sensitive skin, or waterproof.',
                        ],
                        'excluded_attributes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => OpenAiCatalogService::MAX_SEARCH_ATTRIBUTE_LENGTH],
                            'maxItems' => OpenAiCatalogService::MAX_SEARCH_ATTRIBUTES,
                            'description' => 'Explicitly rejected properties or ingredients. Matching products are excluded.',
                        ],
                        'max_price' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Maximum price including VAT in the tenant catalog currency.'],
                        'category' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Required product category.'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                        'excluded_product_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer', 'minimum' => 1],
                            'maxItems' => OpenAiCatalogService::MAX_EXCLUDED_PRODUCTS,
                            'description' => 'Previously displayed product IDs that must not be selected again.',
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => OpenAiCatalogService::GET_PRODUCT,
                'description' => 'Load the verified detail of one published product before displaying it.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['product_id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => OpenAiCatalogService::SHOW_QUESTION,
                'description' => 'Display one short Czech clarification question when a reliable product cannot yet be selected.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'question' => [
                            'type' => 'string',
                            'minLength' => 1,
                            'maxLength' => OpenAiCatalogService::MAX_QUESTION_LENGTH,
                            'description' => 'One clear question written in Czech and addressed directly to the customer.',
                        ],
                    ],
                    'required' => ['question'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Odesle konfiguraci do OpenAI pomoci serveroveho API klice.
     *
     * @param string $apiKey Tajny serverovy OpenAI API klic.
     * @param array<string, mixed> $payload Session konfigurace.
     * @return array{status:int, body:string}
     * @throws OpenAiUpstreamException Pokud cURL nelze inicializovat nebo selze spojeni.
     */
    private function sendRequest(string $apiKey, array $payload): array
    {
        $curl = curl_init(self::ENDPOINT);
        if ($curl === false) {
            throw new OpenAiUpstreamException('OpenAI connection could not be initialized.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new OpenAiUpstreamException(
                $error !== '' ? 'OpenAI connection failed.' : 'OpenAI returned no response.',
            );
        }

        return ['status' => $status, 'body' => $body];
    }
}
