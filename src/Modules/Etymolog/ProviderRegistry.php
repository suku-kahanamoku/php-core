<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Etymolog\Contracts\NameProvider;

final class ProviderRegistry
{
    /** @param array<string,NameProvider> $providers */
    public function __construct(private readonly array $providers) {}

    public function get(string $provider): NameProvider
    {
        return $this->providers[$provider] ?? throw new SyncException('unsupported_provider');
    }
}
