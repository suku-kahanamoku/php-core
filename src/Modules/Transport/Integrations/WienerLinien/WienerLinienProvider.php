<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\WienerLinien;

use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\Model\{JourneyQuery, ProviderDefinition, ResourceIdCodec, TransportException, UpstreamResponseMapper};

final class WienerLinienProvider implements ResourceProvider
{
    private const MONITOR_HORIZON_SECONDS = 70 * 60;
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
        if ($operation === 'departures' && isset($input['at'])) {
            $at = JourneyQuery::date($input['at'])->getTimestamp();
            if ($at < time() - 60 || $at > time() + self::MONITOR_HORIZON_SECONDS) {
                throw new TransportException('unsupported_time', 'Wiener Linien monitor only provides departures within the next 70 minutes.', 422);
            }
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

        $matching = array_values(array_filter($monitors, static fn($monitor) => is_array($monitor)
            && (string)($monitor['locationStop']['properties']['attributes']['rbl'] ?? '') === (string)$input['external']));
        if (!$matching) {
            throw new TransportException('invalid_upstream', 'Monitor does not match the requested stop.', 502);
        }
        $stop = $this->stop($matching[0], (string) $input['external']);
        $stop['alerts'] = $this->alerts($data['data']['trafficInfos'] ?? [], $matching[0]['refTrafficInfoNames'] ?? []);
        if ($operation === 'stop') {
            return $stop;
        }

        $departures = [];
        $from = isset($input['at']) ? JourneyQuery::date($input['at'])->getTimestamp() : null;
        foreach ($matching as $monitor) {
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
                    if ($from !== null && strtotime($expected ?? $scheduled) < $from) {
                        continue;
                    }
                    $vehicle = is_array($departure['vehicle'] ?? null) ? $departure['vehicle'] : [];
                    $attributes = array_replace($line, $vehicle);
                    $barrierFree = $this->boolean($attributes['barrierFree'] ?? null);
                    $cooling = $this->boolean($attributes['cooling'] ?? null);
                    $departures[] = [
                        'trip_id' => null,
                        'external_trip_id' => null,
                        'scheduled_departure' => $scheduled,
                        'expected_departure' => $expected,
                        'scheduled_arrival' => null,
                        'expected_arrival' => null,
                        'realtime' => $expected !== null,
                        'cancelled' => null,
                        'headsign' => $this->text($attributes['towards'] ?? null),
                        'line' => [
                            'id' => null,
                            'name' => $this->text($attributes['name'] ?? null),
                            'code' => $this->text($attributes['name'] ?? null),
                            'mode' => $this->mode($attributes['type'] ?? null),
                            'direction' => $this->text($attributes['direction'] ?? null),
                            'external_direction_id' => $this->text($attributes['richtungsId'] ?? null),
                        ],
                        'stop' => $stop,
                        'platform' => $this->text($line['platform'] ?? null) ?? $stop['platform'],
                        'metadata' => [
                            'features' => $cooling === true ? ['AIR_CONDITIONING'] : [],
                            'accessibility' => $barrierFree === true ? 'accessible' : null,
                            'wheelchair_accessible' => $barrierFree,
                            'folding_ramp' => $this->boolean($attributes['foldingRamp'] ?? null),
                            'air_conditioning' => $cooling,
                        ],
                        'realtime_supported' => $this->boolean($attributes['realtimeSupported'] ?? null),
                        'traffic_jam' => $this->boolean($attributes['trafficjam'] ?? null),
                        'at_stop' => $this->boolean($vehicle['onStop'] ?? null),
                        'alerts' => $this->alerts($data['data']['trafficInfos'] ?? [], $monitor['refTrafficInfoNames'] ?? []),
                    ];
                }
            }
        }

        usort($departures, static fn(array $left, array $right): int => strtotime($left['expected_departure'] ?? $left['scheduled_departure']) <=> strtotime($right['expected_departure'] ?? $right['scheduled_departure']));
        return array_slice($departures, 0, JourneyQuery::integer($input['limit'] ?? 20, 1, 50));
    }

    /** @param array<string, mixed> $monitor @return array<string, mixed> */
    private function stop(array $monitor, string $expectedRbl): array
    {
        $location = $monitor['locationStop'] ?? null;
        $properties = is_array($location) ? ($location['properties'] ?? null) : null;
        $coordinates = is_array($location) ? ($location['geometry']['coordinates'] ?? null) : null;
        $rbl = is_array($properties) ? ($properties['attributes']['rbl'] ?? null) : null;
        if (!is_array($properties) || !is_array($coordinates) || count($coordinates) !== 2 || (string) $rbl !== $expectedRbl
            || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])
            || !is_finite((float)$coordinates[0]) || !is_finite((float)$coordinates[1])
            || abs((float)$coordinates[0]) > 180 || abs((float)$coordinates[1]) > 90) {
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
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/D', $value)) {
            return null;
        }
        try {
            $time = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            return $errors && ($errors['warning_count'] || $errors['error_count']) ? null : $time->format(DATE_RFC3339);
        } catch (\Exception) {
            return null;
        }
    }

    private function boolean(mixed $value): ?bool { return is_bool($value) ? $value : null; }
    private function text(mixed $value): ?string { return is_string($value) && trim($value) !== '' ? $value : null; }

    /** Only monitor-linked notices; never expose provider HTML as executable markup. */
    private function alerts(mixed $infos, mixed $references): array
    {
        if (!is_array($infos) || !is_array($references) || !$references) { return []; }
        $alerts = [];
        foreach ($infos as $info) {
            if (!is_array($info) || !in_array($info['name'] ?? null, $references, true)) { continue; }
            $alerts[] = [
                'id' => $this->text($info['name'] ?? null),
                'title' => $this->text($info['title'] ?? null),
                'description' => $this->text($info['description'] ?? null),
                'status' => $this->text($info['status'] ?? null),
                'start' => $this->time($info['time']['start'] ?? null),
                'end' => $this->time($info['time']['end'] ?? null),
                'resume' => $this->time($info['time']['resume'] ?? null),
                'related_lines' => array_values(array_filter(is_array($info['relatedLines'] ?? null) ? $info['relatedLines'] : [], 'is_string')),
            ];
        }
        return $alerts;
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
