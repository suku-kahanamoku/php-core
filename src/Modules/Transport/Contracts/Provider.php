<?php

declare(strict_types=1);

namespace App\Modules\Transport\Contracts;

use App\Modules\Transport\DTO\ProviderDefinition;

interface Provider
{
    public function definition(): ProviderDefinition;
    /** @return list<string> */
    public function capabilities(): array;
}
