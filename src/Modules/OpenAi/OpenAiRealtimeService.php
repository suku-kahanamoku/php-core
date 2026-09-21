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
                'instructions' => implode(' ', [
                    'Jsi tichy analyzator ziveho rozhovoru prodejce se zakaznikem nad publikovanym tenantovym katalogem.',
                    'Nikdy nemluv k uzivateli ani nevypisuj prodejni argumenty nebo doporuceni textem.',
                    'Prubezne z celeho dialogu odvozuj potrebu, rozpocet, preference, namitky a profil zakaznika.',
                    'Rozlisuj prodejce a zakaznika podle obsahu a rozhoduj se podle potreb zakaznika.',
                    'Kdyz chybi jedna podstatna informace, muzes zavolat show_customer_question s jednou kratkou ceskou otazkou pro klienta.',
                    'Otazku neopakuj, po jejim zobrazeni pockej na dalsi promluvu a nevolej soucasne jiny nastroj.',
                    'Dokud nemas dost informaci pro konkretni produkt, nevolej katalogove nastroje.',
                    'Profily a produkty nikdy nevymyslej a vzdy je over pomoci dostupnych nastroju.',
                    'Profily nacti nejvyse jednou, potom vyhledej kandidaty a pred zobrazenim nacti detail jedineho produktu.',
                    'Kdyz dialog ukaze, ze zobrazeny produkt nevyhovuje, znovu hledej podle aktualniho kontextu.',
                    'Pri kazdem dalsim hledani predej vsechna drive zobrazena ID v excluded_product_ids a nikdy je znovu nevyber.',
                    'V jedne odpovedi volej nejvyse jeden nastroj.',
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
                'description' => 'Nacte publikovane zakaznicke profily vcetne potreb, otazek, namitek a preferenci.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => OpenAiCatalogService::SEARCH_PRODUCTS,
                'description' => 'Vyhleda publikovane produkty podle profilu zakaznika a omezeni z rozhovoru.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'profile_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'ID overeneho zakaznickeho profilu.'],
                        'query' => ['type' => 'string', 'maxLength' => 200, 'description' => 'Potreba, nazev nebo vlastnosti hledaneho produktu.'],
                        'max_price' => ['type' => 'number', 'exclusiveMinimum' => 0, 'description' => 'Nejvyssi cena s DPH v mene tenantoveho katalogu.'],
                        'category' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Pozadovana kategorie produktu.'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                        'excluded_product_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer', 'minimum' => 1],
                            'maxItems' => OpenAiCatalogService::MAX_EXCLUDED_PRODUCTS,
                            'description' => 'ID produktu, ktere uz byly zobrazeny a nesmi se opakovat.',
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type' => 'function',
                'name' => OpenAiCatalogService::GET_PRODUCT,
                'description' => 'Nacte overeny detail konkretniho publikovaneho produktu pred jeho doporucenim.',
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
                'description' => 'Zobrazi klientovi jednu kratkou ceskou doplnujici otazku, pokud bez ni nelze spolehlive vybrat produkt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'question' => [
                            'type' => 'string',
                            'minLength' => 1,
                            'maxLength' => OpenAiCatalogService::MAX_QUESTION_LENGTH,
                            'description' => 'Jedna srozumitelna otazka primo pro klienta.',
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
