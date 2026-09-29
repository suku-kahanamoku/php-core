<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Utils\QueryPolicy;

final class EtymologPublicService
{
    /** Povolene sloupce ve verejnem filtru `q`. */
    private const PUBLIC_FILTERS = ['name', 'kind', 'language', 'country_code'];
    private const MAX_FILTER_BYTES = 16000;

    public function __construct(private readonly EtymologPublicRepository $repository) {}

    public function search(mixed $query, mixed $kind, mixed $page): array
    {
        $filter = $this->filter($query, $kind);
        $page = $this->positiveInteger($page);
        if ($page > 1000000) { throw new EtymologException('Invalid page', 422); }
        return $this->repository->search($filter, $page);
    }

    public function detail(mixed $id): array
    {
        return $this->repository->detail($this->positiveInteger($id)) ?? throw new EtymologException('Name not found', 404);
    }

    /**
     * Prelozi `q` (JSON objekt, standardni filter syntaxe) na bezpecny JSON filtr.
     * `kind` z query parametru se do filtru vlozi jako dodatecna podminka.
     */
    private function filter(mixed $query, mixed $kind): string
    {
        if (!is_string($kind) || !in_array($kind, ['', 'given', 'surname'], true)) {
            throw new EtymologException('Invalid search parameters', 422);
        }
        if (!is_string($query) || $query === '' || strlen($query) > self::MAX_FILTER_BYTES) {
            throw new EtymologException('q must be a JSON object', 422);
        }
        $decoded = json_decode($query, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Keep established plain-text public URLs working for existing callers.
            $decoded = ['name' => ['$regex' => trim($query)]];
        }
        if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            throw new EtymologException('q must be a JSON object', 422);
        }
        array_walk_recursive($decoded, static function ($value): void {
            if (!is_scalar($value) && $value !== null) {
                throw new EtymologException('Invalid filter', 422);
            }
        });
        if ($kind !== '') {
            $decoded['kind'] = ['value' => $kind];
        }
        $safe = QueryPolicy::filter(json_encode($decoded), self::PUBLIC_FILTERS);
        if ($safe === '') {
            throw new EtymologException('Invalid search parameters', 422);
        }
        $name = $this->nameValue(json_decode($safe, true)['name'] ?? null);
        if ($name === null || mb_strlen(trim($name)) < 2 || mb_strlen(trim($name)) > 100) {
            throw new EtymologException('Invalid search parameters', 422);
        }
        return $safe;
    }

    private function nameValue(mixed $spec): ?string
    {
        if (is_string($spec)) {
            return $spec;
        }
        if (!is_array($spec)) {
            return null;
        }
        foreach (['value', '$regex', '$eq'] as $key) {
            if (isset($spec[$key]) && is_string($spec[$key])) {
                return $spec[$key];
            }
        }
        return null;
    }

    private function positiveInteger(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > 2147483647) {
            throw new EtymologException('Expected positive integer', 422);
        }
        return (int)$value;
    }
}
