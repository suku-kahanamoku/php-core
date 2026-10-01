<?php

declare(strict_types=1);

namespace App\Modules\Transport\Model;

use App\Utils\QueryPolicy;

/** Standard list query for provider municipality catalogues; names are literal substrings. */
final class CityQuery
{
    public static function parse(array $body): array
    {
        if (array_diff(array_keys($body), ['q', 'limit', 'page', 'sort', 'projection'])) {
            throw new TransportException('invalid_query', 'Unknown query options.');
        }
        $raw = $body['q'] ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw) || !$raw || array_is_list($raw) || array_diff(array_keys($raw), ['name', 'state'])) {
            throw new TransportException('invalid_query', 'Expected a city q filter.');
        }
        $q = json_decode(QueryPolicy::filter(json_encode($raw, JSON_THROW_ON_ERROR), ['name', 'state']), true);
        $country = $q['state'] ?? null;
        if (!is_string($country) || !preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new TransportException('invalid_country', 'Supply a country.');
        }
        $name = $q['name']['$regex'] ?? '';
        if (isset($q['name']) && (!is_array($q['name']) || array_keys($q['name']) !== ['$regex'] || !is_string($name) || mb_strlen($name) > 120)) {
            throw new TransportException('invalid_query', 'Expected a literal name substring.');
        }
        $projection = $body['projection'] ?? null;
        if ($projection !== null && !is_string($projection)) {
            throw new TransportException('invalid_query', 'Invalid projection.');
        }
        $sort = $body['sort'] ?? '';
        return [
            'country' => $country,
            'query' => trim($name),
            'page' => JourneyQuery::integer($body['page'] ?? 1, 1, 1000),
            'limit' => JourneyQuery::integer($body['limit'] ?? 50, 1, 10000),
            'sort' => QueryPolicy::sort(is_string($sort) ? $sort : json_encode($sort), ['name'], 'name ASC'),
            'projection' => QueryPolicy::projection($projection === null ? null : explode(',', $projection), ['id', 'name', 'state', 'source_mode'])
        ];
    }
}
