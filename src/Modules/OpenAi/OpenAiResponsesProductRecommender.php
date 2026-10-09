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
    private const MAX_RESULTS = 50;

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
    public function recommend(string $query, string $category, string $priceIntent, ?int $excludedProductId = null, ?int $currentProductId = null, ?int $addonForProductId = null): array
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
            $currentProductId,
            $addonForProductId,
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
        if ($addonForProductId !== null && is_array($decision) && ($decision['status'] ?? null) === 'no_match'
            && array_key_exists('product_id', $decision) && $decision['product_id'] === null
            && ($decision['match_quality'] ?? null) === 'nearest'
            && trim((string) ($decision['reason'] ?? '')) !== '' && $this->hasHostedSearch($response)) {
            return ['status' => 'no_match', 'product_id' => null];
        }
        if (!is_array($decision) || ($decision['status'] ?? null) !== 'selected') {
            throw new OpenAiUpstreamException('OpenAI returned an invalid recommendation.', $status);
        }
        $productId = filter_var($decision['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1 || !isset($evidenceIds[$productId])) {
            throw new OpenAiUpstreamException('OpenAI selected a product without Vector Store evidence.', $status);
        }
        if (($excludedProductId !== null && $productId === $excludedProductId) || $productId === $addonForProductId) {
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
        ?int $currentProductId,
        ?int $addonForProductId,
    ): array {
        $fileSearchTool = [
            'type' => 'file_search',
            'vector_store_ids' => [$vectorStoreId],
            'max_num_results' => self::MAX_RESULTS,
        ];
        $excludedIds = array_unique(array_filter([$excludedProductId, $addonForProductId], static fn(?int $id): bool => $id !== null));
        $filters = [];
        foreach ($excludedIds as $id) {
            $filters[] = [
                'type' => 'ne',
                'key' => 'product_id',
                'value' => $id,
            ];
        }
        if ($filters !== []) {
            $fileSearchTool['filters'] = count($filters) === 1 ? $filters[0] : ['type' => 'and', 'filters' => $filters];
        }

        return [
            'model' => $this->model,
            'store' => false,
            'instructions' => <<<'PROMPT'
You select exactly one catalog product for a passive Czech retail assistant.
Use file_search as the only source of product facts. Evaluate every retrieved product yourself against the complete active need, concrete category, confirmed price intent, constraints, preferences, intended use and rejection reasons.
Use the latest confirmed category and price_intent, not superseded values or constraints belonging to an abandoned category or another purchase need. A corrected maximum replaces the old maximum; explicitly unrestricted price removes it. For a similar product at a different price, preserve accepted attributes and evaluate the requested cheaper or more expensive direction against the verified reference price supplied in active_need, keeping any explicit cap that was not changed. Never invent a reference price or numeric budget; an unverifiable relative price condition cannot qualify as exact. A category change requires searching the new category, not combining it with the previous category or its irrelevant attributes. A deliberate return is still evaluated against the latest valid requirements.
Search for the complete need first. Before declaring nearest because of stock, price or attributes, run a second targeted search for the missing condition; broaden optional preferences if necessary. Compare evidence across your searches, not only the first returned document. Use at most three searches. Search snippets are not the entire catalog, so do not claim a catalog-wide absence without evidence; describe the selected product's specific deviation instead.
current_product_id identifies the card currently on the salesperson display. If active_need requests another product or rejects this card, select a different evidence-backed ID even if excluded_product_id was omitted. Preserve all valid requirements. A deliberate request to return permits that earlier ID.
Treat dissatisfaction with the offered product as a revised active need, including when expressed as a comparison rather than an explicit request for another product. Preserve the confirmed category, budget, recipient, intended use and all unchanged constraints; incorporate the stated rejection reason and desired replacement attributes into file_search and candidate comparison. For a fragrance described as too sweet with a preference for freshness, seek a different, fresher and less sweet fragrance within the existing budget; for a cream described as too rich, seek a lighter texture while retaining confirmed skin needs. Do not equate a different ID with a suitable alternative if it repeats the disliked characteristic. Distinguish a mild dislike from an explicit attribute prohibition, do not broaden a single-product rejection to its whole brand or category, and never invent an unstated rejection reason. If no exact alternative meets the revised need, return the closest eligible evidence-backed different product as nearest with its specific unmet condition in the Czech reason.
Never optimize for margin, popularity or inferred customer traits. Never invent an ID or attribute. Previously shown products are not permanently excluded. When excluded_product_id is present, it is a mandatory one-request exclusion: never select that ID, while all older products remain eligible.
For a primary recommendation (addon_for_product_id is null), always return exactly one product_id present in the file_search evidence. Never return no_match after the mandatory category and price gate has been completed.
Use match_quality exact only when the selected product satisfies the active category, confirmed price intent, explicit constraints and current in-stock requirement.
When no exact in-stock candidate exists, return the closest evidence-backed candidate with match_quality nearest. Prefer an in-stock product in the requested category, then minimize deviations from explicit constraints, price and preferences. If every retrieved candidate is unavailable, still choose the closest catalog item.
For nearest, write a short Czech reason naming the important unmet condition, for example unavailable stock, no product in the requested price range, or the closest category or attribute mismatch. Do not claim a mismatch that is absent from the evidence. For exact, return an empty reason.

# Optional complementary offer
When addon_for_product_id is present, the task is one optional complementary product after an explicit customer purchase decision, not a replacement or a more expensive version of the chosen primary product. This section overrides the primary nearest-fallback rule for this mode only.
Use the supplied primary product facts and customer confirmation in active_need to identify a concrete complementary function. Example: makeup followed by a makeup remover; waterproof eye makeup requires evidence of suitable removal. This is a functional example, not a fixed brand or product-ID mapping. Search and assess all compatibility using file_search facts; never invent a curated pairing or claim that an inferred category was requested by the customer.
Carry over relevant recipient, sensitivity, allergies and formulation constraints, not irrelevant primary-product attributes. Empty price_intent means unknown additional budget, not unlimited willingness to spend. Do not copy the primary item budget onto the add-on or turn its price into a new cap. Honor any explicit additional or total basket budget using only verified prices in active_need and search evidence; never invent the remaining budget.
Never select the primary product itself, an item already committed to in active_need, or an add-on the customer says they own or do not want. Suggest only one useful in-stock complement, never maximize margin or manufacture a need. If the customer's confirmation is missing or contradicted, or no useful compatible in-stock complement is supported by the searches, return status no_match, product_id null, match_quality nearest and a short Czech reason. Do not force an unrelated, unavailable or incompatible nearest product just to fill the optional offer. This tool does not create an order or prove a completed purchase.
PROMPT,
            'input' => json_encode([
                'language' => 'cs',
                'category' => $category,
                'price_intent' => $priceIntent,
                'active_need' => $query,
                'excluded_product_id' => $excludedProductId,
                'current_product_id' => $currentProductId,
                'addon_for_product_id' => $addonForProductId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'tools' => [$fileSearchTool],
            'tool_choice' => 'required',
            'max_tool_calls' => 3,
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
                                'enum' => $addonForProductId === null ? ['selected'] : ['selected', 'no_match'],
                            ],
                            'product_id' => [
                                'type' => $addonForProductId === null ? 'integer' : ['integer', 'null'],
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
            'max_output_tokens' => 512,
        ];
    }

    /** Ověří skutečné hosted hledání i tehdy, když nedoložilo žádný vhodný doplněk. */
    private function hasHostedSearch(array $response): bool
    {
        foreach ((array) ($response['output'] ?? []) as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'file_search_call') {
                return true;
            }
        }
        return false;
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
