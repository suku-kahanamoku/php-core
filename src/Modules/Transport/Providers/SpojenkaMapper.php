<?php

declare(strict_types=1);

namespace App\Modules\Transport\Providers;

use App\Modules\Transport\{ResourceIdCodec, TransportException};
use App\Modules\Transport\DTO\JourneyQuery;

/** Whitelist mapper: scheduled data only; never maps positions or unverified realtime references. */
final class SpojenkaMapper
{
    public const UPSTREAM_MODES = ['train'=>'TRAIN','bus'=>'BUS','tram'=>'TRAM','metro'=>'METRO','trolleybus'=>'TROLLEY','cable_car'=>'ROPEWAY','ferry'=>'FERRY'];

    public function __construct(private readonly string $tenant, private readonly string $provider) {}

    private function invalid(): never { throw new TransportException('invalid_upstream', 'Invalid Spojenka response.', 502); }
    private function text(mixed $value, int $max = 512): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > $max) { $this->invalid(); }
        return $value;
    }
    private function time(mixed $value): string
    {
        try { return JourneyQuery::date($value)->format(DATE_RFC3339); }
        catch (TransportException) { $this->invalid(); }
    }
    private function id(string $kind, string $external, ?string $date = null): string
    {
        return ResourceIdCodec::encode($this->tenant, $this->provider, $kind, $external, $date);
    }

    public function station(array $data): array
    {
        $external = $this->text($data['persistentId'] ?? null);
        $lat = $data['latitude'] ?? null; $lon = $data['longitude'] ?? null;
        if (!(($lat === null && $lon === null) || (is_numeric($lat) && is_numeric($lon) && is_finite((float)$lat) && is_finite((float)$lon) && abs((float)$lat) <= 90 && abs((float)$lon) <= 180))) { $this->invalid(); }
        return ['id'=>$this->id('stop', $external),'name'=>$this->text($data['name'] ?? null),
            'lat'=>$lat === null ? null : (float)$lat,'lon'=>$lon === null ? null : (float)$lon,'platform'=>null,'timezone'=>'Europe/Prague'];
    }

    private function stopReference(array $post): array
    {
        $station = $post['stationRef'] ?? [];
        return ['id'=>$this->id('stop', $this->text($station['id'] ?? null)),
            'name'=>$this->text($station['name'] ?? null),'lat'=>null,'lon'=>null,
            'platform'=>is_string($post['postCode'] ?? null) ? $post['postCode'] : null,'timezone'=>'Europe/Prague'];
    }

    private function leg(array $trip): array
    {
        $connection = $trip['connection'] ?? [];
        $mode = array_search($connection['line']['means'] ?? '', self::UPSTREAM_MODES, true);
        if (($connection['line']['means'] ?? '') === 'ON_FOOT') { $mode = 'walk'; }
        if ($mode === false) { $this->invalid(); }
        $departure = $this->time($trip['departure'] ?? null);
        $arrival = $this->time($trip['arrival'] ?? null);
        if (strtotime($arrival) < strtotime($departure)) { $this->invalid(); }
        $date = (new \DateTimeImmutable($connection['originDeparture'] ?? $departure))->setTimezone(new \DateTimeZone('Europe/Prague'))->format('Y-m-d');
        $external = $mode === 'walk' ? null : $this->text($connection['persistentId'] ?? null);
        $line = $connection['line'] ?? [];
        $code = $line['ids'][0]['localLineCode'] ?? $line['numbers'][0]['number'] ?? '';
        return ['mode'=>$mode,'from'=>$this->stopReference($trip['from']['stopPostRef'] ?? []),
            'to'=>$this->stopReference($trip['to']['stopPostRef'] ?? []),
            'scheduled_departure'=>$departure,'scheduled_arrival'=>$arrival,
            'expected_departure'=>null,'expected_arrival'=>null,'realtime'=>false,'cancelled'=>false,
            'trip_id'=>$external === null ? null : $this->id('trip', $external, $date),'service_date'=>$date,
            'line'=>['id'=>null,'name'=>is_string($line['name'] ?? null) ? $line['name'] : null,'code'=>is_string($code) ? $code : (string)$code,'mode'=>$mode],
            'operator'=>null,'distance_m'=>is_numeric($trip['kmDistance'] ?? null) ? max(0, (float)$trip['kmDistance'] * 1000) : null,'geometry'=>null];
    }

    private function queryPlace(array $place): array
    {
        return ['id'=>$place['id'] ?? null,'name'=>$place['name'] ?? null,
            'lat'=>$place['lat'] ?? null,'lon'=>$place['lon'] ?? null,'platform'=>null,'timezone'=>'Europe/Prague'];
    }

    /** Only access/egress duration is explicit in upstream journey bounds. Transfer waiting is never invented as walking. */
    private function walk(array $from, array $to, string $departure, string $arrival): array
    {
        return ['mode'=>'walk','from'=>$from,'to'=>$to,'scheduled_departure'=>$departure,'scheduled_arrival'=>$arrival,
            'expected_departure'=>null,'expected_arrival'=>null,'realtime'=>false,'cancelled'=>false,
            'trip_id'=>null,'service_date'=>null,'line'=>null,'operator'=>null,'distance_m'=>null,'geometry'=>null];
    }

    public function journeys(array $data, ?JourneyQuery $query = null): array
    {
        $sets = $data['journeySets'] ?? null;
        if (!is_array($sets) || !array_is_list($sets) || count($sets) > 20) { $this->invalid(); }
        $results = [];
        foreach ($sets as $set) {
            if (!is_array($set['journeys'] ?? null) || count($set['journeys']) > 20) { $this->invalid(); }
            foreach ($set['journeys'] as $entry) {
                $journey = $entry['journey'] ?? [];
                if (empty($journey['trips']) || !is_array($journey['trips']) || count($journey['trips']) > 30) { $this->invalid(); }
                $legs = array_map($this->leg(...), $journey['trips']);
                $start = $this->time($journey['departure'] ?? null); $end = $this->time($journey['arrival'] ?? null);
                $duration = strtotime($end) - strtotime($start);
                if ($duration < 0) { $this->invalid(); }
                if ($query !== null) {
                    $first = $legs[0]; $last = $legs[count($legs)-1];
                    if (strtotime($start) > strtotime($first['scheduled_departure']) || strtotime($end) < strtotime($last['scheduled_arrival'])) { $this->invalid(); }
                    if ($start !== $first['scheduled_departure'] && strtotime($start) < strtotime($first['scheduled_departure'])) {
                        array_unshift($legs, $this->walk($this->queryPlace($query->from), $first['from'], $start, $first['scheduled_departure']));
                    }
                    if (strtotime($end) > strtotime($last['scheduled_arrival'])) {
                        $legs[] = $this->walk($last['to'], $this->queryPlace($query->to), $last['scheduled_arrival'], $end);
                    }
                }
                $results[] = ['duration_seconds'=>$duration,'transfers'=>max(0,count(array_filter($legs, static fn ($l)=>$l['mode'] !== 'walk'))-1),'legs'=>$legs];
            }
        }
        return $results;
    }

    public function trip(array $data, string $external, string $date): array
    {
        $connection = $data['connection'] ?? [];
        // The upstream may canonicalize boarding-stop hints in persistent IDs.
        $this->text($connection['persistentId'] ?? null);
        if (!is_array($data['route'] ?? null) || count($data['route']) > 2000) { $this->invalid(); }
        // Connections are tied to a concrete service day, including trips crossing midnight.
        $origin = $this->time($connection['originDeparture'] ?? null);
        if ((new \DateTimeImmutable($origin))->setTimezone(new \DateTimeZone('Europe/Prague'))->format('Y-m-d') !== $date) { $this->invalid(); }
        $stops = [];
        foreach ($data['route'] as $index=>$call) {
            $stops[] = ['stop'=>$this->stopReference($call['stopPostRef'] ?? []),'sequence'=>$index,
                'scheduled_arrival'=>isset($call['arrivalTime']) ? $this->time($call['arrivalTime']) : null,
                'scheduled_departure'=>isset($call['departureTime']) ? $this->time($call['departureTime']) : null,
                'expected_arrival'=>null,'expected_departure'=>null,'realtime'=>false];
        }
        return ['id'=>$this->id('trip',$external,$date),'stops'=>$stops,'geometry'=>null];
    }
}
