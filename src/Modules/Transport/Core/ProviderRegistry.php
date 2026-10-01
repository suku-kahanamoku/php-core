<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\TransportException;

use App\Modules\Transport\Contracts\Provider;
use App\Modules\Transport\Model\ProviderDefinition;

/**
 * Registry dopravních poskytovatelů jednoho okurku.
 *
 * Poskytovatelé se indexují podle kódu a duplicitní kód je chyba, aby se
 * nezaložilo na náhodě, který z nich se použije. Adaptéry se instancují výhradně
 * z deklarovaného výčtu; neznámý adaptér se odmítne.
 */
final class ProviderRegistry
{
    /** @var array<string,Provider> Poskytovatelé indexovaní podle kódu. */
    private array $providers = [];

    /**
     * @param  iterable<Provider> $providers Poskytovatelé daného okurku.
     * @return void
     * @throws \LogicException Při duplicitním kódu poskytovatele.
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $p) {
            $code = $p->definition()->code;
            if (isset($this->providers[$code])) {
                throw new \LogicException('Duplicate provider.');
            }
            $this->providers[$code] = $p;
        }
    }
    /**
     * Sestaví registry z řádků konfigurace dopravců.
     *
     * @param  list<array<string, mixed>> $rows   Řádky `transport_provider` okurku.
     * @param  string                      $tenant Kód okurku.
     * @param  array<string, string>       $env    Proměnné prostředí s přístupovými tokeny.
     * @return self                                  Registry připravených poskytovatelů.
     * @throws TransportException 'invalid_adapter' (500), pokud adaptér neznáme.
     */
    public static function build(array $rows, string $tenant, array $env, IntegrationRegistry $modules, ?\App\Modules\Transport\Persistence\TransportRepository $repository = null): self
    {
        $providers = [];
        foreach ($rows as $row) {
            $definition = new ProviderDefinition($tenant, $row['code'], $row['adapter'], json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR), json_decode($row['coverage'], true, 32, JSON_THROW_ON_ERROR), $row['role'], json_decode($row['fallback_for'], true, 32, JSON_THROW_ON_ERROR));
            $module = $modules->get($definition->adapter);
            $module->validate($definition);
            $providers[] = $module->create($definition, $env, $repository);
        }
        return new self($providers);
    }
    /**
     * @return array<string, Provider> Všichni poskytovatelé podle kódu.
     */
    public function all(): array
    {
        return $this->providers;
    }
    /**
     * Načte poskytovatele podle kódu.
     *
     * @param  string $code Kód poskytovatele.
     * @return Provider    Poskytovatel daného okurku.
     * @throws TransportException 'not_found' (404), pokud poskytovatel není
     *                            nakonfigurovaný nebo nepokrývá daný okrsek.
     */
    public function get(string $code): Provider
    {
        return $this->providers[$code] ?? throw new TransportException('not_found', 'Provider not available for this tenant.', 404);
    }
    /** Resolve only explicit identity links. Never match a station by its display name. */
    public function canonicalReference(string $operation, array $reference): array
    {
        $visited = [];
        while (true) {
            $code = $reference['provider'];
            if (isset($visited[$code])) {
                throw new TransportException('invalid_configuration', 'Cyclic source identity mapping.', 500);
            }
            $visited[$code] = true;
            $provider = $this->get($code);
            if ($operation === 'realtime' && $provider->definition()->enabled($operation) && !in_array($operation, $provider->capabilities(), true)) {
                $matches = [];
                foreach ($this->providers as $candidate) {
                    if (
                        $candidate instanceof \App\Modules\Transport\Contracts\RealtimeReferenceProvider
                        && $candidate->definition()->enabled('realtime') && in_array('realtime', $candidate->capabilities(), true)
                        && ($mapped = $candidate->realtimeReference($reference)) !== null
                    ) {
                        if (($mapped['date'] ?? null) !== ($reference['date'] ?? null) || $mapped['provider'] !== $candidate->definition()->code) {
                            throw new \LogicException('Invalid realtime reference mapping.');
                        }
                        $matches[] = $mapped;
                    }
                }
                if (count($matches) > 1) {
                    throw new TransportException('invalid_configuration', 'Ambiguous realtime source mapping.', 500);
                }
                if ($matches) {
                    $reference = $matches[0];
                    continue;
                }
            }
            if (!$provider->definition()->enabled($operation) || !in_array($operation, $provider->capabilities(), true)) {
                throw new TransportException('unsupported_capability', 'Mapped source does not provide the requested operation.', 422);
            }
            if (!$provider instanceof \App\Modules\Transport\Contracts\ResourceMappingProvider) {
                return $reference;
            }
            $mapped = $provider->sourceReference($operation, $reference);
            if ($mapped === null) {
                return $reference;
            }
            if (($mapped['date'] ?? null) !== ($reference['date'] ?? null)) {
                throw new \LogicException('Identity mapping changed the service day.');
            }
            $reference = $mapped;
        }
    }
}
