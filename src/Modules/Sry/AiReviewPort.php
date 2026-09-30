<?php

declare(strict_types=1);

namespace App\Modules\Sry;

/**
 * Budoucí adaptér pro automatickou kontrolu odevzdání — poběží výhradně na serveru.
 *
 * Doporučení nikdy neuděluje body ani nenahrazuje revizi rodiče; rozhodnutí má
 * vždy člověk.
 */
interface AiReviewPort
{
    /**
     * Vrátí doporučení k odevzdání; implementace musí být čistě informativní.
     *
     * @param  int        $submissionId                  ID odevzdání k posouzení.
     * @param  list<int>  $previousApprovedSubmissionIds IDs dříve schválených odevzdání stejného člena.
     * @return array{enabled:bool, decision:'manual', reason:string} Doporučení a jeho zdůvodnění.
     */
    public function suggest(
        int $submissionId,
        array $previousApprovedSubmissionIds,
    ): array;
}
