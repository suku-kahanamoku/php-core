<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Providers;
use App\Modules\Http\Contracts\HttpClient;
use App\Modules\Http\HttpRequest;
use App\Modules\Etymolog\SyncException;

final class CloudflareDispatchProvider
{
    public function __construct(private readonly HttpClient $http, private readonly string $url, private readonly string $secret) {}
    public function launch(string $requestId): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $requestId) || strlen($this->secret) < 32 ||
            !preg_match('~^https://[a-z0-9-]+\.[a-z0-9-]+\.workers\.dev/dispatch$~D', $this->url)) {
            throw new SyncException('worker_configuration_invalid');
        }
        $r = $this->http->send(new HttpRequest($this->url, 'POST', ['X-Etymolog-Key' => $this->secret], ['request_id' => $requestId], timeoutMs: 10000, connectTimeoutMs: 3000, maxBytes: 10000));
        if ($r->status !== 202) { throw new SyncException('worker_launch_failed'); }
    }
}
