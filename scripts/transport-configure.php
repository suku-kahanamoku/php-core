<?php

declare(strict_types=1);
require __DIR__.'/transport-bootstrap.php';
$path = $options['config'] ?? '';
if (!is_file($path)) {
    throw new App\Modules\Transport\Model\TransportException('missing_config', 'Supply --config=<server JSON file>.');
}
$config = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
App\Modules\Transport\Core\ConfigurationService::apply($repository, $config, \App\Modules\Transport\TransportModule::integrations(), \App\Modules\Transport\TransportModule::importers($repository));
echo "Transport configuration applied for tenant $tenant.\n";
