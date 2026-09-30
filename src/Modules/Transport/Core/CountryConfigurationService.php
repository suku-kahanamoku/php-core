<?php
declare(strict_types=1);
namespace App\Modules\Transport\Core;

use App\Modules\Transport\Model\TransportException;

/** Country presets are trusted repository data; tenant overrides are applied by code. */
final class CountryConfigurationService
{
    public static function compose(array $config): array
    {
        $countries = $config['countries'] ?? [];
        if (!is_array($countries) || !array_is_list($countries)) { self::invalid(); }
        $merged = ['providers'=>[], 'feeds'=>[]];
        $seenCountries = [];
        foreach ($countries as $country) {
            if (!is_string($country) || !preg_match('/^[A-Z]{2}$/D', $country) || isset($seenCountries[$country])) { self::invalid(); }
            $seenCountries[$country] = true;
            $path = dirname(__DIR__).'/Countries/'.$country.'/providers.example.json';
            if (!is_file($path)) { throw new TransportException('invalid_configuration', 'Country preset is not installed.'); }
            $preset = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            foreach (array_keys($merged) as $kind) {
                foreach ($preset[$kind] ?? [] as $row) {
                    $code = $row['code'];
                    if (isset($merged[$kind][$code]) && $merged[$kind][$code] !== $row) {
                        throw new TransportException('invalid_configuration', 'Country presets contain conflicting source codes.');
                    }
                    $merged[$kind][$code] = $row;
                }
            }
        }
        foreach (array_keys($merged) as $kind) {
            $seen = [];
            if (isset($config[$kind]) && (!is_array($config[$kind]) || !array_is_list($config[$kind]))) { self::invalid(); }
            foreach ($config[$kind] ?? [] as $row) {
                if (!is_array($row) || !is_string($row['code'] ?? null) || isset($seen[$row['code']])) { self::invalid(); }
                $code = $row['code'];
                $seen[$code] = true;
                $old = $merged[$kind][$code] ?? [];
                $merged[$kind][$code] = array_replace($old, $row);
                if (isset($row['config'])) {
                    if (!is_array($row['config'])) { self::invalid(); }
                    $merged[$kind][$code]['config'] = array_replace($old['config'] ?? [], $row['config']);
                }
            }
            $merged[$kind] = array_values($merged[$kind]);
        }
        return $merged;
    }

    private static function invalid(): never
    {
        throw new TransportException('invalid_configuration', 'Invalid or duplicate country configuration entry.');
    }
}
