<?php

declare(strict_types=1);

namespace App\Modules\FannCatalog;

use RuntimeException;

/** Omezeny HTTP klient pro cteni verejneho katalogu FAnn. */
final class FannCatalogHttpClient
{
    private const ALLOWED_HOST = 'www.fann.cz';

    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly string $userAgent = 'FAnnCatalogImporter/1.0 (+https://www.charter-agency.com/)',
    ) {
    }

    /** Stahne jednu verejnou HTML stranku a overi cil, HTTP stav i obsah. */
    public function get(string $url): string
    {
        $this->assertAllowedUrl($url);
        $handle = $this->createHandle($url);
        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);
        if (!is_string($body) || $body === '' || $status !== 200) {
            throw new RuntimeException("FAnn request failed ({$status}): {$url} {$error}");
        }
        if ($type !== '' && !str_contains(strtolower($type), 'text/html')) {
            throw new RuntimeException("Unexpected FAnn content type {$type}: {$url}");
        }
        return $body;
    }

    /**
     * Stahne vice detailu soubezne s omezenou konkurenci.
     * @param list<string> $urls
     * @return array<string, string>
     */
    public function getMany(array $urls, int $concurrency = 4): array
    {
        $urls = array_values(array_unique($urls));
        foreach ($urls as $url) {
            $this->assertAllowedUrl($url);
        }
        $concurrency = max(1, min(6, $concurrency));
        $multi = curl_multi_init();
        $pending = $urls;
        $active = [];
        $result = [];
        $fill = function () use (&$pending, &$active, $multi, $concurrency): void {
            while (count($active) < $concurrency && $pending !== []) {
                $url = array_shift($pending);
                $handle = $this->createHandle($url);
                $active[(int) $handle] = [$handle, $url];
                curl_multi_add_handle($multi, $handle);
            }
        };
        $fill();
        do {
            do {
                $code = curl_multi_exec($multi, $running);
            } while ($code === CURLM_CALL_MULTI_PERFORM);
            if ($code !== CURLM_OK) {
                throw new RuntimeException('FAnn multi request failed: ' . curl_multi_strerror($code));
            }
            while (($info = curl_multi_info_read($multi)) !== false) {
                $handle = $info['handle'];
                [, $url] = $active[(int) $handle];
                $body = curl_multi_getcontent($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                if ($info['result'] !== CURLE_OK || $status !== 200 || !is_string($body) || $body === '') {
                    throw new RuntimeException("FAnn request failed ({$status}): {$url} " . curl_error($handle));
                }
                $result[$url] = $body;
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
                unset($active[(int) $handle]);
                $fill();
            }
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 || $active !== []);
        curl_multi_close($multi);
        return $result;
    }

    /** Vytvori jednotne nastaveny cURL handle. */
    private function createHandle(string $url): \CurlHandle
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        return $handle;
    }

    /** Brani pouziti importniho klienta jako obecneho SSRF klienta. */
    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== self::ALLOWED_HOST) {
            throw new RuntimeException("Unsupported FAnn URL: {$url}");
        }
    }
}
