<?php

declare(strict_types=1);

namespace App\Modules\Transport\Integrations\OpenTripPlanner;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\{HttpRequest, HttpResponse};
use App\Modules\Transport\Contracts\{ResourceEnrichmentProvider, ResourceMappingProvider, ScheduleProvider};
use App\Modules\Transport\Model\{ResourceIdCodec, TransportException, UpstreamResponseMapper};
use App\Modules\Transport\Protocols\Transmodel\TransmodelProvider;

final class OtpProvider extends TransmodelProvider implements ScheduleProvider, ResourceMappingProvider, ResourceEnrichmentProvider
{
    /** Bounds the batch of follow-up stop lookups a single geocoder search can trigger. */
    private const MAX_PLACES = 30;

    public function capabilities(): array
    {
        return array_merge(
            parent::capabilities(),
            isset($this->definition->config['source_provider']) ? ['realtime'] : [],
            isset($this->definition->config['geocoder_url']) ? ['places'] : [],
        );
    }

    public function sourceReference(string $operation, array $reference): ?array
    {
        $config = $this->definition->config;
        if (!isset($config['source_provider'], $config['otp_feed_id'])) {
            return null;
        }
        $prefix = $config['otp_feed_id'] . ':';
        if (!str_starts_with($reference['external'], $prefix)) {
            throw new TransportException('not_found', 'Resource is outside the configured feed.', 404);
        }
        return array_replace($reference, ['provider' => $config['source_provider'], 'external' => substr($reference['external'], strlen($prefix))]);
    }

    public function fallbackReference(string $operation, array $reference): ?array
    {
        return null;
    }

    /**
     * Sestaví požadavek na OTP Geocoder API (`/otp/geocode/stopClusters`) pro
     * operaci `places`; ostatní operace řeší sdílený Transmodel kontrakt.
     */
    public function resourceRequest(string $operation, array $input): HttpRequest
    {
        if ($operation !== 'places') {
            return parent::resourceRequest($operation, $input);
        }
        if (!isset($this->definition->config['geocoder_url'])) {
            throw new TransportException('unsupported_capability', 'OTP geocoder is not configured.', 422);
        }
        $query = ['query' => (string)$input['query']];
        if (is_numeric($input['location']['lat'] ?? null) && is_numeric($input['location']['lon'] ?? null)) {
            $query['focusLatitude'] = $input['location']['lat'];
            $query['focusLongitude'] = $input['location']['lon'];
        }
        return new HttpRequest(
            rtrim((string)$this->definition->config['geocoder_url'], '/') . '/stopClusters?' . http_build_query($query),
            headers: ['Accept' => 'application/json'],
        );
    }

    /**
     * Převede výsledek stop clusterů na provizorní zastávky (ID a souřadnice);
     * jméno dořeší `enrichResource()` přes ověřený Transmodel dotaz na zastávku.
     */
    public function resourceResult(string $operation, HttpResponse $result, array $input): array
    {
        if ($operation !== 'places') {
            return parent::resourceResult($operation, $result, $input);
        }
        $clusters = UpstreamResponseMapper::json($result);
        if (!array_is_list($clusters)) {
            throw new TransportException('invalid_upstream', 'Invalid OTP geocoder response.', 502);
        }
        $rows = [];
        foreach (array_slice($clusters, 0, self::MAX_PLACES) as $cluster) {
            if (!is_array($cluster) || !is_string($cluster['primaryId'] ?? null) || trim($cluster['primaryId']) === '') {
                continue;
            }
            $coordinate = $cluster['coordinate'] ?? null;
            $lat = is_array($coordinate) && is_numeric($coordinate['lat'] ?? null) ? (float)$coordinate['lat'] : null;
            $lon = is_array($coordinate) && is_numeric($coordinate['lon'] ?? null) ? (float)$coordinate['lon'] : null;
            // Name is intentionally unresolved here; the geocoder's own label shape is not a documented contract.
            $rows[] = ['id' => $this->id('stop', $cluster['primaryId']), 'name' => null, 'lat' => $lat, 'lon' => $lon, 'platform' => null, 'timezone' => null];
        }
        return $rows;
    }

    /**
     * Dořeší jméno, nástupiště a časové pásmo zastávek nalezených geokodérem
     * přes stejný ověřený Transmodel `stop` dotaz, který už modul zná. Zastávka
     * bez vyřešeného jména je `PlaceSearchService` automaticky vyřazena.
     */
    public function enrichResource(string $operation, array $result, array $input, HttpClient $http): array
    {
        if ($operation !== 'places') {
            return $result;
        }
        $requests = [];
        foreach ($result as $row) {
            if ($row['name'] !== null || isset($requests[$row['id']])) {
                continue;
            }
            $ref = ResourceIdCodec::decode((string)$row['id'], $this->definition->tenant, 'stop');
            if ($ref['provider'] !== $this->definition->code) {
                continue;
            }
            $requests[$row['id']] = parent::resourceRequest('stop', ['external' => $ref['external']]);
        }
        if (!$requests) {
            return $result;
        }
        $resolved = [];
        foreach ($http->sendAll($requests) as $id => $response) {
            try {
                $resolved[$id] = parent::resourceResult('stop', $response, []);
            } catch (TransportException) {
                // A single unresolved stop keeps its provisional row; it is filtered later by ranking.
            }
        }
        foreach ($result as &$row) {
            $stop = $resolved[$row['id']] ?? null;
            if ($stop !== null) {
                $row = array_replace($row, ['name' => $stop['name'], 'platform' => $stop['platform'], 'timezone' => $stop['timezone']]);
                $row['lat'] ??= $stop['lat'];
                $row['lon'] ??= $stop['lon'];
            }
        }
        unset($row);
        return $result;
    }
}
