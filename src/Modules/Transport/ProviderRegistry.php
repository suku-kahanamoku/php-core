<?php

declare(strict_types=1);

namespace App\Modules\Transport;

use App\Modules\Transport\Contracts\Provider;
use App\Modules\Transport\DTO\ProviderDefinition;
use App\Modules\Transport\Providers\{TransmodelProvider,PidProvider,SpojenkaProvider};

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
            } $this->providers[$code] = $p;
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
    public static function build(array $rows, string $tenant, array $env): self
    {
        $providers = [];
        foreach ($rows as $row) {
            $definition = new ProviderDefinition($tenant, $row['code'], $row['adapter'], json_decode($row['config'], true, 32, JSON_THROW_ON_ERROR), json_decode($row['coverage'], true, 32, JSON_THROW_ON_ERROR), $row['role'], json_decode($row['fallback_for'], true, 32, JSON_THROW_ON_ERROR));
            $providers[] = match($definition->adapter) {
                'entur','otp_transmodel' => new TransmodelProvider($definition),
                'spojenka' => new SpojenkaProvider($definition),
                'pid' => new PidProvider($definition, (string)($env[$definition->config['token_env'] ?? 'TRANSPORT_PID_TOKEN'] ?? '')),
                default => throw new TransportException('invalid_adapter', 'Unknown transport adapter.', 500)
            };
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
}
