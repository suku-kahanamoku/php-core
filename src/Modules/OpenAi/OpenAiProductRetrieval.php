<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/** Zprostředkuje syrové produktové dokumenty z tenantového znalostního indexu. */
interface OpenAiProductRetrieval
{
    /**
     * @return array{status:string,products:list<array<string, mixed>>}
     */
    public function retrieve(string $query, int $limit): array;
}
