<?php

declare(strict_types=1);

namespace App\Modules\Router;

class Request
{
    public readonly string $method;
    public readonly string $uri;
    public readonly string $franchiseCode;
    public readonly array  $query;
    public readonly array  $body;
    public readonly array  $headers;
    public readonly array  $files;

    public function __construct()
    {
        $this->method        = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri           = $this->parsePath();
        $this->franchiseCode = self::resolveCode();
        $this->query         = $_GET;
        $this->body          = $this->parseBody();
        $this->headers       = $this->parseHeaders();
        $this->files         = $_FILES;
    }

    /**
     * Resolve and validate the franchise code from the HTTP Host header.
     * Allowed codes are defined in FRANCHISE_CODES env var (comma-separated).
     * Aborts with 403 if the resolved code is not in the allowed list.
     * Called statically from Auth (which has no Request instance).
     */
    public static function resolveCode(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $host = explode(':', $host)[0];
        $host = preg_replace('/^www\./i', '', $host);
        $code = ($host === '' || $host === 'localhost' || $host === '127.0.0.1')
            ? 'default'
            : $host;

        $allowed = array_map('trim', explode(',', $_ENV['FRANCHISE_CODES'] ?? 'default'));

        if (!in_array($code, $allowed, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Franchise not recognised.', 'errors' => null]);
            exit;
        }

        return $code;
    }

    private function parsePath(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Strip the script's base directory so the app works in a subdirectory
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return rtrim($path, '/') ?: '/';
    }

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw     = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name                       = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains($this->headers['content-type'] ?? '', 'application/json');
    }

    /**
     * Parse the `projection` query/body parameter into an array.
     *
     * - No parameter present → null  (all columns)
     * - Empty string / empty array  → []    (system columns only)
     * - 'field1,field2'             → ['field1', 'field2']
     * - ['field1', 'field2']        → ['field1', 'field2']
     */
    public function projection(): ?array
    {
        $raw = $this->get('projection');

        if ($raw === null) {
            return null;
        }

        if ($raw === '' || $raw === []) {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw), fn ($v) => $v !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn ($v) => $v !== ''));
    }
}
