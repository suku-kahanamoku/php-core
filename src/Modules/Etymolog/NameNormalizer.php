<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

/**
 * Normalizace zobrazeného jména do jednotné podoby.
 *
 * Diakritika ani interpunkce se nemění — mění se pouze velikost písmen, aby
 * byly názvy s diakritikou (např. „Řeřicha“) porovnávané konzistentně.
 */
final class NameNormalizer
{
    /**
     * Vrátí jméno s jedním počátečním velkým písmenem a zbytek malými.
     *
     * @param  string $name Vstupní jméno.
     * @return string        Normalizovaná podoba pro zobrazení a porovnání.
     */
    public static function display(string $name): string
    {
        $name = trim($name);
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_strtolower(mb_substr($name, 1, null, 'UTF-8'), 'UTF-8');
    }
}
