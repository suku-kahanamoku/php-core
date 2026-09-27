<?php

declare(strict_types=1);

namespace App\Modules\Http;

/** Immutable per-request settings. Credentials are never stored in the shared client. */
final class HttpRequest
{
    /**
     * @param array<string,string|string[]>|list<string> $headers
     * @param array|null $multipart Guzzle multipart parts (name, contents, filename, headers).
     * @param list<string> $redirectHosts Explicit HTTPS redirect allowlist; GET/HEAD only.
     * @param string|null $sink Private local download destination; body is not buffered in PHP memory.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method = 'GET',
        public readonly array $headers = [],
        public readonly array|string|null $body = null,
        public readonly int $timeoutMs = 4000,
        public readonly int $maxBytes = 4000000,
        public readonly int $connectTimeoutMs = 1500,
        public readonly ?array $multipart = null,
        public readonly ?string $sink = null,
        public readonly array $redirectHosts = [],
        public readonly int $maxRedirects = 3,
    ) {
        if ($timeoutMs < 1 || $connectTimeoutMs < 1 || $maxBytes < 1 || $maxRedirects < 0) {
            throw new \InvalidArgumentException('HTTP limits must be positive.');
        }
        if ($body !== null && $multipart !== null) {
            throw new \InvalidArgumentException('Choose body or multipart, not both.');
        }
        if ($redirectHosts !== [] && !in_array($method, ['GET', 'HEAD'], true)) {
            throw new \InvalidArgumentException('Only GET/HEAD requests may follow redirects.');
        }
    }
}
