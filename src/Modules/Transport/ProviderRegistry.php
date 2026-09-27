<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Transport\Contracts\Provider;
use App\Modules\Transport\DTO\ProviderDefinition;
use App\Modules\Transport\Providers\{TransmodelProvider,PidProvider};

final class ProviderRegistry
{
    /** @var array<string,Provider> */
    private array $providers = [];
    /** @param iterable<Provider> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $p) {
            $code = $p->definition()->code;
            if (isset($this->providers[$code])) {
                throw new \LogicException('Duplicate provider.');
            } $this->providers[$code] = $p;
        }
    }
    public static function build(array $rows, string $tenant, array $env): self
    {
        $providers = [];
        foreach ($rows as $row) {
            $definition = new ProviderDefinition($tenant, $row['code'], $row['adapter'], json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR), json_decode($row['coverage'], true, 32, JSON_THROW_ON_ERROR), $row['role'], json_decode($row['fallback_for'], true, 32, JSON_THROW_ON_ERROR));
            $providers[] = match($definition->adapter) {
                'entur','otp_transmodel' => new TransmodelProvider($definition),
                'pid' => new PidProvider($definition, (string)($env[$definition->config['token_env'] ?? 'TRANSPORT_PID_TOKEN'] ?? '')),
                default => throw new TransportException('invalid_adapter', 'Unknown transport adapter.', 500)
            };
        }
        return new self($providers);
    }
    public function all(): array
    {
        return $this->providers;
    }
    public function get(string $code): Provider
    {
        return $this->providers[$code] ?? throw new TransportException('not_found', 'Provider not available for this tenant.', 404);
    }
}
