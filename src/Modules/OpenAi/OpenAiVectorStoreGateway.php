<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Oddeluje stav a synchronizaci Vector Store od databazoveho uloziste. */
interface OpenAiVectorStoreGateway
{
    /** @return array<string, mixed>|null */
    public function store(): ?array;

    public function saveStore(string $vectorStoreId, string $name): void;

    /** @return array<int, array<string, mixed>> */
    public function productMappings(): array;

    public function saveProductMapping(
        int $productId,
        string $vectorStoreId,
        string $fileId,
        string $sourceHash,
    ): void;

    public function deleteProductMapping(int $productId): void;
}
