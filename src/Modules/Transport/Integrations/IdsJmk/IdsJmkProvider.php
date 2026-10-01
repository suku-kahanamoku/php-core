<?php
declare(strict_types=1);
namespace App\Modules\Transport\Integrations\IdsJmk;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Transport\Contracts\{OnlineResourceProvider,RealtimeReferenceProvider};
use App\Modules\Transport\Model\{ProviderDefinition,TransportException,UpstreamResponseMapper};
use App\Modules\Transport\Protocols\GtfsRealtime\GtfsRealtimeReader;

/** KORDIS public live feeds, linked to CIS run identities through the official api.txt index. */
final class IdsJmkProvider implements OnlineResourceProvider, RealtimeReferenceProvider
{
    public function __construct(private readonly ProviderDefinition $definition, private readonly IdsJmkScheduleService $schedule) {}
    public function definition(): ProviderDefinition { return $this->definition; }
    public function capabilities(): array { return ['realtime']; }
    public function realtimeReference(array $reference): ?array
    {
        if ($reference['provider'] !== $this->definition->config['source_provider'] || $this->parse($reference) === null) { return null; }
        return array_replace($reference, ['provider' => $this->definition->code]);
    }
    private function parse(array $reference): ?array
    {
        if (!preg_match('/^L:CISJR:(\d{6}),CN:CISJR:(\d{1,6}),SD:(\d{4}-\d{2}-\d{2}),FS:crz:\d+,FSI:(\d{1,4}),FT:(\d{2}:[0-5]\d:[0-5]\d),TS:crz:\d+,TSI:(\d{1,4}),TT:(\d{2}:[0-5]\d:[0-5]\d)$/D', $reference['external'], $m)
            || ($reference['date'] ?? null) !== $m[3] || (int)$m[4] > (int)$m[6]) { return null; }
        foreach ($this->definition->config['cis_line_ranges'] as $range) {
            if ((int)$m[1] >= $range['min'] && (int)$m[1] <= $range['max']) {
                return ['line' => (int)$m[1] - $range['offset'], 'number' => (int)$m[2], 'date' => $m[3],
                    'from_index' => (int)$m[4], 'from_time' => $m[5], 'to_index' => (int)$m[6], 'to_time' => $m[7]];
            }
        }
        return null;
    }
    public function resourceOnline(string $operation, array $input, HttpClient $http): array
    {
        if ($operation !== 'realtime' || ($ref = $this->parse($input)) === null) {
            throw new TransportException('unsupported_capability', 'No verified IDS JMK identity mapping.', 422);
        }
        $empty = ['realtime' => false, 'position' => null, 'observed_at' => null, 'delay_seconds' => null];
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague')))->format('Y-m-d');
        // The current KORDIS feed omits start_date, so other operating days cannot be proven.
        if ($ref['date'] !== $today) { return $empty; }
        $trip = $this->schedule->resolve($ref, $http);
        if (!$trip || time() < $trip['start'] - 300 || time() > $trip['end'] + 7200) { return $empty; }
        $responses = $http->sendAll([
            'vehicles' => new HttpRequest($this->definition->config['realtime_url'], headers: ['Accept' => '*/*'], maxBytes: 8000000),
            'traffic' => new HttpRequest($this->definition->config['traffic_url'].'/'.$ref['line'], headers: ['Accept' => 'application/json'], maxBytes: 2000000),
        ]);
        if (!$responses['vehicles']->successful()) { throw new TransportException('source_unavailable', 'IDS JMK live feed unavailable.', 503); }
        $traffic = [];
        try {
            $response = $responses['traffic'];
            $served = strtotime($response->header('Date'));
            if ($served !== false && abs(time() - $served) <= 30 && (int)$response->header('Age') <= 30) {
                $traffic = UpstreamResponseMapper::json($response);
            }
        } catch (TransportException) { /* GPS can be available without a verified delay. */ }
        return IdsJmkObservationMapper::map(GtfsRealtimeReader::vehicles($responses['vehicles']->body), $traffic, $trip, time());
    }
}
