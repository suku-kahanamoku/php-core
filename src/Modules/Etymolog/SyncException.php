<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

final class SyncException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $retryAfter = 300)
    {
        parent::__construct($reason);
    }
}
