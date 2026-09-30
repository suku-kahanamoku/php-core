<?php
declare(strict_types=1);
namespace App\Modules\Transport\Model;

/** Internal execution result; it does not replace the public API envelope. */
final class ProviderResult
{
    public function __construct(
        public readonly string $status,
        public readonly mixed $data = null,
        public readonly ?\Throwable $error = null,
    ) {}

    public function succeeded(): bool { return $this->status === 'ok'; }
    public function allowsFallback(): bool { return $this->status === 'unavailable'; }
}
