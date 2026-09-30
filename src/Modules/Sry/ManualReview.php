<?php

declare(strict_types=1);

namespace App\Modules\Sry;

/**
 * Výchozí implementace `AiReviewPort`, která žádnou automatickou kontrolu nedělá.
 *
 * Slouží jako bezpečný fallback: dokud není připojen žádný adaptér, rozhodnutí o
 * odevzdání vždy zůstává na rodiči.
 */
final class ManualReview implements AiReviewPort
{
    /**
     * Vždy vrátí, že je nutné ruční rozhodnutí rodiče.
     *
     * @param  int   $submissionId                   ID odevzdání (ignorováno).
     * @param  list<int> $previousApprovedSubmissionIds Dříve schválená odevzdání (ignorována).
     * @return array{enabled: bool, decision: 'manual', reason: string}
     *                                           `{ enabled: false, decision: 'manual', reason: 'parent_review_required' }`.
     */
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
