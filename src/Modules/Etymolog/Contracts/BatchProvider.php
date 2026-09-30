<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Contracts;

/**
 * Rozhraní dávkového zdroje synchronizace: poskytovatel stáhne jednu ohraničenou
 * dávku položek a vrátí kurzor pro pokračování.
 *
 * Poskytovatel vlastní svou HTTP komunikaci, normalizaci dat i licenci; věci
 * společné pro všechny zdroje řeší `ProviderHttp`.
 */
interface BatchProvider
{
    /**
     * Stáhne jednu dávku položek podle kurzoru.
     *
     * @param  string     $language Jazyk zdroje podle konfigurace úlohy.
     * @param  string     $kind     Druh požadavku (např. 'given', 'surname', 'etymologies').
     * @param  string|null $cursor  Kurzor z předchozí dávky, nebo null pro začátek.
     * @param  int        $limit    Maximální počet položek v dávce.
     * @return array{items:list<array<string, mixed>>, cursor:?string, complete:bool} Dávka, nový kurzor a příznak dokončení.
     * @throws \App\Modules\Etymolog\SyncException Při chybě upstreamu nebo zaseknutém kurzoru.
     */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array;
}
