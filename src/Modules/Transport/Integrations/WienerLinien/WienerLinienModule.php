<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\WienerLinien;

use App\Modules\Transport\Contracts\{IntegrationModule, Provider};
use App\Modules\Transport\Core\ConfigurationService;
use App\Modules\Transport\Model\{ProviderDefinition, TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

final class WienerLinienModule implements IntegrationModule
{
    public function adapter(): string
    {
        return 'wiener_linien';
    }

    public function validate(ProviderDefinition $definition): void
    {
        $url = rtrim((string) ($definition->config['url'] ?? ''), '/');
        ConfigurationService::url($url);
        if ($url !== 'https://www.wienerlinien.at/ogd_realtime') {
            throw new TransportException('invalid_configuration', 'Unsupported Wiener Linien endpoint.');
        }
    }

    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        return new WienerLinienProvider($definition);
    }
}
