<?php

declare(strict_types=1);

namespace App\Modules\Http;

/**
 * Neměnný výsledek jednoho odchozího HTTP volání.
 *
 * Transport nikdy nevyhazuje výjimky pro chyby sítě nebo limitů — místo toho vrací
 * odpověď se stavem 0 a vyplněným `error` (`network_error`, `deadline_exceeded`,
 * `response_too_large`, `invalid_endpoint`, `redirect_rejected`, `request_failed`).
 */
final class HttpResponse
{
    /**
     * @param int                         $status     HTTP stavový kód; 0 znamená, že volání nedorazilo k odpovědi.
     * @param string                      $body       Tělo odpovědi jako řetězec (prázdné, pokud se stahovalo do souboru).
     * @param string|null                 $error      Kód chyby transportu, nebo null při úspěchu.
     * @param int|null                    $retryAfter Doporučené čekání před opakováním v sekundách.
     * @param array<string,array-key,mixed> $headers Hlavičky odpovědi.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $error = null,
        public readonly ?int $retryAfter = null,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @return bool true, pokud volání uspělo a stav je 2xx.
     */
    public function successful(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    /**
     * Vrátí hodnotu hlavičky bez ohledu na velikost písmen.
     *
     * @param  string $name Název hlavičky.
     * @return string       Hodnoty sloučené čárkou, nebo prázdný řetězec, pokud hlavička chybí.
     */
    public function header(string $name): string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return implode(', ', (array)$value);
            }
        }
        return '';
    }

    /**
     * Dekóduje tělo odpovědi jako JSON asociativní pole.
     *
     * @return array<string, mixed>
     * @throws HttpException Pokud volání nebylo úspěšné, tělo není platný JSON nebo JSON není objekt.
     */
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
