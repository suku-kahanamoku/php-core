<?php

declare(strict_types=1);

namespace App\Modules\File;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

/**
 * FileService – business logika pro spravu souboru.
 *
 * Upload flow:
 *   1. upload()  → ulozi soubor do /temp/{code}/{uuid}.{ext}, vytvori DB zaznam s temp_token
 *   2. commit()  → presune soubor do /files/{code}/{uuid}.{ext}, vymaze temp_token v DB
 *
 * Smazani:
 *   delete() → soft-delete DB zaznamu + fyzicke smazani souboru
 */
class FileService extends BaseService
{
    private FileRepository $files;

    /** Povolene MIME typy (whitelist) */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    /** Maximalni velikost souboru: 20 MB */
    private const MAX_SIZE = 20 * 1024 * 1024;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->files = new FileRepository($db, $franchiseCode);
        $this->auth  = $auth;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Vrati strankovany seznam (jen commitnute soubory). Vyzaduje admin.
     *
     * @param  int        $page
     * @param  int        $limit
     * @param  string     $sort
     * @param  string     $filter
     * @param  array|null $projection
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(
        int $page,
        int $limit,
        string $sort,
        string $filter,
        ?array $projection
    ): array {
        $this->auth->requireRole('admin');
        return $this->files->findAll($page, $limit, $sort, $filter, $projection);
    }

    /**
     * Vrati metadata souboru dle ID. Vyzaduje prihlaseni.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection): array
    {
        $this->auth->require();
        $file = $this->files->findById($id, $projection);
        $this->requireEntity($file, 'File not found');
        return $file;
    }

    /**
     * Nacte soubor dle ID pro stazeni/nahled (kontroluje existenci fyzickeho souboru).
     *
     * @param  int $id
     * @return array{path: string, name: string, mime_type: string, file: array<string, mixed>}
     */
    public function resolve(int $id): array
    {
        $this->auth->require();
        $file     = $this->files->findById($id);
        $this->requireEntity($file, 'File not found');

        $absPath = $this->root() . '/' . ltrim($file['path'], '/');
        if (!file_exists($absPath)) {
            Response::notFound('File not found on disk');
        }

        return [
            'path'      => $absPath,
            'name'      => $file['name'],
            'mime_type' => $file['mime_type'],
            'file'      => $file,
        ];
    }

    /**
     * Nahraje soubor do /temp/{code}/{uuid}.{ext} a vytvori DB zaznam.
     * Vraci temp_token pro nasledny commit.
     *
     * @param  array<string, mixed> $uploadedFile  $_FILES entry
     * @param  int|null             $userId
     * @return array{temp_token: string, id: int}
     */
    public function upload(array $uploadedFile, ?int $userId): array
    {
        $this->auth->require();
        $this->validateUpload($uploadedFile);

        $mime   = mime_content_type($uploadedFile['tmp_name']) ?: $uploadedFile['type'];
        $ext    = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $uuid   = $this->generateUuid();
        $token  = $this->generateUuid();
        $code   = $this->files->getCode();

        $dir    = $this->tempRoot() . '/' . $code;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            Response::error('Could not create temp directory', 500);
        }

        $tmpPath = $dir . '/' . $uuid . '.' . $ext;
        if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpPath)) {
            Response::error('Failed to save uploaded file', 500);
        }

        $id = $this->files->insert([
            'temp_token'  => $token,
            'type'        => $ext,
            'mime_type'   => $mime,
            'path'        => 'temp/' . $code . '/' . $uuid . '.' . $ext,
            'name'        => basename($uploadedFile['name']),
            'size'        => (int) $uploadedFile['size'],
            'visibility'  => 'private',
            'expires_at'  => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);

        return ['temp_token' => $token, 'id' => $id];
    }

    /**
     * Commitne tmp soubor: presune z temp/ do files/, vymaze temp_token.
     *
     * @param  string      $tempToken
     * @param  string      $name        Pozadovany nazev souboru (zobrazovany)
     * @param  string      $visibility  'public' | 'private'
     * @param  string|null $entityType
     * @param  int|null    $entityId
     * @return array<string, mixed>  finalni zaznam
     */
    public function commit(
        string $tempToken,
        string $name,
        string $visibility = 'private',
        ?string $entityType = null,
        ?int $entityId = null,
    ): array {
        $this->auth->require();

        $file = $this->files->findByTempToken($tempToken);
        $this->requireEntity($file, 'Temp token not found or already committed');

        $code    = $this->files->getCode();
        $tmpAbs  = $this->root() . '/' . ltrim($file['path'], '/');
        $ext     = $file['type'];
        $uuid    = pathinfo($tmpAbs, PATHINFO_FILENAME);

        $destDir = $this->filesRoot() . '/' . $code;
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            Response::error('Could not create files directory', 500);
        }

        $destRel = 'files/' . $code . '/' . $uuid . '.' . $ext;
        $destAbs = $this->root() . '/' . $destRel;

        if (!rename($tmpAbs, $destAbs)) {
            Response::error('Failed to move file from temp to files', 500);
        }

        $update = [
            'temp_token'  => null,
            'path'        => $destRel,
            'name'        => $name,
            'visibility'  => in_array($visibility, ['public', 'private']) ? $visibility : 'private',
            'expires_at'  => null,
        ];
        if ($entityType !== null) {
            $update['entity_type'] = $entityType;
        }
        if ($entityId !== null) {
            $update['entity_id'] = $entityId;
        }

        $this->files->update((int) $file['id'], $update);
        return $this->files->findById((int) $file['id']) ?? [];
    }

    /**
     * Soft-delete + fyzicke smazani souboru. Vyzaduje admin.
     *
     * @param  int $id
     * @return void
     */
    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');
        $file = $this->files->findById($id);
        $this->requireEntity($file, 'File not found');

        $this->files->softDelete($id);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function validateUpload(array $file): void
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            Response::error('No valid file uploaded', 422);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload error code: ' . $file['error'], 422);
        }
        if ($file['size'] > self::MAX_SIZE) {
            Response::error('File too large (max 20 MB)', 422);
        }

        $mime = mime_content_type($file['tmp_name']) ?: $file['type'];
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            Response::error('File type not allowed: ' . $mime, 422);
        }
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** Absolutni cesta ke korenu projektu (kde jsou temp/ a files/). */
    private function root(): string
    {
        return rtrim($_ENV['FILE_ROOT'] ?? dirname(__DIR__, 3), '/');
    }

    private function tempRoot(): string
    {
        return $this->root() . '/temp';
    }

    private function filesRoot(): string
    {
        return $this->root() . '/files';
    }
}
