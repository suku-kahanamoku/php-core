#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API endpoint tests for App\Modules\File\FileApi
 *
 * Tests /files/* routes.
 * NOTE: Upload tests use a real tmp file (text/plain) created in /tmp.
 *       After upload, returned temp path is used to test commit flow.
 */

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($base)) {
    $base = rtrim($argv[1] ?? 'http://localhost/php/php-core/api', '/');
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}

// ── Helper: multipart upload via cURL ────────────────────────────────────────

function upload_file(string $base, string $tmpFile, string $mime, bool $withAuth = true): array
{
    global $token;

    $ch = curl_init("{$base}/files/upload");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($tmpFile, $mime, 'test_upload.txt')]);

    $headers = [];
    if ($withAuth && $token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'data' => json_decode($raw, true) ?? [], 'raw' => $raw];
}

// ── Auth setup ───────────────────────────────────────────────────────────────

section('Files – auth setup (login as admin)');
$r = request('POST', "{$base}/auth/login", ['email' => 'admin@example.com', 'password' => 'password'], false);
assert_test('Login as admin → 200', $r['status'] === 200, dump_on_fail($r));
if (isset($r['data']['data']['token'])) {
    $token = $r['data']['data']['token'];
}

// ── GET /files – list (admin) ─────────────────────────────────────────────────

section('Files – GET /files (admin list)');
$r = request('GET', "{$base}/files");
assert_test('GET /files → 200', $r['status'] === 200, dump_on_fail($r));
assert_test('Response has items array', isset($r['data']['data']) && is_array($r['data']['data']), dump_on_fail($r));

// ── GET /files/:id – missing ID ───────────────────────────────────────────────

section('Files – GET /files/9999999 (not found)');
$r = request('GET', "{$base}/files/9999999");
assert_test('GET /files/9999999 → 404', $r['status'] === 404, dump_on_fail($r));

// ── POST /files/upload – no file field ───────────────────────────────────────

section('Files – POST /files/upload: missing file field');
$r = request('POST', "{$base}/files/upload", []);
assert_test('Upload without file → 422', $r['status'] === 422, dump_on_fail($r));

// ── POST /files/upload – unauthenticated ─────────────────────────────────────

section('Files – POST /files/upload: unauthenticated');
$tmpFile = tempnam(sys_get_temp_dir(), 'phpcore_test_') . '.txt';
file_put_contents($tmpFile, 'test_upload content');
$r = upload_file($base, $tmpFile, 'text/plain', false);
assert_test('Upload without auth → 401', $r['status'] === 401, dump_on_fail($r));

// ── POST /files/upload – valid upload ─────────────────────────────────────────

section('Files – POST /files/upload: valid text/plain');
$r = upload_file($base, $tmpFile, 'text/plain', true);
assert_test('Upload valid file → 201', $r['status'] === 201, dump_on_fail($r));
assert_test('Response has path', isset($r['data']['data']['path']), dump_on_fail($r));

$tempPath = $r['data']['data']['path'] ?? null;

// ── POST /files/commit – missing path ─────────────────────────────────────────

section('Files – POST /files/commit: missing path');
$r = request('POST', "{$base}/files/commit", ['name' => 'test_document.txt']);
assert_test('Commit without path → 422', $r['status'] === 422, dump_on_fail($r));

// ── POST /files/commit – missing name ────────────────────────────────────────

section('Files – POST /files/commit: missing name');
$r = request('POST', "{$base}/files/commit", ['path' => 'temp/some/path.txt']);
assert_test('Commit without name → 422', $r['status'] === 422, dump_on_fail($r));

// ── POST /files/commit – invalid path ────────────────────────────────────────

section('Files – POST /files/commit: invalid path (not in temp/)');
$r = request('POST', "{$base}/files/commit", ['path' => 'files/some/path.txt', 'name' => 'test.txt']);
assert_test('Commit with non-temp path → 422', $r['status'] === 422, dump_on_fail($r));

// ── POST /files/commit – non-existent file ────────────────────────────────────

section('Files – POST /files/commit: non-existent temp file');
$r = request('POST', "{$base}/files/commit", ['path' => 'temp/dev/nonexistent-uuid.txt', 'name' => 'test_doc.txt']);
assert_test('Commit with missing file → 404', $r['status'] === 404, dump_on_fail($r));

// ── POST /files/commit – valid commit ────────────────────────────────────────

if ($tempPath !== null) {
    section('Files – POST /files/commit: valid commit');
    $r = request('POST', "{$base}/files/commit", [
        'path'        => $tempPath,
        'name'        => 'test_committed_document.txt',
        'visibility'  => 'private',
        'entity_type' => 'test',
    ]);
    assert_test('Commit valid path → 200', $r['status'] === 200, dump_on_fail($r));
    assert_test('Response has id', isset($r['data']['data']['id']), dump_on_fail($r));

    $committedId = $r['data']['data']['id'] ?? null;

    // ── GET /files/:id – after commit ─────────────────────────────────────────

    if ($committedId !== null) {
        section('Files – GET /files/:id after commit');
        $r = request('GET', "{$base}/files/{$committedId}");
        assert_test('GET committed file → 200', $r['status'] === 200, dump_on_fail($r));
        assert_test('name matches committed name', ($r['data']['data']['name'] ?? '') === 'test_committed_document.txt', dump_on_fail($r));

        // ── DELETE /files/:id ──────────────────────────────────────────────────

        section('Files – DELETE /files/:id');
        $r = request('DELETE', "{$base}/files/{$committedId}?force=true");
        assert_test('DELETE committed file → 200', $r['status'] === 200, dump_on_fail($r));

        section('Files – GET /files/:id after delete (should 404)');
        $r = request('GET', "{$base}/files/{$committedId}");
        assert_test('GET deleted file → 404', $r['status'] === 404, dump_on_fail($r));
    }

    // ── Re-commit same path (file already moved) ──────────────────────────────

    section('Files – POST /files/commit: already committed path (file gone)');
    $r = request('POST', "{$base}/files/commit", [
        'path' => $tempPath,
        'name' => 'test_duplicate.txt',
    ]);
    assert_test('Re-commit same path → 404', $r['status'] === 404, dump_on_fail($r));
}

// ── DELETE /files/:id – missing ID validation ─────────────────────────────────

section('Files – DELETE /files/0 (invalid id)');
$r = request('DELETE', "{$base}/files/0?force=true");
assert_test('DELETE /files/0 → 422', $r['status'] === 422, dump_on_fail($r));

// ── Cleanup ───────────────────────────────────────────────────────────────────

if (file_exists($tmpFile)) {
    @unlink($tmpFile);
}

if (!isset($runnerMode)) {
    print_results();
}
