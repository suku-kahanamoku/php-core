<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

/** Configuration readiness only; no network probes, credentials or imported catalogue reads. */
final class CountryCoverageService
{
    public function __construct(private readonly ProviderRegistry $registry) {}

    public function describe(): array
    {
        $countries = [];
        $providers = [];
        foreach ($this->registry->all() as $provider) {
            $definition = $provider->definition();
            $providers[] = $definition->publicData($provider->capabilities());
            foreach ($definition->coverage as $region) {
                $country = $region['country'];
                $countries[$country] ??= ['state' => $country, 'capabilities' => []];
                if ($provider instanceof \App\Modules\Transport\Contracts\ScheduleProvider || ($definition->config['graph_ready'] ?? true) === false) {
                    continue;
                }
                foreach ($provider->capabilities() as $operation) {
                    if ($operation !== 'enrich_journeys' && $definition->enabled($operation) && $definition->roleFor($operation) === 'primary') {
                        $countries[$country]['capabilities'][$operation] = true;
                    }
                }
            }
        }
        ksort($countries);
        foreach ($countries as &$country) {
            $country['search_available'] = isset($country['capabilities']['places'], $country['capabilities']['journeys']);
            $country['cities_available'] = isset($country['capabilities']['cities']);
            $country['capabilities'] = array_keys($country['capabilities']);
            sort($country['capabilities']);
        }
        unset($country);
        return ['providers' => $providers, 'countries' => array_values($countries)];
    }
}
