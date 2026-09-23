<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Čte produktové dokumenty z OpenAI Vector Store bez lokálního rozhodování. */
final class OpenAiVectorProductRetrieval implements OpenAiProductRetrieval
{
    public function __construct(
        private readonly OpenAiVectorStoreClient $client,
        private readonly OpenAiVectorStoreGateway $repository,
        private readonly string $franchiseCode,
    ) {}

    /** @inheritDoc */
    public function retrieve(string $query, int $limit): array
    {
        $store = $this->repository->store();
        $vectorStoreId = trim((string) ($store['vector_store_id'] ?? ''));
        if ($vectorStoreId === '' || trim($query) === '') {
            return ['status' => 'unavailable', 'products' => []];
        }
        try {
            $products = $this->client->searchProductDocuments(
                $vectorStoreId,
                $this->franchiseCode,
                $query,
                $limit,
            );
        } catch (OpenAiConfigurationException | OpenAiUpstreamException) {
            return ['status' => 'unavailable', 'products' => []];
        }
        return [
            'status' => $products === [] ? 'no_match' : 'results',
            'products' => $products,
        ];
    }
}
