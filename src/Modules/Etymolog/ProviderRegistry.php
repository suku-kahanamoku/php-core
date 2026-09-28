<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Etymolog\Contracts\BatchProvider;

final class ProviderRegistry
{
    /** @param array<string,BatchProvider> $providers */
    public function __construct(private readonly array $providers) {}

    public function get(string $provider): BatchProvider
    {
        return $this->providers[$provider] ?? throw new SyncException('unsupported_provider');
    }
}
