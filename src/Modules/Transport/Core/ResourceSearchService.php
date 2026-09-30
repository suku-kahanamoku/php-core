<?php
declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Transport\Contracts\ResourceProvider;
use App\Modules\Transport\Model\{RequestBudget,ResourceIdCodec,TransportException};
use App\Modules\Transport\Persistence\TransportRepository;

/** Shared online search and fallback selection for names and nearby stops. */
final class ResourceSearchService
{
    public function __construct(private readonly ProviderRegistry $registry, private readonly HttpClient $http, private readonly TransportRepository $repository) {}

    public function search(string $operation, array $input, ?string $country, ?string $city, ?array $location, RequestBudget $budget): array
    {
        $candidates = (new ProviderSelectionService($this->registry))->select($operation, $country, $city, $location);
        $sources = $rows = $failed = [];
        $execution = new ProviderExecutionService($this->http, $this->repository);
        foreach (['primary','fallback'] as $phase) {
            $selected = array_filter($candidates, static fn ($p) => $p instanceof ResourceProvider
                && $p->definition()->roleFor($operation) === $phase
                && ($phase === 'primary' || array_intersect($p->definition()->fallbackFor($operation), $failed)));
            $results = $execution->run($selected, function ($provider, HttpClient $http) use ($operation,$input): array {
                $code = $provider->definition()->code;
                $response = $http->sendAll([$code=>$provider->resourceRequest($operation, $input)])[$code];
                $data = $provider->resourceResult($operation, $response, $input);
                foreach ($data as $row) {
                    $ref = ResourceIdCodec::decode((string)($row['id'] ?? ''), $this->repository->tenant, 'stop');
                    if ($ref['provider'] !== $code) { throw new TransportException('invalid_upstream', 'Invalid stop source.', 502); }
                }
                return $data;
            }, $budget->child($phase === 'primary' ? 5000 : 3000));
            foreach ($results as $code=>$result) {
                if ($result->status === 'configuration_error') { throw $result->error; }
                $sources[] = ['provider'=>$code,'status'=>$result->status];
                if ($result->succeeded()) {
                    foreach ($result->data as $row) { $rows[$row['id']] ??= $row + ['source_mode'=>$phase === 'primary' ? 'live' : 'fallback']; }
                } elseif ($result->allowsFallback()) { $failed[] = $code; }
            }
        }
        return ['rows'=>array_values($rows),'sources'=>$sources,'failed'=>$failed,'covered'=>(bool)$candidates];
    }
}
