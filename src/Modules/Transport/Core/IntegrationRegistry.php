<?php
declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Transport\Contracts\IntegrationModule;
use App\Modules\Transport\Model\TransportException;

final class IntegrationRegistry
{
    private array $modules = [];

    /** @param iterable<IntegrationModule> $modules */
    public function __construct(iterable $modules)
    {
        foreach ($modules as $module) {
            if (isset($this->modules[$module->adapter()])) { throw new \LogicException('Duplicate transport adapter.'); }
            $this->modules[$module->adapter()] = $module;
        }
    }

    public function get(string $adapter): IntegrationModule
    {
        return $this->modules[$adapter] ?? throw new TransportException('invalid_adapter', 'Unknown transport adapter.', 500);
    }

    public function has(string $adapter): bool { return isset($this->modules[$adapter]); }
}
