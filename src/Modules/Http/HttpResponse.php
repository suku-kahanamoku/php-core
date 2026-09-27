<?php

declare(strict_types=1);

namespace App\Modules\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $error = null,
        public readonly ?int $retryAfter = null,
        public readonly array $headers = [],
    ) {
    }

    public function successful(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return implode(', ', (array)$value);
            }
        }
        return '';
    }

    public function json(): array
    {
        if (!$this->successful()) {
            throw new HttpException($this->error ?? 'http_status', $this->status);
        }
        try {
            $data = json_decode($this->body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException('invalid_json', $this->status);
        }
        if (!is_array($data)) {
            throw new HttpException('invalid_json', $this->status);
        }
        return $data;
    }
}
