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
     * Zjisti a overi franchise kod z HTTP hlavicky Host.
     * Povolene kody jsou definovany v env promenne FRANCHISE_CODES (oddelene carkou).
     * Ukonci pozadavek s 403 pokud zjisteny kod neni v povolene liste.
     * Volano staticky z Auth (kde neni dostupna instance Request).
     */
    public static function resolveCode(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $host = explode(':', $host)[0];
        $host = preg_replace('/^www\./i', '', $host);
        $code = ($host === '' || $host === 'localhost' || $host === '127.0.0.1')
            ? 'default'
            : $host;

        // Podporuje format "host:alias,host2:alias2" i puvodni "host,host2"
        $map = [];
        foreach (array_map('trim', explode(',', $_ENV['FRANCHISE_CODES'] ?? 'default')) as $entry) {
            if (str_contains($entry, ':')) {
                [$host, $alias] = explode(':', $entry, 2);
                $map[trim($host)] = trim($alias);
            } else {
                $map[$entry] = $entry;
            }
        }

        if (!isset($map[$code])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(
                [
                    'success' => false,
                    'message' => "Franchise \"$code\" not recognised.",
                    'errors'  => null,
                ]
            );
            exit;
        }

        return $map[$code];
    }

    private function parsePath(): string
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Odstran zakladni adresar skriptu, aby aplikace fungovala v podadresari
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

    /**
     * Vrati hodnotu z body nebo query stringu dle klice.
     *
     * @param  string $key      Nazev parametru
     * @param  mixed  $default  Vychozi hodnota kdyz klic neni pritomen
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Vrati vsechny parametry z body i query stringu jako jeden pole.
     * 
     * @return array
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Vrati hodnotu HTTP hlavicky dle nazvu (case-insensitive).
     *
     * @param  string $name     Nazev hlavicky (napr. 'Authorization')
     * @param  mixed  $default  Vychozi hodnota kdyz hlavicka neni pritomna
     * @return mixed
     */
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Vrati true, pokud je Content-Type roven application/json.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return str_contains($this->headers['content-type'] ?? '', 'application/json');
    }

    /**
     * Parsuje parametr `projection` z query/body do pole.
     *
     * - Parametr neni pritomen     → null  (vsechny sloupce)
     * - Prazdny retezec / pole     → []    (pouze systemove sloupce)
     * - 'field1,field2'            → ['field1', 'field2']
     * - ['field1', 'field2']       → ['field1', 'field2']
     * - '["field1","field2"]'      → ['field1', 'field2']  (JSON pole jako retezec)
     *
     * @return array<string>|null
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
            return array_values(
                array_filter(array_map('trim', $raw), fn($v) => $v !== '')
            );
        }

        // Pokus o dekodovani JSON pole jako retezec: ["field1","field2",...]
        if (is_string($raw) && str_starts_with(ltrim($raw), '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(
                    array_filter(array_map('trim', $decoded), fn($v) => $v !== '')
                );
            }
        }

        return array_values(
            array_filter(
                array_map('trim', explode(',', (string) $raw)),
                fn($v) => $v !== ''
            )
        );
    }

    /**
     * Parsuje parametr `factory` z query/body.
     *
     * Format: {"url": "/wine/${name}--$${id}", "slug": "${name}-${id}"}
     * - `${field}` je nahrazeno hodnotou pole `field` z kazdeho zaznamu
     * - `$${field}` je nahrazeno literalnim `$` + hodnotou pole `field`
     *
     * @return array<string, string>|null  Mapa nazvu generovaneho pole => sablona, nebo null pokud neni zadano
     */
    public function factory(): ?array
    {
        $raw = $this->get('factory');

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return array_filter($raw, fn($v) => is_string($v));
        }

        if (is_string($raw) && str_starts_with(ltrim($raw), '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_filter($decoded, fn($v) => is_string($v));
            }
        }

        return null;
    }
}
