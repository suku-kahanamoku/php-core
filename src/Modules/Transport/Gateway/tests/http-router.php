<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_URI'] === '/health') {
    echo '{"ok":true}';
    exit;
}
require dirname(__DIR__, 5) . '/vendor/autoload.php';
$_ENV['FRANCHISE_CODES'] = 'tram.test:tram,other.test:other';
$_ENV['INTERNAL_API_KEY'] = 'java-gateway-test-internal-key';
$_ENV['APP_ENV'] = 'production';
$_ENV['TRANSPORT_JAVA_ENABLED'] = '1';
$_ENV['TRANSPORT_JAVA_TENANT'] = 'tram';
$_ENV['TRANSPORT_JAVA_URL'] = getenv('JAVA_GATEWAY_TEST_JAVA_URL');
$_ENV['TRANSPORT_JAVA_TOKEN'] = 'java-gateway-test-java-token-123456789';
// A database dependency must fail this real gateway request, not silently use the app DB.
spl_autoload_register(static function (string $class): void {
    if ($class === App\Modules\Database\Database::class || $class === App\Utils\RateLimiter::class) {
        throw new RuntimeException('Database access is forbidden in Java gateway tests.');
    }
}, true, true);
$_SERVER['SCRIPT_NAME'] = '/api/transport/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 5) . '/api/transport/index.php';
require $_SERVER['SCRIPT_FILENAME'];
