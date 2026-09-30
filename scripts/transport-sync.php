<?php

declare(strict_types=1);
require __DIR__.'/transport-bootstrap.php';
$storage = $_ENV['TRANSPORT_STORAGE_DIR'] ?? dirname(__DIR__).'/temp/transport';
$result = App\Modules\Transport\TransportModule::feedSync($repository, $storage, App\Modules\Http\HttpModule::client())->sync((string)($options['feed'] ?? ''), $options['file'] ?? null);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
