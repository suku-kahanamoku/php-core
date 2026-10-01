<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

/** A live integration may resolve explicit foreign identifiers without guessing by line/name. */
interface RealtimeReferenceProvider extends Provider
{
    public function realtimeReference(array $reference): ?array;
}
