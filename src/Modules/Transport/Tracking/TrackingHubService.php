<?php

declare(strict_types=1);

namespace App\Modules\Transport\Tracking;

use App\Modules\Http\Contracts\AsyncHttpClient;
use App\Modules\Http\{HttpRequest,HttpResponse};

/** Reference-counted subscriptions. One fetch per trip every 10s; no persistent telemetry. */
final class TrackingHubService
{
    private array $peers = [];
    private array $watches = [];
    private int $generation = 0;
    private array $requests = [];
    public function __construct(private readonly TrackingTicketService $tickets, private readonly AsyncHttpClient $http, private readonly string $coreUrl, private readonly string $key, private readonly array $tenants, private readonly int $maxTrips = 10, private readonly int $interval = 10)
    {
        if ($maxTrips < 1 || $maxTrips > 100 || $interval < 10 || $interval > 30) {
            throw new \InvalidArgumentException("Tracking limits out of range.");
        }
    }
    public function message(string $peer, array $body, callable $send, callable $close): void
    {
        if (($body['type'] ?? '') === 'ping' && isset($this->peers[$peer])) {
            $send(['type' => 'pong']);
            return;
        }
        if (($body['type'] ?? '') !== 'subscribe' || isset($this->peers[$peer])) {
            $close();
            return;
        }
        try {
            $c = $this->tickets->verify((string)($body['ticket'] ?? ''), time());
        } catch (\Throwable) {
            $close();
            return;
        }
        if (!isset($this->tenants[$c['tenant']])) {
            $close();
            return;
        }
        $watch = $c['tenant'].'|'.$c['trip'];
        // Bound public HTTP quota usage to <=60/min, leaving room for normal requests.
        if (!isset($this->watches[$watch]) && count($this->watches) >= $this->maxTrips) {
            $send(['type' => 'observation','trip' => $c['trip'],'data' => TrackingObservationMapper::unavailable('busy')]);
            $close();
            return;
        }
        $this->peers[$peer] = ['watch' => $watch,'expires' => $c['expires'],'send' => $send,'close' => $close];
        $this->watches[$watch] ??= ['tenant' => $c['tenant'],'trip' => $c['trip'],'next' => 0,'pending' => false,'generation' => ++$this->generation];
        $send(['type' => 'observation','trip' => $c['trip'],'data' => TrackingObservationMapper::unavailable('connecting')]);
    }
    public function closed(string $peer): void
    {
        $watch = $this->peers[$peer]['watch'] ?? null;
        unset($this->peers[$peer]);
        if ($watch !== null && !array_filter($this->peers, fn ($p) => $p['watch'] === $watch)) {
            unset($this->watches[$watch]);
        }
    }
    public function tick(): void
    {
        foreach ($this->peers as $id => $p) {
            if ($p['expires'] <= time()) {
                ($p['close'])();
                $this->closed($id);
            }
        }
        $this->requests = array_values(array_filter($this->requests, static fn ($at) => $at > time() - 60));
        foreach ($this->watches as $id => $w) {
            if (count($this->requests) >= 60) {
                break;
            }
            if ($w['pending'] || $w['next'] > time()) {
                continue;
            }
            $this->watches[$id]['pending'] = true;
            $this->watches[$id]['next'] = time() + $this->interval;
            $this->requests[] = time();
            $request = new HttpRequest(rtrim($this->coreUrl, '/').'/transport/v1/trips/'.$w['trip'].'/observation', headers:['Accept' => 'application/json','X-Internal-Key' => $this->key,'X-Forwarded-Host' => $this->tenants[$w['tenant']]], timeoutMs:8000, maxBytes:65536);
            $this->http->sendAsync($request, function (HttpResponse $response) use ($id, $w): void {
                if (($this->watches[$id]['generation'] ?? null) !== $w['generation']) {
                    return;
                }
                $this->watches[$id]['pending'] = false;
                $data = TrackingObservationMapper::unavailable();
                if ($response->successful()) {
                    try {
                        $body = $response->json();
                        $r = ($body['success'] ?? false) === true ? ($body['data'] ?? []) : [];
                        // Recheck freshness at delivery; backend request time never extends GPS life.
                        if (($r['status'] ?? '') === 'live') {
                            $data = TrackingObservationMapper::map(['realtime' => true,'observed_at' => $r['observed_at'] ?? null,'position' => ['type' => 'Point','coordinates' => [$r['position']['lon'] ?? null,$r['position']['lat'] ?? null]],'delay_seconds' => $r['delay_seconds'] ?? null,'cancelled' => $r['cancelled'] ?? null], time());
                        } elseif (in_array($r['status'] ?? '', ['stale','unsupported'], true)) {
                            $data = TrackingObservationMapper::unavailable($r['status']);
                        }
                    } catch (\Throwable) {
                    }
                }
                foreach ($this->peers as $p) {
                    if ($p['watch'] === $id) {
                        ($p['send'])(['type' => 'observation','trip' => $w['trip'],'data' => $data]);
                    }
                }
            });
        }
    }
}
