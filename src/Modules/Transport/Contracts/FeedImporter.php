<?php
declare(strict_types=1);
namespace App\Modules\Transport\Contracts;

/** Import into a staged canonical snapshot. Activation is a separate operation. */
interface FeedImporter
{
    public function format(): string;
    public function import(string $archivePath, int $versionId): array;
}
