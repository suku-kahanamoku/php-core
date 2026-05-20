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
 *   1. upload()  → ulozi soubor do /temp/{code}/{uuid}.{ext}, vrati relativni path
 *   2. commit()  → presune soubor do /files/{code}/[entity_type/] a vytvori DB zaznam
 *
 * Smazani:
 *   remove() → soft-delete DB zaznamu
 *   delete() → hard-delete DB zaznamu + fyzicke smazani souboru
 */
class FileService extends BaseService
{
    private FileRepository $_files;

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
        $this->_files = new FileRepository($db, $franchiseCode);
        $this->_auth  = $auth;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Vrati strankovany seznam (jen commitnute soubory). Vyzaduje prihlaseni.
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
        $this->_auth->require();
        return $this->_files->findAll($page, $limit, $sort, $filter, $projection);
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
        $this->_auth->require();
        $file = $this->_files->findById($id, $projection);
        $this->_requireEntity($file, 'File not found');
        return $file;
    }

    /**
     * Nahraje soubor do /temp/{code}/{uuid}.{ext} a vrati relativni cestu.
     * Zadne ukladani do DB — to probehne az pri commit().
     *
     * @param  array<string, mixed> $uploadedFile  $_FILES entry
     * @return array{path: string}
     */
    public function upload(array $uploadedFile): array
    {
        $this->_auth->require();
        $this->_validateUpload($uploadedFile);

        $ext  = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $uuid = $this->_generateUuid();
        $code = $this->_files->getCode();

        $dir = $this->_tempRoot() . '/' . $code;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            Response::error('Could not create temp directory', 500);
        }

        $relPath = 'temp/' . $code . '/' . $uuid . '.' . $ext;
        $absPath = $this->_root() . '/' . $relPath;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $absPath)) {
            Response::error('Failed to save uploaded file', 500);
        }

        return ['path' => $relPath];
    }

    /**
     * Commitne tmp soubor: presune z temp/ do files/ a vytvori zaznam v DB.
     * Cesta musi byt relativni od FILE_ROOT a zacinat 'temp/'.
     *
     * @param  string      $path        Relativni cesta vracena z upload() (napr. temp/dev/uuid.txt)
     * @param  string      $name        Zobrazovany nazev souboru
     * @param  string      $visibility  'public' | 'private'
     * @param  string|null $entityType
     * @param  int|null    $entityId
     * @return array<string, mixed>  finalni zaznam
     */
    public function commit(
        string $path,
        string $name,
        string $visibility = 'private',
        ?string $entityType = null,
        ?int $entityId = null,
    ): array {
        $this->_auth->require();

        // Bezpecnostni kontrola — povolujeme jen soubory z adresare temp/
        if (!str_starts_with($path, 'temp/')) {
            Response::error('Invalid temp path', 422);
        }

        $absTemp = $this->_root() . '/' . $path;
        if (!file_exists($absTemp)) {
            Response::notFound('Temp file not found');
        }

        $code    = $this->_files->getCode();
        $uuid    = pathinfo($absTemp, PATHINFO_FILENAME);
        $ext     = strtolower(pathinfo($absTemp, PATHINFO_EXTENSION));
        $mime    = mime_content_type($absTemp) ?: 'application/octet-stream';
        $size    = (int) filesize($absTemp);

        $entityPrefix = $this->_normalizeEntityPrefix($entityType);
        $destDir = $this->_filesRoot() . '/' . $code;
        if ($entityPrefix !== null) {
            $destDir .= '/' . $entityPrefix;
        }
        // Add entity ID to path if provided
        if ($entityId !== null && $entityPrefix !== null) {
            $destDir .= '/' . $entityId;
        }
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            Response::error('Could not create files directory', 500);
        }

        // Filename: with prefix if no ID, without prefix if ID is provided
        $destFile = ($entityId === null && $entityPrefix !== null)
            ? ($entityPrefix . '_' . $uuid . '.' . $ext)
            : ($uuid . '.' . $ext);

        $destRel = 'files/' . $code . '/';
        if ($entityPrefix !== null) {
            $destRel .= $entityPrefix . '/';
        }
        if ($entityId !== null && $entityPrefix !== null) {
            $destRel .= $entityId . '/';
        }
        $destRel .= $destFile;
        $destAbs = $this->_root() . '/' . $destRel;

        if (!rename($absTemp, $destAbs)) {
            Response::error('Failed to move file from temp to files', 500);
        }

        $data = [
            'type'       => $ext,
            'mime_type'  => $mime,
            'path'       => $destRel,
            'name'       => $name,
            'size'       => $size,
            'visibility' => in_array($visibility, ['public', 'private']) ? $visibility : 'private',
        ];
        if ($entityType !== null) {
            $data['entity_type'] = $entityType;
        }
        if ($entityId !== null) {
            $data['entity_id'] = $entityId;
        }

        $id = $this->_files->insert($data);
        return $this->_files->findById($id) ?? [];
    }

    /**
     * Hard-delete: fyzicky smaze soubor z disku + smaze zaznam z DB.
     * Vyzaduje admin.
     *
     * @param  int $id
     * @return void
     */
    public function delete(int $id): void
    {
        $this->_auth->requireRole('admin');
        $file = $this->_files->findById($id);
        $this->_requireEntity($file, 'File not found');

        $absPath = $this->_root() . '/' . ltrim($file['path'], '/');
        if (file_exists($absPath)) {
            unlink($absPath);
        }

        $this->_files->hardDelete($id);
    }

    /**
     * Soft-smazani souboru (oznaci jako smazany, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $file = $this->_files->findById($id);
        $this->_requireEntity($file, 'File not found');

        return $this->_files->softDelete($id);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function _validateUpload(array $file): void
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

    private function _generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** Absolutni cesta ke korenu projektu (kde jsou temp/ a files/). */
    private function _root(): string
    {
        return rtrim($_ENV['FILE_ROOT'] ?? dirname(__DIR__, 3), '/');
    }

    private function _tempRoot(): string
    {
        return $this->_root() . '/temp';
    }

    private function _filesRoot(): string
    {
        return $this->_root() . '/files';
    }

    /**
     * Normalizuje entity_type na bezpecny prefix/slozku (napr. "Product" -> "product").
     */
    private function _normalizeEntityPrefix(?string $entityType): ?string
    {
        if ($entityType === null) {
            return null;
        }

        $prefix = strtolower(trim($entityType));
        $prefix = preg_replace('/[^a-z0-9_-]+/', '', $prefix) ?? '';

        return $prefix !== '' ? $prefix : null;
    }
}
