<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use Closure;

/** Minimalni serverovy klient oficialniho OpenAI Vector Store API. */
final class OpenAiVectorStoreClient
{
    private const BASE_URL = 'https://api.openai.com/v1';

    private Closure $transport;
    private string $apiKey;

    /**
     * @param Closure|null $transport Testovaci transport `(method, path, payload, multipart): array`.
     */
    public function __construct(?Closure $transport = null, ?string $apiKey = null)
    {
        $this->apiKey = trim($apiKey ?? (string) ($_ENV['OPENAI_API_KEY'] ?? ''));
        $this->transport = $transport ?? Closure::fromCallable([$this, 'sendRequest']);
    }

    /** @return array<string, mixed> */
    public function createVectorStore(string $name): array
    {
        return $this->request('POST', '/vector_stores', ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function uploadJson(string $filename, string $content): array
    {
        return $this->request('POST', '/files', [
            'purpose' => 'assistants',
            'filename' => $filename,
            'content' => $content,
        ], true);
    }

    /** @param array<string, string|int|float|bool> $attributes @return array<string, mixed> */
    public function attachFile(string $vectorStoreId, string $fileId, array $attributes): array
    {
        return $this->request('POST', '/vector_stores/' . rawurlencode($vectorStoreId) . '/files', [
            'file_id' => $fileId,
            'attributes' => $attributes,
        ]);
    }

    /** @return array<string, mixed> */
    public function retrieveFile(string $vectorStoreId, string $fileId): array
    {
        return $this->request(
            'GET',
            '/vector_stores/' . rawurlencode($vectorStoreId) . '/files/' . rawurlencode($fileId),
        );
    }

    public function detachFile(string $vectorStoreId, string $fileId): void
    {
        $this->request(
            'DELETE',
            '/vector_stores/' . rawurlencode($vectorStoreId) . '/files/' . rawurlencode($fileId),
        );
    }

    public function deleteFile(string $fileId): void
    {
        $this->request('DELETE', '/files/' . rawurlencode($fileId));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function request(string $method, string $path, array $payload = [], bool $multipart = false): array
    {
        if ($this->apiKey === '') {
            throw new OpenAiConfigurationException('OPENAI_API_KEY is not configured.');
        }
        $result = ($this->transport)($method, $path, $payload, $multipart, $this->apiKey);
        $status = (int) ($result['status'] ?? 0);
        $body = (string) ($result['body'] ?? '');
        if ($status < 200 || $status >= 300) {
            throw new OpenAiUpstreamException('OpenAI Vector Store request failed.', $status);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new OpenAiUpstreamException('OpenAI Vector Store returned invalid JSON.', $status);
        }
        return $decoded;
    }

    /**
     * Produkcni cURL transport. Telo upstream chyby se zamerne nepropaguje.
     * @param array<string, mixed> $payload
     * @return array{status:int, body:string}
     */
    private function sendRequest(
        string $method,
        string $path,
        array $payload,
        bool $multipart,
        string $apiKey,
    ): array {
        $handle = curl_init(self::BASE_URL . $path);
        $headers = ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($payload !== []) {
            if ($multipart) {
                $options[CURLOPT_POSTFIELDS] = [
                    'purpose' => (string) $payload['purpose'],
                    'file' => new \CURLStringFile(
                        (string) $payload['content'],
                        (string) $payload['filename'],
                        'application/json',
                    ),
                ];
            } else {
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_HTTPHEADER] = $headers;
                $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
            }
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if (!is_string($body)) {
            $body = '';
        }
        curl_close($handle);
        return ['status' => $status, 'body' => $body];
    }
}
