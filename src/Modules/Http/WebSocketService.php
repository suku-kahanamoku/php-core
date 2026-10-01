<?php

declare(strict_types=1);

namespace App\Modules\Http;

/** One event-loop worker; TLS and connection/IP limits belong to the reverse proxy. */
final class WebSocketService
{
    public function run(string $listen, array $origins, callable $message, callable $closed, callable $tick, string $name = 'websocket', ?string $runtimeDirectory = null): void
    {
        if (!preg_match('~^websocket://127\.0\.0\.1:\d{2,5}$~D', $listen) || !$origins) {
            throw new \InvalidArgumentException('Loopback listener and explicit origins required.');
        }
        if (!preg_match('/^[a-z0-9_-]{1,64}$/D', $name)) {
            throw new \InvalidArgumentException('Invalid worker name.');
        }
        $runtimeDirectory ??= sys_get_temp_dir() . '/php-core-' . $name;
        if (!is_dir($runtimeDirectory) && !mkdir($runtimeDirectory, 0700, true) && !is_dir($runtimeDirectory)) {
            throw new \RuntimeException('Cannot create WebSocket runtime directory.');
        }
        \Workerman\Worker::$pidFile = $runtimeDirectory . '/worker.pid';
        \Workerman\Worker::$logFile = $runtimeDirectory . '/worker.log';
        $worker = new \Workerman\Worker($listen);
        $worker->count = 1;
        $worker->name = $name;
        $worker->onConnect = static function ($peer): void {
            $peer->maxPackageSize = 8192;
            $peer->maxSendBufferSize = 65536;
            $peer->onBufferFull = static fn ($p) => $p->close();
        };
        $admitted = [];
        $worker->onWebSocketConnect = static function ($peer, $request) use ($origins, &$admitted): void {
            if (!in_array($request->header('origin'), $origins, true) || count($admitted) >= 500) {
                $peer->close();
                return;
            }
            $admitted[$peer->id] = time();
        };
        $worker->onMessage = static function ($peer, $data) use ($message, &$admitted): void {
            if (!isset($admitted[$peer->id]) || strlen($data) > 8192) {
                $peer->close();
                return;
            }
            try {
                $body = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($body)) {
                    throw new \RuntimeException();
                }
                $message((string)$peer->id, $body, static fn (array $v) => $peer->send(json_encode($v, JSON_THROW_ON_ERROR)), static function () use ($peer): void {
                    $peer->websocketType = "\x88";
                    $peer->close(pack('n', 1008));
                });
                $admitted[$peer->id] = time();
            } catch (\Throwable) {
                $peer->close();
            }
        };
        $worker->onClose = static function ($peer) use ($closed, &$admitted): void {
            unset($admitted[$peer->id]);
            $closed((string)$peer->id);
        };
        $worker->onWorkerStart = static function () use ($worker, $tick, &$admitted): void {
            \Workerman\Timer::add(1, static function () use ($worker, $tick, &$admitted): void {
                foreach ($worker->connections as $peer) {
                    if (time() - ($admitted[$peer->id] ?? 0) > 45) {
                        $peer->close();
                    }
                }
                $tick();
            });
        };
        \Workerman\Worker::runAll();
    }
}
