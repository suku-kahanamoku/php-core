<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\IdsJmk;
use App\Modules\Transport\Contracts\{IntegrationModule,Provider};
use App\Modules\Transport\Model\{ProviderDefinition,TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

final class IdsJmkModule implements IntegrationModule
{
    public function adapter(): string { return 'idsjmk'; }
    public function validate(ProviderDefinition $definition): void
    {
        foreach (['schedule_url' => 'https://kordis-jmk.cz/gtfs/gtfs.zip', 'realtime_url' => 'https://kordis-jmk.cz/gtfs/gtfsReal.dat',
            'traffic_url' => 'https://www.idsjmk.cz/api/traffic-state/line'] as $key => $url) {
            if (($definition->config[$key] ?? null) !== $url) { throw new TransportException('invalid_configuration', 'Unsupported IDS JMK endpoint.', 500); }
        }
        if (!is_string($definition->config['source_provider'] ?? null) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $definition->config['source_provider'])
            || !is_array($definition->config['cis_line_ranges'] ?? null) || !$definition->config['cis_line_ranges']) {
            throw new TransportException('invalid_configuration', 'IDS JMK source identity mapping required.', 500);
        }
        foreach ($definition->config['cis_line_ranges'] as $range) {
            if (!is_array($range) || !is_int($range['min'] ?? null) || !is_int($range['max'] ?? null) || !is_int($range['offset'] ?? null)
                || $range['min'] < 100000 || $range['max'] > 999999 || $range['max'] < $range['min'] || $range['min'] <= $range['offset'] || $range['max'] - $range['offset'] > 9999) {
                throw new TransportException('invalid_configuration', 'Invalid CIS line mapping range.', 500);
            }
        }
    }
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider
    {
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
        $base = $env['TRANSPORT_STATIC_CACHE_DIR'] ?? sys_get_temp_dir().'/tram-static-'.$uid;
        $directory = rtrim($base, '/').'/'.hash('sha256', $definition->tenant.'|'.$definition->code.'|'.$definition->config['schedule_url']);
        return new IdsJmkProvider($definition, new IdsJmkScheduleService($directory, $definition->config['schedule_url']));
    }
}
