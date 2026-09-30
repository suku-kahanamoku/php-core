<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

use Closure;
use App\Modules\Http\{HttpModule, HttpRequest};
use App\Modules\Http\Contracts\HttpClient;

/** Minimalni serverovy klient oficialniho OpenAI Vector Store API. */
final class OpenAiVectorStoreProvider
{
    private const BASE_URL = 'https://api.openai.com/v1';

    private Closure $transport;
    private string $apiKey;

    /**
     * @param Closure|null $transport Testovaci transport `(method, path, payload, multipart): array`.
     */
    public function __construct(?Closure $transport = null, ?string $apiKey = null, private readonly ?HttpClient $http = null)
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

    /**
     * Odpojí soubor od Vector Store (soubor v OpenAI zůstává existovat).
     *
     * @param  string $vectorStoreId ID Vector Store.
     * @param  string $fileId        ID souboru v OpenAI.
     * @return void                  Vedlejší efekt: `DELETE /vector_stores/{id}/files/{id}`.
     * @throws OpenAiConfigurationException Pokud není nastaven `OPENAI_API_KEY`.
     * @throws OpenAiUpstreamException      Při chybě OpenAI (mimo 2xx).
     */
    public function detachFile(string $vectorStoreId, string $fileId): void
    {
        $this->request(
            'DELETE',
            '/vector_stores/' . rawurlencode($vectorStoreId) . '/files/' . rawurlencode($fileId),
        );
    }

    /**
     * Smaže nahraný soubor v OpenAI.
     *
     * @param  string $fileId ID souboru v OpenAI.
     * @return void            Vedlejší efekt: `DELETE /files/{id}`.
     * @throws OpenAiConfigurationException Pokud není nastaven `OPENAI_API_KEY`.
     * @throws OpenAiUpstreamException      Při chybě OpenAI (mimo 2xx).
     */
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
     * Společný HTTP transport. Telo upstream chyby se zamerne nepropaguje.
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
        $parts = $multipart ? [
            ['name' => 'purpose', 'contents' => (string)$payload['purpose']],
            ['name' => 'file', 'contents' => (string)$payload['content'], 'filename' => (string)$payload['filename'], 'headers' => ['Content-Type' => 'application/json']],
        ] : null;
        $response = ($this->http ?? HttpModule::client())->send(new HttpRequest(
            self::BASE_URL.$path,
            $method,
            ['Authorization' => 'Bearer '.$apiKey, 'Accept' => 'application/json'],
            body: !$multipart && $payload !== [] ? $payload : null,
            timeoutMs: 60000,
            connectTimeoutMs: 10000,
            multipart: $parts,
        ));
        if ($response->error !== null) {
            throw new OpenAiUpstreamException('OpenAI Vector Store connection failed.');
        }
        return ['status' => $response->status, 'body' => $response->body];
    }
}
