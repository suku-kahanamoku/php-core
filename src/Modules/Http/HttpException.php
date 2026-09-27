<?php

declare(strict_types=1);

namespace App\Modules\Http;

/** Contains no upstream body, URL, credentials or transport exception chain. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 0)
    {
        parent::__construct('HTTP request failed: '.$reason);
    }
}
