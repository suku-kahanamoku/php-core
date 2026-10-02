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
            $this->geocoderUrl($definition->config['url'], $definition->config['geocoder_url']);
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
            if (isset($config['geocoder_url'])) {
                $config['geocoder_url'] = $this->geocoderUrl($config['url'], $config['geocoder_url']);
            }
            $definition = $definition->withConfig($config);
        }
        return new OtpProvider($definition);
    }

    /** Geocoder must belong to the same immutable OTP instance as Transmodel. */
    private function geocoderUrl(string $graphUrl, string $configuredUrl): string
    {
        $path = parse_url($graphUrl, PHP_URL_PATH) ?? '';
        if (!str_ends_with($path, '/transmodel/v3') || parse_url($graphUrl, PHP_URL_QUERY) !== null || parse_url($graphUrl, PHP_URL_FRAGMENT) !== null) {
            throw new TransportException('invalid_configuration', 'OTP geocoder requires a graph URL ending in /transmodel/v3.');
        }
        $configuredPath = parse_url($configuredUrl, PHP_URL_PATH) ?? '';
        if (!str_ends_with($configuredPath, '/geocode') || parse_url($configuredUrl, PHP_URL_QUERY) !== null || parse_url($configuredUrl, PHP_URL_FRAGMENT) !== null) {
            throw new TransportException('invalid_configuration', 'OTP geocoder URL must end in /geocode.');
        }
        return substr($graphUrl, 0, -strlen('/transmodel/v3')) . '/geocode';
    }
}
