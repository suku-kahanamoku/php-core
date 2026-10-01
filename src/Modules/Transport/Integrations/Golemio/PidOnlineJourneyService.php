<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\Golemio;

use App\Modules\Transport\Model\ResourceIdCodec;
use App\Modules\Transport\Model\TransportException;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Transport\Model\JourneyQuery;
use App\Modules\Transport\Import\ServiceTimeService;
use App\Modules\Transport\Model\UpstreamResponseMapper;

/** Bounded PID path composition from Golemio's live stop times and trip details. */
final class PidOnlineJourneyService
{
    private const WINDOW_SECONDS = 14400;
    private const STOP_TIMES_LIMIT = 16;
    private const TRIPS_PER_SIDE = 4;
    private const MIN_TRANSFER_SECONDS = 180;

    public function __construct(private readonly PidProvider $provider)
    {
    }

    /** @return array{journeys: list<array<string, mixed>>, limited: bool} */
    public function search(JourneyQuery $query, HttpClient $http, int $budgetMs): array
    {
        $definition = $this->provider->definition();
        $from = ResourceIdCodec::decode($query->from['id'], $definition->tenant, 'stop');
        $to = ResourceIdCodec::decode($query->to['id'], $definition->tenant, 'stop');
        $anchor = $query->time->setTimezone(new \DateTimeZone('Europe/Prague'));
        $requests = [];
        $requestMeta = [];
        $headers = ['X-Access-Token: '.$this->provider->token()];
        foreach (['origin' => $from['external'], 'destination' => $to['external']] as $side => $stop) {
            foreach ([0,-1] as $dayOffset) {
                $day = $anchor->setTime(0, 0)->modify($dayOffset.' days');
                $date = $day->format('Y-m-d');
                $seconds = $anchor->getTimestamp() - $day->getTimestamp();
                $start = max(0, $seconds - ($query->arriveBy ? self::WINDOW_SECONDS : 0));
                $end = $seconds + ($query->arriveBy ? 0 : self::WINDOW_SECONDS);
                if ($end < 0 || $start > 172799) {
                    continue;
                }
                $key = $side.'|'.$date;
                $url = rtrim($definition->config['url'], '/').'/v2/gtfs/stoptimes/'.rawurlencode($stop).'?'.http_build_query([
                    'date' => $date,'from' => self::clock($start),'to' => self::clock($end),
                    'limit' => self::STOP_TIMES_LIMIT,
                ]);
                $requests[$key] = new HttpRequest($url, headers:$headers, timeoutMs:min(2500, $budgetMs));
                $requestMeta[$key] = [$side,$date,$stop];
            }
        }
        $responses = $http->sendAll($requests, min(2500, $budgetMs));
        $candidateTimes = ['origin' => [], 'destination' => []];
        foreach ($responses as $key => $response) {
            [$side,$date,$stop] = $requestMeta[$key];
            $times = UpstreamResponseMapper::json($response);
            if (!array_is_list($times)) {
                throw new TransportException('invalid_upstream', 'Invalid PID stop times.', 502);
            }
            foreach ($times as $call) {
                if (!is_array($call) || !is_string($call['trip_id'] ?? null)
                    || !is_string($call['stop_id'] ?? null) || !is_numeric($call['stop_sequence'] ?? null)) {
                    throw new TransportException('invalid_upstream', 'Invalid PID stop time.', 502);
                }
                if ($call['stop_id'] !== $stop) {
                    continue;
                }
                $clock = $side === 'origin' ? ($call['departure_time'] ?? null) : ($call['arrival_time'] ?? null);
                $seconds = is_string($clock) ? ServiceTimeService::seconds($clock) : null;
                if ($seconds === null) {
                    continue;
                }
                $instant = ServiceTimeService::instant($date, $seconds, 'Europe/Prague')->getTimestamp();
                if ($instant < $query->time->getTimestamp() - ($query->arriveBy ? self::WINDOW_SECONDS : 0)
                    || $instant > $query->time->getTimestamp() + ($query->arriveBy ? 0 : self::WINDOW_SECONDS)) {
                    continue;
                }
                $candidateTimes[$side][$date.'|'.$call['trip_id']] = $instant;
            }
        }
        $tripRequests = [];
        foreach ($candidateTimes as $side => $times) {
            if ($query->arriveBy) {
                arsort($times, SORT_NUMERIC);
            } else {
                asort($times, SORT_NUMERIC);
            }
            $candidateTimes[$side] = $times;
            foreach (array_slice(array_keys($times), 0, self::TRIPS_PER_SIDE) as $key) {
                [$date,$trip] = explode('|', $key, 2);
                $tripRequests[$key] ??= $this->provider->resourceRequest('trip', ['external' => $trip,'date' => $date]);
            }
        }
        $trips = [];
        if ($tripRequests) {
            foreach ($http->sendAll($tripRequests, max(1, $budgetMs - 2500)) as $key => $response) {
                [$date,$trip] = explode('|', $key, 2);
                $trips[$key] = $this->provider->resourceResult('trip', $response, ['external' => $trip,'date' => $date]);
            }
        }
        $journeys = [];
        foreach (array_slice(array_keys($candidateTimes['origin']), 0, self::TRIPS_PER_SIDE) as $originKey) {
            if (!isset($trips[$originKey]) || !in_array($trips[$originKey]['line']['mode'] ?? null, $query->modes, true)) {
                continue;
            }
            $originTrip = $trips[$originKey];
            foreach ($originTrip['stops'] as $boardIndex => $board) {
                if (self::stopId($board, $definition->tenant) !== $from['external']) {
                    continue;
                }
                foreach (array_slice($originTrip['stops'], $boardIndex + 1, null, true) as $exitIndex => $exit) {
                    if (self::stopId($exit, $definition->tenant) === $to['external']) {
                        $this->add($journeys, $query, [$this->leg($originTrip, $board, $exit, $query)]);
                    }
                    if ($query->maxTransfers < 1 || count($journeys) >= 80 || self::stopId($exit, $definition->tenant) === $to['external']) {
                        continue;
                    }
                    foreach (array_slice(array_keys($candidateTimes['destination']), 0, self::TRIPS_PER_SIDE) as $destinationKey) {
                        if (!isset($trips[$destinationKey]) || $destinationKey === $originKey
                            || !in_array($trips[$destinationKey]['line']['mode'] ?? null, $query->modes, true)) {
                            continue;
                        }
                        $destinationTrip = $trips[$destinationKey];
                        foreach ($destinationTrip['stops'] as $transferIndex => $transfer) {
                            if (self::stopId($transfer, $definition->tenant) !== self::stopId($exit, $definition->tenant)) {
                                continue;
                            }
                            $firstArrival = strtotime($exit['scheduled_arrival'] ?? '');
                            $secondDeparture = strtotime($transfer['scheduled_departure'] ?? '');
                            if (!$firstArrival || !$secondDeparture || $secondDeparture - $firstArrival < self::MIN_TRANSFER_SECONDS) {
                                continue;
                            }
                            foreach (array_slice($destinationTrip['stops'], $transferIndex + 1, null, true) as $destination) {
                                if (self::stopId($destination, $definition->tenant) === $to['external']) {
                                    $this->add($journeys, $query, [
                                        $this->leg($originTrip, $board, $exit, $query),
                                        $this->leg($destinationTrip, $transfer, $destination, $query),
                                    ]);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        return ['journeys' => array_slice($journeys, 0, 80),'limited' => true];
    }

    /** @param list<array<string,mixed>> $journeys @param list<array<string,mixed>> $legs */
    private function add(array &$journeys, JourneyQuery $query, array $legs): void
    {
        $firstTime = $legs[0]['scheduled_departure'] ?? null;
        $lastTime = $legs[count($legs) - 1]['scheduled_arrival'] ?? null;
        if (!$firstTime || !$lastTime) {
            return;
        }
        $departure = strtotime($firstTime);
        $arrival = strtotime($lastTime);
        if (!$departure || !$arrival || $arrival <= $departure || (!$query->arriveBy && $departure < $query->time->getTimestamp())
            || ($query->arriveBy && $arrival > $query->time->getTimestamp())) {
            return;
        }
        $journeys[] = ['duration_seconds' => $arrival - $departure,'transfers' => count($legs) - 1,'legs' => $legs];
    }

    /** @param array<string,mixed> $trip @param array<string,mixed> $from @param array<string,mixed> $to */
    private function leg(array $trip, array $from, array $to, JourneyQuery $query): array
    {
        $origin = $from['stop'];
        $destination = $to['stop'];
        if ($origin['id'] === $query->from['id']) {
            $origin['lat'] ??= $query->from['lat'];
            $origin['lon'] ??= $query->from['lon'];
        }
        if ($destination['id'] === $query->to['id']) {
            $destination['lat'] ??= $query->to['lat'];
            $destination['lon'] ??= $query->to['lon'];
        }
        return ['min_transfer_seconds' => self::MIN_TRANSFER_SECONDS, 'mode' => $trip['line']['mode'],'from' => $origin,'to' => $destination,
            'scheduled_departure' => $from['scheduled_departure'],'scheduled_arrival' => $to['scheduled_arrival'],
            'expected_departure' => null,'expected_arrival' => null,'realtime' => false,'cancelled' => null,
            'trip_id' => $trip['id'],'service_date' => $trip['service_date'],'line' => $trip['line'],
            'operator' => null,'distance_m' => null,'geometry' => null];
    }

    /** @param array<string,mixed> $call */
    private static function stopId(array $call, string $tenant): string
    {
        return ResourceIdCodec::decode($call['stop']['id'], $tenant, 'stop')['external'];
    }

    private static function clock(int $seconds): string
    {
        return intdiv($seconds, 3600).':'.sprintf('%02d:%02d', intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
