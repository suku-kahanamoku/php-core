<?php

declare(strict_types=1);
require __DIR__.'/transport-bootstrap.php';
$manager = new App\Modules\Transport\Integrations\OpenTripPlanner\GraphService($repository, App\Modules\Http\HttpModule::client());
$version = App\Modules\Transport\Model\JourneyQuery::integer($options['version'] ?? 0, 1, PHP_INT_MAX);
if (($options['command'] ?? '') === 'export') {
    echo json_encode($manager->export($version, (string)($options['output'] ?? '')), JSON_PRETTY_PRINT)."\n";
} elseif (($options['command'] ?? '') === 'activate') {
    $manager->activate($version, (string)($options['manifest'] ?? ''), (string)($options['graph-url'] ?? ''));
    echo "Activated graph version $version.\n";
} else {
    throw new App\Modules\Transport\Model\TransportException('invalid_command', 'Use --command=export or --command=activate.');
}
