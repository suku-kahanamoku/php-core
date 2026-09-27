<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTO;

use App\Modules\Transport\TransportException;

final class ProviderDefinition
{
    public function __construct(
        public readonly string $tenant,
        public readonly string $code,
        public readonly string $adapter,
        public readonly array $config,
        public readonly array $coverage,
        public readonly string $role = 'primary',
        public readonly array $fallbackFor = []
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $code) || !in_array($role, ['primary','fallback'], true)) {
            throw new TransportException('invalid_provider', 'Invalid provider configuration.', 500);
        }
    }
    public function covers(JourneyQuery $query): bool
    {
        // A route needs a provider covering BOTH endpoints. Country/city are hints, never a hard route boundary.
        foreach ([$query->from,$query->to] as $point) {
            $found = false;
            foreach ($this->coverage as $region) {
                $box = $region['bbox'] ?? null;
                if ($box && isset($point['lat'],$point['lon'])) {
                    [$west,$south,$east,$north] = $box;
                    $lon = $point['lon'];
                    if ($point['lat'] >= $south && $point['lat'] <= $north && ($west <= $east ? $lon >= $west && $lon <= $east : $lon >= $west || $lon <= $east)) {
                        $found = true;
                    }
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }
    public function publicData(array $capabilities): array
    {
        return ['id' => $this->code,'capabilities' => $capabilities,'coverage' => $this->coverage,'role' => $this->role, 'schedule_ready' => $this->config['graph_ready'] ?? null,'attribution' => $this->config['attribution'] ?? null];
    }
}
