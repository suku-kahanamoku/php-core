<?php

declare(strict_types=1);

namespace App\Modules\Transport\Gateway;

/** Safe gateway configuration/network/protocol error; no transport domain decisions. */
final class JavaTransportException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
