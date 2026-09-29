<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/** Private, tenant-scoped immutable downloads. Providers own HTTP and licence validation. */
final class EtymologSnapshotRepository
{
    private readonly string $directory;
    private readonly \Closure $clock;
    private const MAX_BYTES = 25000000;
    private const TTL = 604800;

    public function __construct(string $root, string $tenant, ?\Closure $clock = null)
    {
        $this->directory = rtrim($root, '/').'/'.hash('sha256', $tenant);
        $this->clock = $clock ?? static fn (): int => time();
    }

    private function path(string $key, string $hash): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) { throw new SyncException('invalid_snapshot_hash'); }
        return $this->directory.'/'.hash('sha256', $key).'-'.$hash.'.snapshot';
    }

    public function get(string $key, string $hash): ?string
    {
        $path = $this->path($key, $hash);
        if (!is_file($path) || is_link($path)) { return null; }
        $stat = stat($path);
        if ($stat['mtime'] < ($this->clock)() - self::TTL || $stat['size'] > self::MAX_BYTES) { return null; }
        $bytes = file_get_contents($path);
        return is_string($bytes) && hash_equals($hash, hash('sha256', $bytes)) ? $bytes : null;
    }

    public function put(string $key, string $bytes): void
    {
        if (strlen($bytes) > self::MAX_BYTES) { throw new SyncException('snapshot_too_large'); }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new SyncException('snapshot_cache_unavailable');
        }
        $path = $this->path($key, hash('sha256', $bytes));
        $temporary = tempnam($this->directory, '.snapshot-');
        if ($temporary === false) { throw new SyncException('snapshot_cache_unavailable'); }
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temporary, $path)) {
                throw new SyncException('snapshot_cache_unavailable');
            }
        } finally {
            if (is_file($temporary)) { unlink($temporary); }
        }
        // Keep historical snapshots only for bounded continuation/retries.
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->isFile() && !$file->isLink() && str_ends_with($file->getFilename(), '.snapshot') && $file->getMTime() < ($this->clock)() - self::TTL) {
                unlink($file->getPathname());
            }
        }
    }
}
