<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use Closure;

/** Vybírá jeden produkt pomocí OpenAI Responses API a hostovaného file_search. */
final class OpenAiResponsesProductRecommender implements OpenAiProductRecommender
{
    private const ENDPOINT = 'https://api.openai.com/v1/responses';
    private const DEFAULT_MODEL = 'gpt-5.6-terra';
    private const MAX_RESULTS = 20;

    private Closure $transport;
    private string $apiKey;
    private string $model;

    /**
     * @param Closure|null $transport Testovací transport se signaturou
     *        `(string $apiKey, array $payload): array{status:int,body:string}`.
     */
    public function __construct(
        private readonly OpenAiVectorStoreGateway $vectorStores,
        ?Closure $transport = null,
        ?string $apiKey = null,
        ?string $model = null,
    ) {
        $this->apiKey = trim($apiKey ?? (string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        $this->model = trim($model ?? (string) ($_ENV['OPENAI_RECOMMENDATION_MODEL'] ?? ''))
            ?: self::DEFAULT_MODEL;
        $this->transport = $transport ?? Closure::fromCallable([$this, 'sendRequest']);
    }

    /** @inheritDoc */
    public function recommend(string $query, string $category, string $priceIntent): array
    {
        if ($this->apiKey === '') {
            throw new OpenAiConfigurationException('OPENAI_API_KEY is not configured.');
        }
        $store = $this->vectorStores->store();
        $vectorStoreId = trim((string) ($store['vector_store_id'] ?? ''));
        if ($vectorStoreId === '') {
            return ['status' => 'unavailable', 'product_id' => null];
        }

        $result = ($this->transport)($this->apiKey, $this->payload(
            $vectorStoreId,
            $query,
            $category,
            $priceIntent,
        ));
        $status = (int) ($result['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new OpenAiUpstreamException('OpenAI recommendation request failed.', $status);
        }
        $response = json_decode((string) ($result['body'] ?? ''), true);
        if (!is_array($response) || ($response['status'] ?? null) !== 'completed') {
            throw new OpenAiUpstreamException('OpenAI returned an incomplete recommendation.', $status);
        }

        $evidenceIds = $this->evidenceProductIds($response);
        $decision = json_decode($this->outputText($response), true);
        if (!is_array($decision) || !in_array($decision['status'] ?? null, ['selected', 'no_match'], true)) {
            throw new OpenAiUpstreamException('OpenAI returned an invalid recommendation.', $status);
        }
        if ($decision['status'] === 'no_match') {
            return ['status' => 'no_match', 'product_id' => null];
        }
        $productId = filter_var($decision['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1 || !isset($evidenceIds[$productId])) {
            throw new OpenAiUpstreamException('OpenAI selected a product without Vector Store evidence.', $status);
        }
        return ['status' => 'selected', 'product_id' => $productId];
    }

    /**
     * Sestaví jediný agentní Responses požadavek, ve kterém OpenAI vyhledá i rozhodne.
     *
     * @return array<string, mixed>
     */
    private function payload(
        string $vectorStoreId,
        string $query,
        string $category,
        string $priceIntent,
    ): array {
        return [
            'model' => $this->model,
            'store' => false,
            'instructions' => <<<'PROMPT'
You select exactly one catalog product for a passive Czech retail assistant.
Use file_search as the only source of product facts. Evaluate every retrieved product yourself against the complete active need, concrete category, confirmed price intent, mandatory constraints, preferences, intended use and rejection reasons.
Never optimize for margin, popularity or inferred customer traits. Never invent an ID or attribute. A maximum price and explicit exclusion are mandatory. Previously shown products are not permanently excluded, but an immediate request for another product should prefer a different suitable result when the input says so.
Return selected only for a product_id present in the file_search evidence. Return no_match when no retrieved document satisfies the evidence.
PROMPT,
            'input' => json_encode([
                'language' => 'cs',
                'category' => $category,
                'price_intent' => $priceIntent,
                'active_need' => $query,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'tools' => [[
                'type' => 'file_search',
                'vector_store_ids' => [$vectorStoreId],
                'max_num_results' => self::MAX_RESULTS,
            ]],
            'tool_choice' => 'required',
            'include' => ['file_search_call.results'],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'product_recommendation',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'enum' => ['selected', 'no_match'],
                            ],
                            'product_id' => [
                                'anyOf' => [
                                    ['type' => 'integer'],
                                    ['type' => 'null'],
                                ],
                            ],
                        ],
                        'required' => ['status', 'product_id'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_output_tokens' => 128,
        ];
    }

    /** @param array<string, mixed> $response @return array<int, true> */
    private function evidenceProductIds(array $response): array
    {
        $ids = [];
        foreach ((array) ($response['output'] ?? []) as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'file_search_call') {
                continue;
            }
            foreach ((array) ($item['results'] ?? []) as $result) {
                if (!is_array($result) || !is_array($result['attributes'] ?? null)) {
                    continue;
                }
                $id = filter_var($result['attributes']['product_id'] ?? null, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0) {
                    $ids[$id] = true;
                }
            }
        }
        return $ids;
    }

    /** @param array<string, mixed> $response */
    private function outputText(array $response): string
    {
        $text = '';
        foreach ((array) ($response['output'] ?? []) as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text') {
                    $text .= (string) ($content['text'] ?? '');
                }
            }
        }
        if (trim($text) === '') {
            throw new OpenAiUpstreamException('OpenAI recommendation output is missing.');
        }
        return $text;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,body:string}
     */
    private function sendRequest(string $apiKey, array $payload): array
    {
        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            throw new OpenAiUpstreamException('OpenAI connection could not be initialized.');
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
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
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }
}
