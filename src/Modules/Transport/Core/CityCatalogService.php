<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Model\{RequestBudget, TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

/** Tenant-scoped online municipality catalogues, with imported city metadata only on source failure. */
final class CityCatalogService
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository) {}

    public function search(array $query): array
    {
        $result = (new ResourceSearchService($this->registry, $this->http, $this->repository))->search(
            'cities',
            ['country' => $query['country']],
            $query['country'],
            null,
            null,
            new RequestBudget()
        );
        if (!$result['covered']) {
            throw new TransportException('cities_not_configured', 'No municipality catalogue for this country.', 503);
        }
        $rows = $result['rows'];
        if ($result['failed']) {
            array_push($rows, ...$this->repository->cities($query['country'], $result['failed']));
        }
        $partial = (bool)array_filter($result['sources'], fn($s) => $s['status'] !== 'ok');
        if (!$rows && $partial && !array_filter($result['sources'], fn($s) => $s['status'] === 'ok')) {
            throw new TransportException('sources_unavailable', 'Municipality sources are unavailable.', 503);
        }
        $unique = [];
        $term = PlaceSearchService::normalize($query['query']);
        foreach ($rows as $row) {
            if (($row['state'] ?? null) !== $query['country'] || !is_string($row['name'] ?? null) || trim($row['name']) === '') {
                continue;
            }
            $name = PlaceSearchService::normalize($row['name']);
            if ($term !== '' && !str_contains($name, $term)) {
                continue;
            }
            $key = $row['state'] . ':' . $name;
            if (!isset($unique[$key]) || ($unique[$key]['source_mode'] === 'fallback' && $row['source_mode'] === 'live')) {
                $unique[$key] = $row;
            }
        }
        $descending = str_contains($query['sort'], '-1') || str_contains($query['sort'], 'DESC');
        // Names are already normalized in the unique keys; do not transliterate on every comparison.
        if ($descending) {
            krsort($unique, SORT_STRING);
        } else {
            ksort($unique, SORT_STRING);
        }
        $rows = array_values($unique);
        $total = count($rows);
        $offset = ($query['page'] - 1) * $query['limit'];
        $rows = array_slice($rows, $offset, $query['limit']);
        $projection = array_flip($query['projection']);
        $rows = array_map(fn($row) => array_intersect_key($row, $projection), $rows);
        return ['data' => $rows, 'total' => $total, 'has_more' => $offset + count($rows) < $total, 'partial' => $partial, 'sources' => $result['sources']];
    }
}
