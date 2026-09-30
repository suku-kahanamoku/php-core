<?php

declare(strict_types=1);

namespace App\Modules\Transport\Model;

/**
 * Whitelist polí pro krátkodobý detail cesty; telemetrie ani soukromé body
 * uživatelského dotazu nesmějí projít do perzistentního payloadu.
 */
final class JourneyCacheMapper
{
    /**
     * @param  array<string, mixed> $journey            Normalizovaná cesta.
     * @param  bool                 $publicStopsOnly     true jen pokud oba vstupy byly veřejné zastávky.
     * @return array<string, mixed>                     Omezený payload pro detail.
     */
    public static function sanitize(array $journey, bool $publicStopsOnly = false): array
    {
        $result = array_intersect_key($journey, array_flip(['duration_seconds','transfers']));
        $result['source'] = array_intersect_key($journey['source'] ?? [], array_flip(['provider','status','mode','fetched_at','graph_version','snapshot_at','valid_until','attribution','limited']));
        $result['legs'] = [];
        foreach ($journey['legs'] ?? [] as $leg) {
            $item = array_intersect_key($leg, array_flip([
                'mode','scheduled_departure','scheduled_arrival',
                'trip_id','service_date','line','operator','distance_m',
            ]));
            if (isset($item['line'])) {
                $item['line'] = is_array($item['line']) ? array_intersect_key($item['line'], array_flip(['id','name','code','mode'])) : null;
            }
            if (isset($item['operator'])) {
                $item['operator'] = is_array($item['operator']) ? array_intersect_key($item['operator'], array_flip(['id','name'])) : null;
            }
            $item['expected_departure'] = null;
            $item['expected_arrival'] = null;
            $item['realtime'] = false;
            $item['cancelled'] = null;
            foreach (['from','to'] as $key) {
                $place = array_intersect_key($leg[$key] ?? [], array_flip(['id','name','lat','lon','platform','timezone']));
                if (!$publicStopsOnly || empty($place['id'])) {
                    $place['name'] = null;
                    $place['lat'] = null;
                    $place['lon'] = null;
                    $place['platform'] = null;
                }
                $item[$key] = $place;
            }
            $item['geometry'] = $publicStopsOnly && ($leg['mode'] ?? '') !== 'walk' ? ($leg['geometry'] ?? null) : null;
            $result['legs'][] = $item;
        }
        return $result;
    }
}
