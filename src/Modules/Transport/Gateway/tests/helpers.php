<?php

declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

use App\Modules\Transport\Gateway\JavaTransportException;

$checks = 0;
function check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL ' . $name);
    }
    ++$checks;
    echo "PASS $name\n";
}
function fails(callable $callback, string $reason): void
{
    try {
        $callback();
    } catch (JavaTransportException $error) {
        check($error->reason === $reason, 'reject ' . $reason);
        return;
    }
    throw new RuntimeException('Expected ' . $reason);
}
