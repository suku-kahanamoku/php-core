<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\OpenTripPlanner;

use App\Modules\Transport\Contracts\{ResourceMappingProvider,ScheduleProvider};
use App\Modules\Transport\Model\TransportException;
use App\Modules\Transport\Protocols\Transmodel\TransmodelProvider;

final class OtpProvider extends TransmodelProvider implements ScheduleProvider, ResourceMappingProvider
{
    public function capabilities(): array
    {
        return array_merge(parent::capabilities(), isset($this->definition->config['source_provider']) ? ['realtime'] : []);
    }

    public function sourceReference(string $operation, array $reference): ?array
    {
        $config = $this->definition->config;
        if (!isset($config['source_provider'], $config['otp_feed_id'])) { return null; }
        $prefix = $config['otp_feed_id'].':';
        if (!str_starts_with($reference['external'], $prefix)) {
            throw new TransportException('not_found', 'Resource is outside the configured feed.', 404);
        }
        return array_replace($reference, ['provider'=>$config['source_provider'], 'external'=>substr($reference['external'], strlen($prefix))]);
    }

    public function fallbackReference(string $operation, array $reference): ?array { return null; }
}
