<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use App\Modules\Database\Database;

/** Perzistentni stav tenantove synchronizace s OpenAI Vector Store. */
final class OpenAiVectorStoreRepository implements OpenAiVectorStoreGateway
{
    public function __construct(
        private readonly Database $database,
        private readonly string $franchiseCode,
    ) {}

    /** @return array<string, mixed>|null */
    public function store(): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT franchise_code, vector_store_id, name FROM openai_vector_store WHERE franchise_code = ?',
            [$this->franchiseCode],
        );
        return $row === false ? null : $row;
    }

    public function saveStore(string $vectorStoreId, string $name): void
    {
        $this->database->query(
            'INSERT INTO openai_vector_store (franchise_code, vector_store_id, name)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE vector_store_id = VALUES(vector_store_id), name = VALUES(name)',
            [$this->franchiseCode, $vectorStoreId, $name],
        );
    }

    /** @return array<int, array<string, mixed>> Mapovani indexovane product_id. */
    public function productMappings(): array
    {
        $result = [];
        foreach (
            $this->database->fetchAll(
                'SELECT product_id, vector_store_id, openai_file_id, source_hash
             FROM openai_vector_store_product WHERE franchise_code = ?',
                [$this->franchiseCode],
            ) as $row
        ) {
            $result[(int) $row['product_id']] = $row;
        }
        return $result;
    }

    public function saveProductMapping(
        int $productId,
        string $vectorStoreId,
        string $fileId,
        string $sourceHash,
    ): void {
        $this->database->query(
            'INSERT INTO openai_vector_store_product
                (franchise_code, product_id, vector_store_id, openai_file_id, source_hash)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE vector_store_id = VALUES(vector_store_id),
                 openai_file_id = VALUES(openai_file_id), source_hash = VALUES(source_hash)',
            [$this->franchiseCode, $productId, $vectorStoreId, $fileId, $sourceHash],
        );
    }

    public function deleteProductMapping(int $productId): void
    {
        $this->database->query(
            'DELETE FROM openai_vector_store_product WHERE franchise_code = ? AND product_id = ?',
            [$this->franchiseCode, $productId],
        );
    }
}
