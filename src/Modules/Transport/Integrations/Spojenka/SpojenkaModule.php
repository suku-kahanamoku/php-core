<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\Spojenka;

use App\Modules\Transport\Contracts\{IntegrationModule,Provider};
use App\Modules\Transport\Core\ConfigurationService;
use App\Modules\Transport\Model\{ProviderDefinition,TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

final class SpojenkaModule implements IntegrationModule
{
    public function adapter(): string { return 'spojenka'; }
    public function validate(ProviderDefinition $definition): void
    {
        ConfigurationService::url($definition->config['url'] ?? '');
        if (rtrim($definition->config['url'], '/') !== 'https://spojenka.d3s.mff.cuni.cz/api') {
            throw new TransportException('invalid_configuration', 'Unsupported Spojenka endpoint.');
        }
    }
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        $config = $definition->config;
        // Public endpoint shares one admission pool across tenants; detail enrichment must not burst dozens of calls.
        $quota = $config['quota'] ?? [];
        $quota += ['scope' => 'spojenka-public', 'limit' => 8, 'window_ms' => 1000];
        $config['quota'] = $quota;
        return new SpojenkaProvider($definition->withConfig($config));
    }
}
