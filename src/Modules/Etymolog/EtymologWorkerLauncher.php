<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/** Fixed CLI entry point; tenant and opaque request ID are shell-escaped server values. */
final class EtymologWorkerLauncher
{
    public function __construct(private readonly string $tenant) {}

    public function launch(string $requestId): void
    {
        $php = PHP_BINDIR.'/php';
        $script = dirname(__DIR__, 3).'/scripts/etymolog-sync.php';
        if (!preg_match('/^[a-f0-9]{32}$/D', $requestId) || !is_executable($php) || !is_file($script) || !is_executable('/usr/bin/nohup') || !is_callable('exec')) {
            throw new \RuntimeException('worker_unavailable');
        }
        $command = '/usr/bin/nohup '.escapeshellarg($php).' '.escapeshellarg($script).' '.escapeshellarg('--tenant='.$this->tenant).' '.escapeshellarg('--request='.$requestId).' </dev/null >/dev/null 2>&1 & echo $!';
        exec($command, $output, $code);
        if ($code !== 0 || !preg_match('/^[1-9][0-9]*$/D', trim(implode('', $output)))) { throw new \RuntimeException('worker_launch_failed'); }
    }
}
