<?php
declare(strict_types=1);
namespace App\Modules\Transport\Contracts;

use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Persistence\TransportRepository;

/** Installed code owns construction and validation; JSON never names PHP classes. */
interface IntegrationModule
{
    public function adapter(): string;
    public function validate(ProviderDefinition $definition): void;
    public function create(ProviderDefinition $definition, array $env, ?TransportRepository $repository = null): Provider;
}
