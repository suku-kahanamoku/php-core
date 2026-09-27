<?php

declare(strict_types=1);

namespace App\Modules\Sry;

use App\Modules\Http\{HttpModule, HttpRequest};
use App\Modules\Http\Contracts\HttpClient;

/** R2 and realtime secrets never leave this server. All tickets are scoped and short-lived. */
class CloudflareGateway
{
    public function __construct(private string $url, private string $secret, private readonly ?HttpClient $http = null) {}
    public function ticket(array $claims): string
    {
        if (
            !str_starts_with($this->url, "https://") ||
            strlen($this->secret) < 32
        ) {
            throw new SryError("notConfigured", 503);
        }
        $claims["exp"] = time() + 120;
        $claims["tenant"] = "sry";
        $payload = rtrim(
            strtr(
                base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)),
                "+/",
                "-_",
            ),
            "=",
        );
        return $payload .
            "." .
            rtrim(
                strtr(
                    base64_encode(
                        hash_hmac("sha256", $payload, $this->secret, true),
                    ),
                    "+/",
                    "-_",
                ),
                "=",
            );
    }
    public function url(string $path, array $claims): string
    {
        return rtrim($this->url, "/") .
            $path .
            "?ticket=" .
            rawurlencode($this->ticket($claims));
    }
    public function call(
        string $path,
        array $claims,
        ?array $body = null,
    ): array {
        $response = ($this->http ?? HttpModule::client())->send(new HttpRequest(
            $this->url($path, $claims), $body === null ? 'GET' : 'POST',
            ['Content-Type' => 'application/json'], $body, timeoutMs: 20000,
        ));
        $raw = $response->body;
        $status = $response->status;
        if ($response->error !== null || $status < 200 || $status >= 300) {
            throw new SryError("cloudUnavailable", 503);
        }
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
