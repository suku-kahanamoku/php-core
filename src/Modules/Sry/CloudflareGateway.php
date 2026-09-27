<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/** R2 and realtime secrets never leave this server. All tickets are scoped and short-lived. */
class CloudflareGateway
{
    public function __construct(private string $url, private string $secret) {}
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
        $ch = curl_init($this->url($path, $claims));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($body, JSON_THROW_ON_ERROR),
            );
        }
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) {
            throw new SryError("cloudUnavailable", 503);
        }
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
