<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\OpenTripPlanner;

use App\Modules\Transport\Contracts\{IntegrationModule, Provider};
use App\Modules\Transport\Core\ConfigurationService;
use App\Modules\Transport\Model\{ProviderDefinition, TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

final class OpenTripPlannerModule implements IntegrationModule
{
    public function adapter(): string
    {
        return 'otp_transmodel';
    }
    public function validate(ProviderDefinition $definition): void
    {
        if (isset($definition->config['source_provider']) && empty($definition->config['otp_feed_id'])) {
            throw new TransportException('invalid_configuration', 'Source mapping requires an explicit feed namespace.');
        }
        ConfigurationService::url($definition->config['url'] ?? '', true);
        if (empty($definition->config['feed_code'])) {
            throw new TransportException('invalid_configuration', 'Local planners must reference an imported feed.');
        }
        if (isset($definition->config['geocoder_url'])) {
            ConfigurationService::url($definition->config['geocoder_url'], true);
        }
    }
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        if ($repository !== null) {
            $version = $repository->activeFeed($definition->config['feed_code']);
            $config = array_replace($definition->config, [
                'url' => $version['graph_url'] ?? $definition->config['url'],
                'graph_ready' => $version !== null && !empty($version['graph_url']),
                'valid_from' => $version['valid_from'] ?? null,
                'valid_until' => $version['valid_until'] ?? null,
                'graph_version' => $version['id'] ?? null,
                'snapshot_at' => isset($version['snapshot_at']) ? str_replace(' ', 'T', $version['snapshot_at']) . 'Z' : null,
            ]);
            $definition = $definition->withConfig($config);
        }
        return new OtpProvider($definition);
    }
}
