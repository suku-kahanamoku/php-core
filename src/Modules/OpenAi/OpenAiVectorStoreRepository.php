<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use App\Modules\Database\Database;

/**
 * Persistentní stav synchronizace jednoho okurku s OpenAI Vector Store.
 *
 * Všechny dotazy jsou omezené na `franchise_code`, takže se stav jednoho okurku
 * nesmí promítnout do jiného. Zápisy jsou idempotentní (`ON DUPLICATE KEY UPDATE`),
 * aby opakovaná synchronizace nevytvářela duplicity.
 */
final class OpenAiVectorStoreRepository implements OpenAiVectorStoreGateway
{
    /**
     * @param  Database $database      Databázová vrstva (PDO zůstává uvnitř).
     * @param  string   $franchiseCode Kód okurku, jehož stav se načítá a zapisuje.
     * @return void
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $franchiseCode,
    ) {}

    public function acquireSyncLock(): bool
    {
        $row = $this->database->fetchOne(
            'SELECT GET_LOCK(?, 0) AS acquired',
            ['openai_vector_store_sync:' . $this->franchiseCode],
        );
        return (int) ($row['acquired'] ?? 0) === 1;
    }

    public function releaseSyncLock(): void
    {
        $this->database->fetchOne(
            'SELECT RELEASE_LOCK(?) AS released',
            ['openai_vector_store_sync:' . $this->franchiseCode],
        );
    }

    /** @return array<string, mixed>|null */
    public function store(): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT franchise_code, vector_store_id, name FROM openai_vector_store WHERE franchise_code = ?',
            [$this->franchiseCode],
        );
        return $row === false ? null : $row;
    }

    /**
     * Uloží nebo aktualizuje Vector Store daného okurku.
     *
     * @param  string $vectorStoreId ID Vector Store v OpenAI.
     * @param  string $name          Název pro přehlednost.
     * @return void                  Vedlejší efekt: `INSERT ... ON DUPLICATE KEY UPDATE`.
     */
    public function saveStore(string $vectorStoreId, string $name): void
    {
        $this->database->query(
            'INSERT INTO openai_vector_store (franchise_code, vector_store_id, name)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE vector_store_id = VALUES(vector_store_id), name = VALUES(name)',
            [$this->franchiseCode, $vectorStoreId, $name],
        );
    }

    /**
     * Načte mapování indexovaných produktů na soubory ve Vector Store.
     *
     * @return array<int, array<string, mixed>> Řádky indexované podle `product_id`.
     */
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

    /**
     * Uloží nebo aktualizuje mapování produktu na soubor ve Vector Store.
     *
     * @param  int    $productId     ID produktu okurku.
     * @param  string $vectorStoreId ID Vector Store.
     * @param  string $fileId        ID nahraného souboru v OpenAI.
     * @param  string $sourceHash    Hash zdrojového dokumentu pro rozpoznání změn.
     * @return void                  Vedlejší efekt: `INSERT ... ON DUPLICATE KEY UPDATE`.
     */
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

    /**
     * Odebere mapování produktu (produkt už v okurku není nebo se nepublikuje).
     *
     * @param  int $productId ID produktu okurku.
     * @return void           Vedlejší efekt: `DELETE openai_vector_store_product`.
     */
    public function deleteProductMapping(int $productId): void
    {
        $this->database->query(
            'DELETE FROM openai_vector_store_product WHERE franchise_code = ? AND product_id = ?',
            [$this->franchiseCode, $productId],
        );
    }
}
