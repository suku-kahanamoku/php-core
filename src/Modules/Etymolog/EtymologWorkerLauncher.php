<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Spouštěč synchronizačního workera jako odděleného CLI procesu.
 *
 * Vstupní bod je pevně daný skriptem, okurk i neprůhledné ID požadavku se
 * předávají jako hodnoty z konfigurace a serveru a jsou shell-escapované.
 * ID požadavku se navíc ověřuje formátem, takže přes shell se nedostane nic
 * jiného.
 */
final class EtymologWorkerLauncher
{
    /**
     * @param  string $tenant   Kod okurku, ktery se preda skriptu.
     * @param  string $dispatch Zpusob spusteni: 'process', 'cron' nebo neprazdna jina hodnota (chyba konfigurace).
     * @return void
     */
    public function __construct(private readonly string $tenant, private readonly string $dispatch = 'process') {}

    /**
     * Spustí worker jako odpojený proces a čeká na jeho PID.
     *
     * @param  string $requestId `request_id` dávky ve tvaru 32 hex znaků.
     * @return void             Vedlejší efekt: spuštění procesu na pozadí.
     * @throws SyncException     'worker_unavailable' při nedostupném skriptu či shellu,
     *                           'worker_process_disabled' při zakázaném `exec()` a
     *                           'worker_configuration_invalid' při neznámém způsobu spuštění.
     * @throws \RuntimeException 'worker_launch_failed', pokud se nepodaří získat PID.
     */
    public function launch(string $requestId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $requestId)) { throw new SyncException('worker_unavailable'); }
        if ($this->dispatch === 'cron') { return; } // Explicitly configured external queue consumer.
        if ($this->dispatch !== 'process') { throw new SyncException('worker_configuration_invalid'); }
        if (!is_callable('exec')) { throw new SyncException('worker_process_disabled'); }
        $php = PHP_BINDIR.'/php';
        $script = dirname(__DIR__, 3).'/scripts/etymolog-sync.php';
        if (!is_executable($php) || !is_file($script) || !is_executable('/usr/bin/nohup')) {
            throw new SyncException('worker_unavailable');
        }
        $command = '/usr/bin/nohup '.escapeshellarg($php).' '.escapeshellarg($script).' '.escapeshellarg('--tenant='.$this->tenant).' '.escapeshellarg('--request='.$requestId).' </dev/null >/dev/null 2>&1 & echo $!';
        exec($command, $output, $code);
        if ($code !== 0 || !preg_match('/^[1-9][0-9]*$/D', trim(implode('', $output)))) { throw new \RuntimeException('worker_launch_failed'); }
    }
}
