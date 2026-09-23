<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use RuntimeException;

/** Inkrementalni synchronizace publikovanych produktu do tenantoveho Vector Store. */
final class OpenAiVectorStoreSyncService
{
    public function __construct(
        private readonly OpenAiCatalogGateway $catalog,
        private readonly OpenAiVectorStoreGateway $repository,
        private readonly OpenAiVectorStoreClient $client,
        private readonly OpenAiProductDocumentBuilder $documents,
        private readonly string $franchiseCode,
    ) {}

    /**
     * @param callable(string):void|null $progress
     * @return array{vector_store_id:string,total:int,created:int,updated:int,unchanged:int,removed:int}
     */
    public function sync(?callable $progress = null): array
    {
        $store = $this->repository->store();
        if ($store === null) {
            $name          = 'Product catalog - ' . $this->franchiseCode;
            $created       = $this->client->createVectorStore($name);
            $vectorStoreId = trim((string) ($created['id'] ?? ''));
            if ($vectorStoreId === '') {
                throw new OpenAiUpstreamException('OpenAI did not return a vector store ID.');
            }
            $this->repository->saveStore($vectorStoreId, $name);
            if ($progress !== null) {
                $progress("Created vector store {$vectorStoreId}");
            }
        } else {
            $vectorStoreId = (string) $store['vector_store_id'];
        }

        $mappings       = $this->repository->productMappings();
        $seen           = [];
        $createdCount   = 0;
        $updatedCount   = 0;
        $unchangedCount = 0;
        $total          = 0;
        foreach ($this->catalog->publishedProducts() as $product) {
            $productId = (int) ($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $total++;
            $seen[$productId] = true;
            $document         = $this->documents->build($product, $this->franchiseCode);
            $hash             = hash('sha256', $document);
            $old              = $mappings[$productId] ?? null;
            if (is_array($old) && hash_equals((string) $old['source_hash'], $hash)) {
                $unchangedCount++;
                continue;
            }

            $upload = $this->client->uploadJson(
                sprintf('%s-product-%d.json', $this->franchiseCode, $productId),
                $document,
            );
            $fileId = trim((string) ($upload['id'] ?? ''));
            if ($fileId === '') {
                throw new OpenAiUpstreamException('OpenAI did not return a file ID.');
            }
            try {
                $this->client->attachFile($vectorStoreId, $fileId, [
                    'franchise_code' => $this->franchiseCode,
                    'product_id'     => $productId,
                    'sku'            => mb_substr((string) ($product['sku'] ?? ''), 0, 512),
                    'source_hash'    => $hash,
                ]);
                $this->waitUntilIndexed($vectorStoreId, $fileId);
                $this->repository->saveProductMapping($productId, $vectorStoreId, $fileId, $hash);
            } catch (\Throwable $exception) {
                $this->safeDeleteFile($fileId);
                throw $exception;
            }
            if (is_array($old)) {
                $this->safeRemoveRemote($vectorStoreId, (string) $old['openai_file_id']);
                $updatedCount++;
            } else {
                $createdCount++;
            }
            if ($progress !== null) {
                $progress("Indexed product {$productId}");
            }
        }

        $removedCount = 0;
        foreach ($mappings as $productId => $mapping) {
            if (isset($seen[$productId])) {
                continue;
            }
            $this->safeRemoveRemote($vectorStoreId, (string) $mapping['openai_file_id']);
            $this->repository->deleteProductMapping($productId);
            $removedCount++;
        }
        return [
            'vector_store_id' => $vectorStoreId,
            'total'           => $total,
            'created'         => $createdCount,
            'updated'         => $updatedCount,
            'unchanged'       => $unchangedCount,
            'removed'         => $removedCount,
        ];
    }

    /** Ceka na dokonceni asynchronniho chunkovani a embeddingu. */
    private function waitUntilIndexed(string $vectorStoreId, string $fileId): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $file   = $this->client->retrieveFile($vectorStoreId, $fileId);
            $status = (string) ($file['status'] ?? '');
            if ($status === 'completed') {
                return;
            }
            if (in_array($status, ['failed', 'cancelled'], true)) {
                throw new OpenAiUpstreamException("Vector Store indexing ended with status {$status}.");
            }
            usleep(500_000);
        }
        throw new RuntimeException('Timed out while waiting for Vector Store indexing.');
    }

    /** Odpoji stary index a odstrani zdrojovy OpenAI soubor. */
    private function safeRemoveRemote(string $vectorStoreId, string $fileId): void
    {
        if ($fileId === '') {
            return;
        }
        try {
            $this->client->detachFile($vectorStoreId, $fileId);
        } catch (OpenAiUpstreamException) {
        }
        $this->safeDeleteFile($fileId);
    }

    /** Odstraneni souboru je uklid; jeho selhani nesmi zneplatnit novy index. */
    private function safeDeleteFile(string $fileId): void
    {
        try {
            $this->client->deleteFile($fileId);
        } catch (OpenAiUpstreamException) {
        }
    }
}
