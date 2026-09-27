<?php

declare(strict_types=1);

namespace App\Modules\Transport;

final class TransportException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly int $status = 422, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
