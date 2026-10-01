<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\WienerLinien;

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\Model\{ProviderDefinition, ResourceIdCodec, TransportException, UpstreamResponseMapper};

final class WienerLinienProvider implements ResourceProvider
{
    public function __construct(private readonly ProviderDefinition $definition) {}

    public function definition(): ProviderDefinition
    {
        return $this->definition;
    }

    public function capabilities(): array
    {
        return ['stop', 'departures'];
    }

    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        if (!in_array($operation, $this->capabilities(), true)) {
            throw new TransportException('unsupported_capability', 'Wiener Linien does not support this operation.', 422);
        }

        $stopId = (string) ($input['external'] ?? '');
        if (preg_match('/^[0-9]{1,10}$/D', $stopId) !== 1) {
            throw new TransportException('invalid_id', 'Wiener Linien stop ID must be an RBL number.', 422);
        }

        return new HttpRequest(
            rtrim((string) $this->definition->config['url'], '/') . '/monitor?' . http_build_query([
                'stopId' => $stopId,
                'activateTrafficInfo' => 'stoerungkurz',
            ]),
            headers: ['Accept' => 'application/json'],
            timeoutMs: 4000,
            connectTimeoutMs: 1500,
        );
    }

    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        $data = UpstreamResponseMapper::json($result);
        $monitors = $data['data']['monitors'] ?? null;
        if (!is_array($monitors) || $monitors === []) {
            throw new TransportException('not_found', 'Wiener Linien stop was not found.', 404);
        }

        $stop = $this->stop($monitors[0], (string) $input['external']);
        if ($operation === 'stop') {
            return $stop;
        }

        $departures = [];
        foreach ($monitors as $monitor) {
            if (!is_array($monitor)) {
                continue;
            }
            foreach ($monitor['lines'] ?? [] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                foreach ($line['departures']['departure'] ?? [] as $departure) {
                    if (!is_array($departure) || !is_array($departure['departureTime'] ?? null)) {
                        continue;
                    }
                    $times = $departure['departureTime'];
                    $scheduled = $this->time($times['timePlanned'] ?? null);
                    if ($scheduled === null) {
                        continue;
                    }
                    $expected = $this->time($times['timeReal'] ?? null);
                    $departures[] = [
                        'trip_id' => null,
                        'external_trip_id' => null,
                        'scheduled_departure' => $scheduled,
                        'expected_departure' => $expected,
                        'scheduled_arrival' => null,
                        'expected_arrival' => null,
                        'realtime' => $expected !== null,
                        'cancelled' => null,
                        'headsign' => is_string($line['towards'] ?? null) ? $line['towards'] : null,
                        'line' => [
                            'id' => null,
                            'name' => is_string($line['name'] ?? null) ? $line['name'] : null,
                            'code' => is_string($line['name'] ?? null) ? $line['name'] : null,
                            'mode' => $this->mode($line['type'] ?? null),
                        ],
                        'stop' => $stop,
                    ];
                }
            }
        }

        usort($departures, static fn(array $left, array $right): int => $left['scheduled_departure'] <=> $right['scheduled_departure']);
        return $departures;
    }

    /** @param array<string, mixed> $monitor @return array<string, mixed> */
    private function stop(array $monitor, string $expectedRbl): array
    {
        $location = $monitor['locationStop'] ?? null;
        $properties = is_array($location) ? ($location['properties'] ?? null) : null;
        $coordinates = is_array($location) ? ($location['geometry']['coordinates'] ?? null) : null;
        $rbl = is_array($properties) ? ($properties['attributes']['rbl'] ?? null) : null;
        if (!is_array($properties) || !is_array($coordinates) || count($coordinates) !== 2 || (string) $rbl !== $expectedRbl) {
            throw new TransportException('invalid_upstream', 'Invalid Wiener Linien monitor response.', 502);
        }

        return [
            'id' => ResourceIdCodec::encode($this->definition->tenant, $this->definition->code, 'stop', $expectedRbl),
            'name' => is_string($properties['title'] ?? null) ? $properties['title'] : null,
            'city' => is_string($properties['municipality'] ?? null) ? $properties['municipality'] : null,
            'lat' => is_numeric($coordinates[1]) ? (float) $coordinates[1] : null,
            'lon' => is_numeric($coordinates[0]) ? (float) $coordinates[0] : null,
            'platform' => is_string($properties['gate'] ?? null) ? $properties['gate'] : null,
            'timezone' => 'Europe/Vienna',
        ];
    }

    private function time(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format(DATE_RFC3339);
        } catch (\Exception) {
            return null;
        }
    }

    private function mode(mixed $value): ?string
    {
        return match ($value) {
            'ptTram' => 'tram',
            'ptMetro' => 'metro',
            'ptBus', 'ptBusNight' => 'bus',
            default => null,
        };
    }
}
