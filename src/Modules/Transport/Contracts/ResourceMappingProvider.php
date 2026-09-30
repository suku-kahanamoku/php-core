<?php
declare(strict_types=1);
namespace App\Modules\Transport\Contracts;

/** Exact provider-issued identities only. References preserve kind and service date. */
interface ResourceMappingProvider extends Provider
{
    public function sourceReference(string $operation, array $reference): ?array;
    public function fallbackReference(string $operation, array $reference): ?array;
}
