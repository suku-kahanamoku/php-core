<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use Closure;
use App\Modules\Http\{HttpModule, HttpRequest};
use App\Modules\Http\Contracts\HttpClient;

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
        private readonly ?HttpClient $http = null,
    ) {
        $this->apiKey = trim($apiKey ?? (string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        $this->model = trim($model ?? (string) ($_ENV['OPENAI_RECOMMENDATION_MODEL'] ?? ''))
            ?: self::DEFAULT_MODEL;
        $this->transport = $transport ?? Closure::fromCallable([$this, 'sendRequest']);
    }

    /** @inheritDoc */
    public function recommend(string $query, string $category, string $priceIntent, ?int $excludedProductId = null): array
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
            $excludedProductId,
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
        if (!is_array($decision) || ($decision['status'] ?? null) !== 'selected') {
            throw new OpenAiUpstreamException('OpenAI returned an invalid recommendation.', $status);
        }
        $productId = filter_var($decision['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1 || !isset($evidenceIds[$productId])) {
            throw new OpenAiUpstreamException('OpenAI selected a product without Vector Store evidence.', $status);
        }
        if ($excludedProductId !== null && $productId === $excludedProductId) {
            throw new OpenAiUpstreamException('OpenAI selected the explicitly excluded current product.', $status);
        }
        $matchQuality = (string) ($decision['match_quality'] ?? '');
        $reason = trim((string) ($decision['reason'] ?? ''));
        if (!in_array($matchQuality, ['exact', 'nearest'], true)) {
            throw new OpenAiUpstreamException('OpenAI returned an invalid match quality.', $status);
        }
        if ($matchQuality === 'nearest' && $reason === '') {
            throw new OpenAiUpstreamException('OpenAI omitted the nearest-match reason.', $status);
        }
        return [
            'status' => 'selected',
            'product_id' => $productId,
            'match_quality' => $matchQuality,
            'reason' => $matchQuality === 'nearest' ? mb_substr($reason, 0, 240) : '',
        ];
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
        ?int $excludedProductId,
    ): array {
        $fileSearchTool = [
            'type' => 'file_search',
            'vector_store_ids' => [$vectorStoreId],
            'max_num_results' => self::MAX_RESULTS,
        ];
        if ($excludedProductId !== null) {
            $fileSearchTool['filters'] = [
                'type' => 'ne',
                'key' => 'product_id',
                'value' => $excludedProductId,
            ];
        }

        return [
            'model' => $this->model,
            'store' => false,
            'instructions' => <<<'PROMPT'
You select exactly one catalog product for a passive Czech retail assistant.
Use file_search as the only source of product facts. Evaluate every retrieved product yourself against the complete active need, concrete category, confirmed price intent, constraints, preferences, intended use and rejection reasons.
Never optimize for margin, popularity or inferred customer traits. Never invent an ID or attribute. Previously shown products are not permanently excluded. When excluded_product_id is present, it is a mandatory one-request exclusion: never select that ID, while all older products remain eligible.
Always return exactly one product_id present in the file_search evidence. Never return no_match after the mandatory category and price gate has been completed.
Use match_quality exact only when the selected product satisfies the active category, confirmed price intent, explicit constraints and current in-stock requirement.
When no exact in-stock candidate exists, return the closest evidence-backed candidate with match_quality nearest. Prefer an in-stock product in the requested category, then minimize deviations from explicit constraints, price and preferences. If every retrieved candidate is unavailable, still choose the closest catalog item.
For nearest, write a short Czech reason naming the important unmet condition, for example unavailable stock, no product in the requested price range, or the closest category or attribute mismatch. Do not claim a mismatch that is absent from the evidence. For exact, return an empty reason.
PROMPT,
            'input' => json_encode([
                'language' => 'cs',
                'category' => $category,
                'price_intent' => $priceIntent,
                'active_need' => $query,
                'excluded_product_id' => $excludedProductId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'tools' => [$fileSearchTool],
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
                                'enum' => ['selected'],
                            ],
                            'product_id' => [
                                'type' => 'integer',
                            ],
                            'match_quality' => [
                                'type' => 'string',
                                'enum' => ['exact', 'nearest'],
                            ],
                            'reason' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['status', 'product_id', 'match_quality', 'reason'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'max_output_tokens' => 192,
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
        $response = ($this->http ?? HttpModule::client())->send(new HttpRequest(
            self::ENDPOINT, 'POST', ['Authorization' => 'Bearer '.$apiKey, 'Accept' => 'application/json'],
            $payload, timeoutMs: 60000, connectTimeoutMs: 10000,
        ));
        if ($response->error !== null) {
            throw new OpenAiUpstreamException('OpenAI connection failed.');
        }
        return ['status' => $response->status, 'body' => $response->body];
    }
}
