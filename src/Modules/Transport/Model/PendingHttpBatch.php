<?php
declare(strict_types=1);
namespace App\Modules\Transport\Model;

final class PendingHttpBatch
{
    public array $responses = [];
    public function __construct(public array $requests, public readonly RequestBudget $budget, public readonly int $concurrency) {}
}
