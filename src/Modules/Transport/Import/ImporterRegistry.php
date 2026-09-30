<?php
declare(strict_types=1);
namespace App\Modules\Transport\Import;

use App\Modules\Transport\Contracts\FeedImporter;
use App\Modules\Transport\Model\TransportException;

final class ImporterRegistry
{
    private array $importers = [];
    public function __construct(iterable $factories)
    {
        foreach ($factories as $factory) {
            $importer = $factory();
            if (isset($this->importers[$importer->format()])) { throw new \LogicException('Duplicate import format.'); }
            $this->importers[$importer->format()] = $factory;
        }
    }
    public function get(string $format): FeedImporter
    {
        $factory = $this->importers[$format] ?? throw new TransportException('unsupported_feed_format', 'Feed importer is not installed.');
        return $factory();
    }
}
