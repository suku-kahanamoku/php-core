<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

final class NameNormalizer
{
    /** One initial uppercase letter, the rest lowercase; keep accents and punctuation. */
    public static function display(string $name): string
    {
        $name = trim($name);
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_strtolower(mb_substr($name, 1, null, 'UTF-8'), 'UTF-8');
    }
}
