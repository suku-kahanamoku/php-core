<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Contracts;

interface BatchProvider
{
    /** @return array{items:array,cursor:?string,complete:bool} */
    public function batch(string $language, string $kind, ?string $cursor, int $limit): array;
}
