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
    private const MODEL = 'gpt-realtime';
    private const CLIENT_SECRET_TTL_SECONDS = 60;
    private const INPUT_AUDIO_RATE = 24000;

    private Closure $transport;
    private string $apiKey;

    /**
     * Pripravi sluzbu s produkcnim cURL transportem nebo testovacim callbackem.
     *
     * @param Closure|null $transport Testovaci transport se signaturou
     *        `(string $apiKey, array $payload): array{status:int, body:string}`.
     * @param string|null $apiKey Volitelny testovaci klic; v produkci se nacita
     *        z promenne prostredi `OPENAI_API_KEY`.
     */
    public function __construct(?Closure $transport = null, ?string $apiKey = null)
    {
        $this->apiKey = trim($apiKey ?? (string) ($_ENV['OPENAI_API_KEY'] ?? ''));
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
            'model' => self::MODEL,
            'input_audio_format' => [
                'type' => 'audio/pcm',
                'rate' => self::INPUT_AUDIO_RATE,
            ],
            'output_modalities' => ['text'],
        ];
    }

    /**
     * Sestavi pevnou session konfiguraci vhodnou pro kratke odpovedi v brylich.
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
                'model' => self::MODEL,
                'output_modalities' => ['text'],
                'instructions' => implode(' ', [
                    'Jsi strucny cesky asistent pro monochromaticke chytre bryle.',
                    'Odpovidej cesky, prostym textem bez Markdownu.',
                    'Odpoved omez nejvyse na dve kratke vety.',
                ]),
                'max_output_tokens' => 128,
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
