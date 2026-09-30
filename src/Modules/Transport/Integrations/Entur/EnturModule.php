<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\Entur;

use App\Modules\Transport\Contracts\{IntegrationModule,Provider};
use App\Modules\Transport\Core\ConfigurationService;
use App\Modules\Transport\Model\{ProviderDefinition,TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

final class EnturModule implements IntegrationModule
{
    public function adapter(): string { return 'entur'; }
    public function validate(ProviderDefinition $definition): void
    {
        ConfigurationService::url($definition->config['url'] ?? '');
        if (isset($definition->config['geocoder_url'])) { ConfigurationService::url($definition->config['geocoder_url']); }
        if (isset($definition->config['geocoder_url']) && empty($definition->config['client_name'])) {
            throw new TransportException('invalid_configuration', 'Entur geocoding needs a client_name.');
        }
    }
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        return new EnturProvider($definition);
    }
}
