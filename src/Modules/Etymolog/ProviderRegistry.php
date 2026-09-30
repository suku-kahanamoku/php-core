<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Modules\Etymolog\Contracts\BatchProvider;

/**
 * Registry synchronizačních zdrojů: mapuje klíč zdroje na jeho poskytovatele.
 *
 * Místo přímé práce s HTTP poskytovateli tak synchronizace závisí pouze na
 * rozhraní `BatchProvider`; nový zdroj se přidá jen zde, v `EtymologModule`.
 */
final class ProviderRegistry
{
    /**
     * @param  array<string, BatchProvider> $providers Poskytovatelé podle klíče zdroje.
     * @return void
     */
    public function __construct(private readonly array $providers) {}

    /**
     * Vrátí poskytovatele pro daný zdroj.
     *
     * @param  string $provider Klíč zdroje (např. 'wiktionary-cs').
     * @return BatchProvider     Poskytovatel pro dávkovou synchronizaci.
     * @throws SyncException 'unsupported_provider', pokud zdroj není zaregistrovaný.
     */
    public function get(string $provider): BatchProvider
    {
        return $this->providers[$provider] ?? throw new SyncException('unsupported_provider');
    }
}
