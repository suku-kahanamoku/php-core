<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

final class EtymologPublicService
{
    public function __construct(private readonly EtymologPublicRepository $repository) {}
    public function search(mixed $query, mixed $kind, mixed $page): array
    {
        if (!is_string($query) || mb_strlen(trim($query)) < 2 || mb_strlen(trim($query)) > 100 || !is_string($kind) || !in_array($kind, ['', 'given', 'surname'], true)) {
            throw new EtymologException('Invalid search parameters', 422);
        }
        $page = $this->positiveInteger($page);
        if ($page > 1000000) { throw new EtymologException('Invalid page', 422); }
        return $this->repository->search(trim($query), $kind, $page);
    }
    public function detail(mixed $id): array
    {
        return $this->repository->detail($this->positiveInteger($id)) ?? throw new EtymologException('Name not found', 404);
    }
    private function positiveInteger(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > 2147483647) {
            throw new EtymologException('Expected positive integer', 422);
        }
        return (int)$value;
    }
}
