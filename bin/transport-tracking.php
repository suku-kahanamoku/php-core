<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
use App\Modules\Http\HttpModule;
use App\Modules\Transport\Tracking\{TrackingHubService,TrackingTicketService};

$env = fn (string $key) => $_ENV[$key] ?? getenv($key) ?: '';
$core = $env('TRANSPORT_TRACKING_CORE_URL');
if (!preg_match('~^https?://[a-zA-Z0-9.:-]+(?:/[a-zA-Z0-9/_-]*)?$~D', $core)) {
    throw new RuntimeException('Configure trusted TRANSPORT_TRACKING_CORE_URL.');
}
$tenants = json_decode($env('TRANSPORT_TRACKING_TENANTS'), true, 16, JSON_THROW_ON_ERROR);
if (!is_array($tenants) || !$tenants || !$env('INTERNAL_API_KEY')) {
    throw new RuntimeException('Tracking tenant map/internal key required.');
}
foreach ($tenants as $host) {
    if (!is_string($host) || !preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di', $host)) {
        throw new RuntimeException('Invalid tenant host.');
    }
}
$hub = new TrackingHubService(new TrackingTicketService($env('TRANSPORT_TRACKING_SECRET')), HttpModule::asyncClient(), $core, $env('INTERNAL_API_KEY'), $tenants, (int)($env('TRANSPORT_TRACKING_MAX_TRIPS') ?: 10), (int)($env('TRANSPORT_TRACKING_INTERVAL') ?: 10));
HttpModule::websocket()->run($env('TRANSPORT_TRACKING_LISTEN') ?: 'websocket://127.0.0.1:8091', array_values(array_filter(array_map('trim', explode(',', $env('TRANSPORT_TRACKING_ORIGINS'))))), $hub->message(...), $hub->closed(...), $hub->tick(...), 'transport-tracking', $env('TRANSPORT_TRACKING_RUNTIME_DIR') ?: null);
