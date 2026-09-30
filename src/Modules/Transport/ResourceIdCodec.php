<?php

declare(strict_types=1);

namespace App\Modules\Transport;

/**
 * Neprůhledné ID bezpečná pro URL.
 *
 * ID nese okrsek, poskytovatele, druh zdroje, externí identifikátor a volitelné
 * datum. Při rozkladu se vždy ověřují okrsek i druh, takže nelze použít cizí
 * nebo jiného typu ID; poskytovatel se vrací, ale neověřuje proti povolenému
 * výčtu.
 */
final class ResourceIdCodec
{
    /**
     * Zakóduje zdroj do URL-safe ID.
     *
     * @param  string      $tenant   Kód okurku.
     * @param  string      $provider Kód poskytovatele.
     * @param  string      $kind     Druh zdroje (např. `stop`).
     * @param  string      $external Externí identifikátor u poskytovatele.
     * @param  string|null $date     Datum platnosti ve formátu `Y-m-d`, nebo null.
     * @return string                ID vhodné do URL.
     * @throws \JsonException       Pokud nelze hodnotu serializovat.
     */
    public static function encode(string $tenant, string $provider, string $kind, string $external, ?string $date = null): string
    {
        return rtrim(strtr(base64_encode(json_encode([$tenant,$provider,$kind,$external,$date], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
    /**
     * Rozloží ID a ověří okrsek i druh zdroje.
     *
     * @param  string $id     ID z URL.
     * @param  string $tenant Kód okurku, ve kterém se ID smí použít.
     * @param  string $kind   Očekávaný druh zdroje.
     * @return array{provider: string, external: string, date: string|null}
     *         Poskytovatel, externí ID a datum platnosti.
     * @throws TransportException 'invalid_id', pokud tvar ID nevyhovuje, nebo
     *                            'not_found' (404), pokud jde o cizí okrsek, jiný
     *                            druh nebo neplatnou hodnotu.
     */
    public static function decode(string $id, string $tenant, string $kind): array
    {
        if (strlen($id) > 2048 || !preg_match('/^[A-Za-z0-9_-]+$/D', $id)) {
            throw new TransportException('invalid_id', 'Invalid resource ID.');
        }
        $data = json_decode(base64_decode(strtr($id, '-_', '+/'), true) ?: '', true);
        if (!is_array($data) || count($data) !== 5 || $data[0] !== $tenant || $data[2] !== $kind || !is_string($data[1]) || !is_string($data[3]) || strlen($data[3]) > 512 || ($data[4] !== null && (!is_string($data[4]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data[4])))) {
            throw new TransportException('not_found', 'Resource not found.', 404);
        }
        return ['provider' => $data[1],'external' => $data[3],'date' => $data[4]];
    }
}
