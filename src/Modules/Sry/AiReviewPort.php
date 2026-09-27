<?php

declare(strict_types=1);

namespace App\Modules\Sry;

/** Future server-only adapter. A suggestion never awards points or replaces a parent review. */
interface AiReviewPort
{
    /** @return array{enabled:bool, decision:'manual', reason:string} */
    public function suggest(
        int $submissionId,
        array $previousApprovedSubmissionIds,
    ): array;
}
