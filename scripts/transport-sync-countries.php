<?php

declare(strict_types=1);
require __DIR__ . '/transport-bootstrap.php';
$path = $options['config'] ?? '';
if (!is_file($path)) {
    throw new App\Modules\Transport\Model\TransportException('missing_config', 'Supply --config=<server JSON file with "countries">.');
}
$config = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
App\Modules\Transport\Core\ConfigurationService::apply($repository, $config, App\Modules\Transport\TransportModule::integrations(), App\Modules\Transport\TransportModule::importers($repository));
$storage = $_ENV['TRANSPORT_STORAGE_DIR'] ?? dirname(__DIR__) . '/temp/transport';
$feedSync = App\Modules\Transport\TransportModule::feedSync($repository, $storage, App\Modules\Http\HttpModule::client());
$resolved = App\Modules\Transport\Core\CountryConfigurationService::compose($config);
$results = ['tenant' => $tenant, 'providers_applied' => count($resolved['providers']), 'feeds' => []];
foreach ($resolved['feeds'] as $feed) {
    $results['feeds'][$feed['code']] = $feedSync->sync($feed['code']);
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
