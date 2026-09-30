<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTO;

use App\Modules\Transport\TransportException;

/**
 * Definice dopravního poskytovatele pro daný okrsek.
 *
 * Kód musí být bezpečný pro URL a veřejné API, role je `primary` nebo
 * `fallback`. Pokrytí je výhradně geometrické — země a město jsou jen nápověda
 * a nikdy neurčují hranici trasy.
 */
final class ProviderDefinition
{
    /**
     * @param  string              $tenant     Kód okurku.
     * @param  string              $code       Kód poskytovatele (`[a-z0-9][a-z0-9_-]{0,63}`).
     * @param  string              $adapter    Adaptér (`entur`, `pid` nebo `otp_transmodel`).
     * @param  array<string, mixed> $config     Konfigurace poskytovatele (adresy, hlavičky, atribuce).
     * @param  list<array<string, mixed>> $coverage Pokryté oblasti s obdélníkem `[w, s, e, n]`.
     * @param  string              $role       `primary` nebo `fallback`.
     * @param  list<string>        $fallbackFor Kódy poskytovatelů, pro které je záložní.
     * @return void
     * @throws TransportException 'invalid_provider' (500), pokud kód nebo role nevyhovují.
     */
    public function __construct(
        public readonly string $tenant,
        public readonly string $code,
        public readonly string $adapter,
        public readonly array $config,
        public readonly array $coverage,
        public readonly string $role = 'primary',
        public readonly array $fallbackFor = []
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $code) || !in_array($role, ['primary','fallback'], true)) {
            throw new TransportException('invalid_provider', 'Invalid provider configuration.', 500);
        }
    }
    /**
     * Rozhodne, zda poskytovatel pokrývá celou trasu.
     *
     * @param  JourneyQuery $query Dotaz na spojení.
     * @return bool               true, pokud poskytovatel pokrývá oba koncové body.
     */
    public function covers(JourneyQuery $query): bool
    {
        // A route needs a provider covering BOTH endpoints. Country/city are hints, never a hard route boundary.
        foreach ([$query->from,$query->to] as $point) {
            $found = false;
            foreach ($this->coverage as $region) {
                $box = $region['bbox'] ?? null;
                if ($box && isset($point['lat'],$point['lon'])) {
                    [$west,$south,$east,$north] = $box;
                    $lon = $point['lon'];
                    if ($point['lat'] >= $south && $point['lat'] <= $north && ($west <= $east ? $lon >= $west && $lon <= $east : $lon >= $west || $lon <= $east)) {
                        $found = true;
                    }
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }
    /**
     * Sestaví veřejné údaje o poskytovateli pro API.
     *
     * @param  list<string>         $capabilities Deklarované schopnosti.
     * @return array<string, mixed>              Kód, schopnosti, pokrytí, role, stav
     *                                            připravenosti grafů a atribuce.
     */
    public function publicData(array $capabilities): array
    {
        return ['id' => $this->code,'capabilities' => $capabilities,'coverage' => $this->coverage,'role' => $this->role, 'schedule_ready' => $this->config['graph_ready'] ?? null,'attribution' => $this->config['attribution'] ?? null];
    }
}
