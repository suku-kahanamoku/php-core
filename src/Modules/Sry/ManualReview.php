<?php

declare(strict_types=1);

namespace App\Modules\Sry;

final class ManualReview implements AiReviewPort
{
    public function suggest(
        int $submissionId,
        array $previousApprovedSubmissionIds,
    ): array {
        return [
            "enabled" => false,
            "decision" => "manual",
            "reason" => "parent_review_required",
        ];
    }
}
