<?php

declare(strict_types=1);

namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\TransportException;

use App\Modules\Transport\Model\ProviderDefinition;
use App\Modules\Transport\Persistence\TransportRepository;

/**
 * Uplatní konfiguraci dopravců a feedů z konfigurace serveru.
 *
 * Konfigurace se nejdřív celá ověří a teprve potom se zapíše v jedné
 * transakci, takže neúplná konfigurace nezanechá poloviční stav. Ověřují se
 * jen známé adaptéry, jednoznačné kódy, platné adresy koncových bodů, explicitní
 * pokrytí obdélníkem a oprávnění feedu skladovat data.
 */
final class ConfigurationService
{
    /**
     * Ověří a uloží poskytnutou konfiguraci dopravců, feedů a způsobů dopravy.
     *
     * @param  TransportRepository $r       Repozitář daného okurku (včetně PDO).
     * @param  array<string, mixed> $config Konfigurace: `providers`, volitelně `feeds`.
     * @return void                       Vedlejší efekt: zápis do `transport_provider`,
     *                                    `transport_feed` a `enumeration`.
     * @throws TransportException          'invalid_configuration' nebo 'storage_not_allowed';
     *                                    při chybě se transakce vrátí zpět.
     */
    public static function apply(TransportRepository $r, array $config, IntegrationRegistry $modules, \App\Modules\Transport\Import\ImporterRegistry $importers): void
    {
        $config = CountryConfigurationService::compose($config);
        if (empty($config['providers']) || !is_array($config['providers'])) {
            throw new TransportException('invalid_configuration', 'Providers are required.');
        }
        $seen = [];
        foreach ($config['providers'] as $p) {
            if (!is_string($p['code'] ?? null) || !is_string($p['adapter'] ?? null)
                || !is_array($p['config'] ?? null) || !is_array($p['coverage'] ?? null) || !array_is_list($p['coverage'])
                || isset($p['role']) && !is_string($p['role'])
                || isset($p['fallback_for']) && (!is_array($p['fallback_for']) || !array_is_list($p['fallback_for']) || array_filter($p['fallback_for'], fn ($v)=>!is_string($v)))) {
                throw new TransportException('invalid_configuration', 'Invalid provider definition.');
            }
            if (!$modules->has($p['adapter'] ?? '') || isset($seen[$p['code']])) {
                throw new TransportException('invalid_configuration', 'Unknown adapter or duplicate provider.');
            }
            $seen[$p['code']] = true;
            $definition = new ProviderDefinition($r->tenant, $p['code'], $p['adapter'], $p['config'], $p['coverage'], $p['role'] ?? 'primary', $p['fallback_for'] ?? []);
            $module = $modules->get($definition->adapter);
            $module->validate($definition);
            self::operations($definition, $module->create($definition, [])->capabilities());
            self::quota($definition->config);
            foreach (['client_name','token_env'] as $key) {
                if (isset($p['config'][$key]) && (!is_string($p['config'][$key]) || preg_match('/[\r\n]/', $p['config'][$key]))) {
                    throw new TransportException('invalid_configuration', 'Invalid provider header configuration.');
                }
            }
            if (!$p['coverage']) {
                throw new TransportException('invalid_configuration', 'Coverage must be explicit.');
            }
            foreach ($p['coverage'] as $region) {
                if (!is_array($region)) { throw new TransportException('invalid_configuration', 'Invalid coverage region.'); }
                $cities = $region['cities'] ?? (isset($region['city']) ? [$region['city']] : []);
                if (!is_array($cities) || !array_is_list($cities) || array_filter($cities, fn ($city) => !is_string($city) || trim($city) === '' || mb_strlen($city) > 120)) {
                    throw new TransportException('invalid_configuration', 'Invalid city coverage.');
                }
                $b = $region['bbox'] ?? null;
                if (!preg_match('/^[A-Z]{2}$/D', $region['country'] ?? '') || !is_array($b) || !array_is_list($b) || count($b) !== 4 || array_filter($b, fn ($x) => !is_numeric($x) || !is_finite((float)$x)) || abs($b[0]) > 180 || abs($b[2]) > 180 || abs($b[1]) > 90 || abs($b[3]) > 90 || $b[1] > $b[3]) {
                    throw new TransportException('invalid_configuration', 'Invalid coverage bounding box.');
                }
            }
        }
        foreach ($config['providers'] as $p) {
            $references = $p['fallback_for'] ?? [];
            foreach ($p['config']['operations'] ?? [] as $policy) { $references = array_merge($references, $policy['fallback_for'] ?? []); }
            if (in_array($p['code'], $references, true) || array_diff($references, array_keys($seen))) {
                throw new TransportException('invalid_configuration', 'Fallback refers to an unknown provider.');
            }
        }
        foreach ($config['providers'] as $p) {
            foreach (['source_provider','schedule_provider'] as $ref) {
                if (isset($p['config'][$ref]) && (!isset($seen[$p['config'][$ref]]) || $p['config'][$ref] === $p['code'])) {
                    throw new TransportException('invalid_configuration', 'Invalid linked provider.');
                }
            }

        }
        $r->db->beginTransaction();
        try {
            foreach ($config['providers'] as $p) {
                $r->execute('INSERT INTO transport_provider(franchise_code,code,adapter,role,config,coverage,fallback_for,published) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE adapter=VALUES(adapter),role=VALUES(role),config=VALUES(config),coverage=VALUES(coverage),fallback_for=VALUES(fallback_for),published=VALUES(published)', [$r->tenant,$p['code'],$p['adapter'],$p['role'] ?? 'primary',self::json($p['config']),self::json($p['coverage']),self::json($p['fallback_for'] ?? []),!empty($p['published']) ? 1 : 0]);
            }
            foreach ($config['feeds'] ?? [] as $f) {
                if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $f['code'] ?? '') || !isset($seen[$f['provider']])) {
                    throw new TransportException('invalid_configuration', 'Invalid feed.');
                }
                $importers->get($f['config']['format'] ?? 'gtfs');
                self::url($f['url']);
                new \DateTimeZone($f['timezone']);
                if (empty($f['config']['storage_allowed'])) {
                    throw new TransportException('storage_not_allowed', 'Explicit storage permission is required for each imported feed.');
                }
                $r->execute('INSERT INTO transport_feed(franchise_code,code,provider_code,url,timezone,config) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE provider_code=VALUES(provider_code),url=VALUES(url),timezone=VALUES(timezone),config=VALUES(config)', [$r->tenant,$f['code'],$f['provider'],$f['url'],$f['timezone'],self::json($f['config'])]);
            }
            $modes = ['bus' => 'Autobus','tram' => 'Tramvaj','train' => 'Vlak','metro' => 'Metro','trolleybus' => 'Trolejbus','ferry' => 'Trajekt','coach' => 'Dálkový autobus','airplane' => 'Letadlo','cable_car' => 'Pozemní lanová dráha','gondola' => 'Visutá lanová dráha','funicular' => 'Lanovka','monorail' => 'Jednokolejka'];
            $position = 0;
            foreach ($modes as $code => $label) {
                $r->execute("INSERT INTO enumeration(franchise_code,type,syscode,label,value,position,published) VALUES (?,'transport_mode',?,?,?,?,1) ON DUPLICATE KEY UPDATE syscode=VALUES(syscode)", [$r->tenant,$code,$label,$code,++$position]);
            }
            $r->db->commit();
        } catch (\Throwable $e) {
            $r->db->rollBack();
            throw $e;
        }
    }
    private static function operations(ProviderDefinition $definition, array $capabilities): void
    {
        $policies = $definition->config['operations'] ?? [];
        if (!is_array($policies)) { throw new TransportException('invalid_configuration', 'Invalid operation policies.'); }
        foreach ($policies as $operation => $policy) {
            if (!in_array($operation, $capabilities, true) || !is_array($policy)
                || array_diff(array_keys($policy), ['enabled','role','priority','fallback_for'])
                || isset($policy['enabled']) && !is_bool($policy['enabled'])
                || isset($policy['role']) && !in_array($policy['role'], ['primary','fallback'], true)
                || isset($policy['priority']) && (!is_int($policy['priority']) || $policy['priority'] < 0 || $policy['priority'] > 10000)
                || isset($policy['fallback_for']) && (!is_array($policy['fallback_for']) || !array_is_list($policy['fallback_for']) || array_filter($policy['fallback_for'], fn ($v) => !is_string($v)))) {
                throw new TransportException('invalid_configuration', 'Invalid or unsupported operation policy.');
            }
        }
    }

    private static function quota(array $config): void
    {
        if (isset($config['min_interval_ms']) && (!is_int($config['min_interval_ms']) || $config['min_interval_ms'] < 0 || $config['min_interval_ms'] > 60000)) {
            throw new TransportException('invalid_configuration', 'Invalid request interval.');
        }
        $q = $config['quota'] ?? [];
        if (!is_array($q) || array_diff(array_keys($q), ['scope','limit','window_ms'])
            || isset($q['scope']) && (!is_string($q['scope']) || !preg_match('/^[a-zA-Z0-9:_.-]{1,128}$/D', $q['scope']))
            || (isset($q['limit']) !== isset($q['window_ms']))
            || isset($q['limit']) && (!is_int($q['limit']) || $q['limit'] < 1 || $q['limit'] > 10000 || !is_int($q['window_ms']) || $q['window_ms'] < 1 || $q['window_ms'] > 3600000)) {
            throw new TransportException('invalid_configuration', 'Invalid shared quota policy.');
        }
    }

    /**
     * Ověří konfigurovanou koncovou adresu poskytovatele.
     *
     * @param  string $url      Adresa koncového bodu.
     * @param  bool   $internal true pro vnitřní plánovače, kde je přípustné i HTTP.
     * @return void            Bez návratu; při chybě vyhodí výjimku.
     * @throws TransportException 'invalid_configuration', pokud adresa chybí, není
     *                            `https` (případně `http` pro vnitřní), obsahuje
     *                            přihlašovací údaje, fragment nebo zalomení řádku.
     */
    public static function url(string $url, bool $internal = false): void
    {
        $p = parse_url($url);
        if (!$p || !isset($p['host']) || !in_array($p['scheme'] ?? '', $internal ? ['http','https'] : ['https'], true) || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || preg_match('/[\r\n]/', $url)) {
            throw new TransportException('invalid_configuration', 'Invalid server-configured endpoint.');
        }
    }
    /**
     * Serializuje konfiguraci pro sloupec JSON.
     *
     * @param  array<array-key, mixed> $v Hodnota k uložení.
     * @return string                   JSON bez escapovaných lomítek a Unicode.
     * @throws \JsonException          Pokud nelze hodnotu serializovat.
     */
    private static function json(array $v): string
    {
        return json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
