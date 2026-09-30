<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Soukromé, omezené na okruk a neměnné kopie stažených souborů.
 *
 * Poskytovatelé si vlastní HTTP komunikaci a ověření licencí; tento repozitář
 * je jen mezipaměť na disku. Každý soubor má v názvu SHA-256 svého obsahu a při
 * čtení se hash znovu ověřuje, takže poškozená kopie se použije jen jako chybějící.
 * Adresář se vytvoří s právy 0700 a staré snapshoty se po týdnu uklízejí.
 */
final class EtymologSnapshotRepository
{
    /** Adresář pro snapshoty daného okurku. */
    private readonly string $directory;

    /** Zdroj aktuálního času (nahrazitelné v testech). */
    private readonly \Closure $clock;

    /** Maximální velikost snapshotu v bajtech. */
    private const MAX_BYTES = 25000000;

    /** Doba životnosti snapshotu v sekundách (7 dní). */
    private const TTL = 604800;

    /**
     * @param  string        $root   Kořenový adresář cache.
     * @param  string        $tenant Kod okurku; podílí se na názvu podadresáře.
     * @param  \Closure|null $clock  Zdroj Unix času; prázdná hodnota znamená `time()`.
     * @return void
     */
    public function __construct(string $root, string $tenant, ?\Closure $clock = null)
    {
        $this->directory = rtrim($root, '/').'/'.hash('sha256', $tenant);
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Sestaví cestu snapshotu a ověří formát hashe.
     *
     * @param  string $key  Klíč zdroje (název souboru nebo URL).
     * @param  string $hash SHA-256 obsahu ve tvaru 64 hex znaků.
     * @return string       Absolutní cesta k souboru.
     * @throws SyncException 'invalid_snapshot_hash' při neplatném formátu hashe.
     */
    private function path(string $key, string $hash): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) { throw new SyncException('invalid_snapshot_hash'); }
        return $this->directory.'/'.hash('sha256', $key).'-'.$hash.'.snapshot';
    }

    /**
     * Načte snapshot, pokud existuje, není expirovaný a obsah souhlasí s hashem.
     *
     * @param  string $key  Klíč zdroje.
     * @param  string $hash SHA-256 očekávaného obsahu.
     * @return string|null Obsah souboru, nebo null pokud snapshot chybí, je starý, příliš velký nebo poškozený.
     */
    public function get(string $key, string $hash): ?string
    {
        $path = $this->path($key, $hash);
        if (!is_file($path) || is_link($path)) { return null; }
        $stat = stat($path);
        if ($stat['mtime'] < ($this->clock)() - self::TTL || $stat['size'] > self::MAX_BYTES) { return null; }
        $bytes = file_get_contents($path);
        return is_string($bytes) && hash_equals($hash, hash('sha256', $bytes)) ? $bytes : null;
    }

    /**
     * Uloží snapshot atomicky a uklidí expirované soubory.
     *
     * @param  string $key   Klíč zdroje.
     * @param  string $bytes Obsah k uložení.
     * @return void          Vedlejší efekt: zápis do mezipaměti a úklid starých souborů.
     * @throws SyncException 'snapshot_too_large' nebo 'snapshot_cache_unavailable'.
     */
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
