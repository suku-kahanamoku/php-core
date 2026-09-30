<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/**
 * Pomocné statické funkce pro validaci vstupů modulu Sry.
 *
 * Třída je bez stavu a volá se staticky z `SryService` a `SryAuth`. Všechny
 * kontroly jsou jednotné: vstup musí být řetězec, nesmí být prázdný (pokud je
 * povinný) a nesmí překročit maximální délku.
 */
final class SryInput
{
    /**
     * Načte a zvaliduje textový parametr z těla požadavku.
     *
     * @param  array<string, mixed> $body     Tělo požadavku.
     * @param  string               $key      Klíč parametru.
     * @param  int                  $max      Maximální délka v multibyte znacích.
     * @param  bool                 $required true, pokud nesmí být prázdný.
     * @return string                        Oříznutá hodnota.
     * @throws SryError                      'invalidInput' (422), pokud hodnota chybí, není řetězec nebo je příliš dlouhá.
     */
    public static function text(
        array $body,
        string $key,
        int $max = 100,
        bool $required = true,
    ): string {
        $value = $body[$key] ?? "";
        if (!is_string($value)) {
            throw new SryError("invalidInput");
        }
        $value = trim($value);
        if (($required && $value === "") || mb_strlen($value) > $max) {
            throw new SryError("invalidInput");
        }
        return $value;
    }
    /**
     * Zvaliduje heslo z těla požadavku.
     *
     * @param  array<string, mixed> $body Tělo požadavku s klíčem `password`.
     * @return string                    Heslo v původní podobě (před hashováním).
     * @throws SryError                  'passwordInvalid' (422), pokud délka není mezi 10 a 72 znaky.
     */
    public static function password(array $body): string
    {
        $p = $body["password"] ?? null;
        if (!is_string($p) || strlen($p) < 10 || strlen($p) > 72) {
            throw new SryError("passwordInvalid");
        }
        return $p;
    }
    /**
     * Načte a zvaliduje e-mailovou adresu (normalizovanou na malá písmena).
     *
     * @param  array<string, mixed> $body Tělo požadavku s klíčem `email`.
     * @return string                    E-mail v malých písmenech.
     * @throws SryError                  'invalidInput' (422), pokud adresa chybí nebo není platná.
     */
    public static function email(array $body): string
    {
        $e = strtolower(self::text($body, "email", 254));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            throw new SryError("invalidInput");
        }
        return $e;
    }
}
