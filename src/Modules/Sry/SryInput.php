<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class SryInput
{
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
    public static function password(array $body): string
    {
        $p = $body["password"] ?? null;
        if (!is_string($p) || strlen($p) < 10 || strlen($p) > 72) {
            throw new SryError("passwordInvalid");
        }
        return $p;
    }
    public static function email(array $body): string
    {
        $e = strtolower(self::text($body, "email", 254));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            throw new SryError("invalidInput");
        }
        return $e;
    }
}
