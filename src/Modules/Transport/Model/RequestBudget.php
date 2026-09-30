<?php
declare(strict_types=1);
namespace App\Modules\Transport\Model;

/** Monotonic deadline. The same budget travels through resolution, search and enrichment. */
final class RequestBudget
{
    private readonly int $deadline;

    public function __construct(int $milliseconds = 8000, ?int $parentDeadline = null)
    {
        $this->deadline = min($parentDeadline ?? PHP_INT_MAX, hrtime(true) + max(0, $milliseconds) * 1000000);
    }

    public function remainingMs(): int { return max(0, (int)floor(($this->deadline - hrtime(true)) / 1000000)); }
    public function child(int $milliseconds): self { return new self($milliseconds, $this->deadline); }
}
