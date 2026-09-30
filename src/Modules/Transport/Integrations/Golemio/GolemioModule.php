<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\Golemio;

use App\Modules\Transport\Contracts\{IntegrationModule,Provider};
use App\Modules\Transport\Core\ConfigurationService;
use App\Modules\Transport\Model\{ProviderDefinition,TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

final class GolemioModule implements IntegrationModule
{
    public function adapter(): string { return 'pid'; }
    public function validate(ProviderDefinition $definition): void
    {
        if (isset($definition->config['schedule_provider']) && empty($definition->config['otp_feed_id'])) {
            throw new TransportException('invalid_configuration', 'Schedule mapping requires an explicit feed namespace.');
        }
        ConfigurationService::url($definition->config['url'] ?? '');
        if (rtrim($definition->config['url'], '/') !== 'https://api.golemio.cz') {
            throw new TransportException('invalid_configuration', 'Unsupported Golemio endpoint.');
        }
    }
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        $token = (string)($env[$definition->config['token_env'] ?? 'TRANSPORT_PID_TOKEN'] ?? '');
        $config = $definition->config;
        $quota = $config['quota'] ?? [];
        $quota += ['limit'=>20,'window_ms'=>8000];
        // Same credential shares admission across tenants, without storing the credential.
        if ($token !== '') { $quota += ['scope'=>'golemio-'.hash('sha256', $token)]; }
        $config['quota'] = $quota;
        return new PidProvider($definition->withConfig($config), $token);
    }
}
