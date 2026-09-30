<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Odděluje stav a synchronizaci Vector Store od databázového úložiště.
 *
 * Kontrakt popisuje jen perzistenci jednoho okurku; načítání katalogu i
 * vlastní síťové volání do OpenAI řeší implementace a provider.
 */
interface OpenAiVectorStoreGateway
{
    /**
     * @return array<string, mixed>|null Uložený stav Vector Store okurku, nebo null.
     */
    public function store(): ?array;

    /**
     * @param  string $vectorStoreId ID Vector Store v OpenAI.
     * @param  string $name          Název pro přehlednost.
     * @return void                  Uloží nebo aktualizuje stav.
     */
    public function saveStore(string $vectorStoreId, string $name): void;

    /**
     * @return array<int, array<string, mixed>> Mapování indexované podle `product_id`.
     */
    public function productMappings(): array;

    /**
     * @param  int    $productId     ID produktu okurku.
     * @param  string $vectorStoreId ID Vector Store.
     * @param  string $fileId        ID souboru v OpenAI.
     * @param  string $sourceHash    Hash zdrojového dokumentu.
     * @return void                  Uloží nebo aktualizuje mapování.
     */
    public function saveProductMapping(
        int $productId,
        string $vectorStoreId,
        string $fileId,
        string $sourceHash,
    ): void;

    /**
     * @param  int $productId ID produktu okurku.
     * @return void           Odebere mapování produktu.
     */
    public function deleteProductMapping(int $productId): void;
}
