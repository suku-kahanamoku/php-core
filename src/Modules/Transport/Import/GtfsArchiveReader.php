<?php

declare(strict_types=1);

namespace App\Modules\Transport\Import;

use App\Modules\Transport\TransportException;

/**
 * Čte položky ZIP jako proudy, nikdy nerozbalí cesty řízené útočníkem.
 *
 * Při otevření se ověří, že archiv nemá duplicitní položky, cesty s `..`,
 * zpětnými lomítky nebo absolutní cesty a nepřekračuje limity (200 položek,
 * 4 GB nekomprimovaně). Řádky se čtou proudově a proti BOM i počtu sloupců.
 */
final class GtfsArchiveReader
{
    /** Otevřený archiv. */
    private \ZipArchive $zip;

    /**
     * @param  string $path    Cesta k archivu na disku.
     * @param  int    $maxRows Maximální počet řádků v jednom souboru.
     * @return void
     * @throws TransportException 'invalid_archive' nebo 'archive_limit', pokud archiv
     *                            nelze otevřít nebo porušuje limity.
     */
    public function __construct(string $path, private readonly int $maxRows = 10000000)
    {
        $this->zip = new \ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new TransportException('invalid_archive', 'Cannot open GTFS ZIP.');
        }
        $size = 0;
        $names = [];
        for ($i = 0;$i < $this->zip->numFiles;$i++) {
            $s = $this->zip->statIndex($i);
            $name = $s['name'];
            if (isset($names[$name]) || str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/')) {
                throw new TransportException('invalid_archive', 'Unsafe or duplicate ZIP entry.');
            }
            $names[$name] = true;
            $size += $s['size'];
            if ($size > 4000000000 || $this->zip->numFiles > 200) {
                throw new TransportException('archive_limit', 'GTFS archive exceeds configured limits.');
            }
        }
    }
    /**
     * Ověří, že archiv obsahuje zadaný soubor.
     *
     * @param  string $name Název položky v archivu.
     * @return bool         true, pokud položka existuje.
     */
    public function has(string $name): bool
    {
        return $this->zip->locateName($name) !== false;
    }
    /**
     * Čte řádky CSV souboru z archivu jako dvojice sloupec–hodnota.
     *
     * @param  string             $file     Název položky (např. `stops.txt`).
     * @param  list<string>       $required Povinné názvy sloupců v hlavičce.
     * @param  bool               $optional true, když chybějící soubor není chyba.
     * @return \Generator<int, array<string, string|null>> Řádky souboru.
     * @throws TransportException 'missing_gtfs_file', 'invalid_archive', 'invalid_csv'
     *                            nebo 'row_limit'.
     */
    public function rows(string $file, array $required = [], bool $optional = false): \Generator
    {
        if (!$this->has($file)) {
            if ($optional) {
                return;
            } throw new TransportException('missing_gtfs_file', "Required GTFS file missing: $file");
        }
        $stream = $this->zip->getStream($file);
        if (!$stream) {
            throw new TransportException('invalid_archive', 'Cannot read archive entry.');
        }
        try {
            $header = fgetcsv($stream, 1048576, ',', '"', '');
            if (!is_array($header)) {
                throw new TransportException('invalid_csv', "Empty GTFS file: $file");
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            if (count($header) !== count(array_unique($header)) || array_diff($required, $header)) {
                throw new TransportException('invalid_csv', "Invalid GTFS columns: $file");
            }
            $count = 0;
            while (($values = fgetcsv($stream, 1048576, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (++$count > $this->maxRows) {
                    throw new TransportException('row_limit', 'GTFS row limit exceeded.');
                }
                if (count($values) !== count($header)) {
                    throw new TransportException('invalid_csv', "Invalid GTFS row in $file");
                }
                yield array_combine($header, $values);
            }
        } finally {
            fclose($stream);
        }
    }
    /**
     * Uzavře archiv.
     *
     * @return void Bez návratu.
     */
    public function __destruct()
    {
        $this->zip->close();
    }
}
