<?php

declare(strict_types=1);

namespace App\Modules\Sry;

final class SryError extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
    ) {
        parent::__construct($errorCode);
    }
}
