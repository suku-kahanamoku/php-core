<?php

declare(strict_types=1);
namespace App\Modules\Transport\DTO;

use App\Utils\QueryPolicy;
use App\Modules\Transport\TransportException;

/** Standard q filter; GPS is accepted in a POST body only, never URL or storage. */
final class PlaceQuery
{
    public static function parse(array $body): array
    {
        if (array_diff(array_keys($body), ['q','limit','page','sort','projection'])) { throw new TransportException('invalid_query', 'Unknown query options.'); }
        $raw = $body['q'] ?? [];
        if (is_string($raw)) { $raw = json_decode($raw, true); }
        if (!is_array($raw) || !$raw || array_is_list($raw)) { throw new TransportException('invalid_query', 'Expected a q filter.'); }
        $q = json_decode(QueryPolicy::filter(json_encode($raw, JSON_THROW_ON_ERROR), ['name','state','city','latitude','longitude','observed_at']), true);
        $name = $q['name']['$regex'] ?? null;
        if (!is_string($name) || array_keys($q['name']) !== ['$regex'] || mb_strlen(trim($name)) < 2 || mb_strlen($name) > 120) { throw new TransportException('invalid_query', 'Expected a literal name substring.'); }
        $country = $q['state'] ?? null; $city = $q['city'] ?? null;
        if ($country !== null && (!is_string($country) || !preg_match('/^[A-Z]{2}$/D',$country))) { throw new TransportException('invalid_country','Invalid country.'); }
        if ($city !== null && (!is_string($city) || trim($city) === '' || mb_strlen($city)>120)) { throw new TransportException('invalid_city','Invalid city.'); }
        $location = null;
        if (isset($q['latitude']) || isset($q['longitude']) || isset($q['observed_at'])) {
            $location = JourneyQuery::currentLocation(['type'=>'current_location','lat'=>$q['latitude']??null,'lon'=>$q['longitude']??null,'observed-at'=>$q['observed_at']??null]);
        }
        if (JourneyQuery::integer($body['page']??1,1,1000) !== 1) { throw new TransportException('invalid_query','Online autocomplete supports the first result page only.'); }
        $sort = QueryPolicy::sort(is_string($body['sort']??null) ? $body['sort'] : json_encode($body['sort']??[]), ['name']);
        $projection = $body['projection'] ?? null;
        if ($projection !== null && !is_string($projection)) { throw new TransportException('invalid_query','Invalid projection.'); }
        return ['query'=>trim($name),'country'=>$country,'city'=>$city === null ? null : trim($city),'location'=>$location,
            'limit'=>JourneyQuery::integer($body['limit']??20,1,50),'sort'=>$sort,
            'projection'=>QueryPolicy::projection($projection===null?null:explode(',',$projection),['id','name','lat','lon','platform','timezone','source_mode'])];
    }
}
