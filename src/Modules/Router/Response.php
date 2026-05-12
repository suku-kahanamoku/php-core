<?php

declare(strict_types=1);

namespace App\Modules\Router;

class Response
{
    /**
     * Odesle JSON odpoved a ukonci pozadavek.
     *
     * @param  mixed $data
     * @param  int   $status  HTTP stavovy kod (vychozi 200)
     * @return never
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        exit;
    }

    /**
     * Odesle uspesnou JSON odpoved.
     *
     * @param  mixed  $data     Data obsazena v odpovedi
     * @param  string $message  Zprava (vychozi 'OK')
     * @param  int    $status   HTTP stavovy kod (vychozi 200)
     * @return never
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): never {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Odesle odpoved 201 Created.
     *
     * @param  mixed  $data
     * @param  string $message
     * @return never
     */
    public static function created(
        mixed $data = null,
        string $message = 'Created'
    ): never {
        self::success($data, $message, 201);
    }

    /**
     * Odesle chybovou JSON odpoved.
     *
     * @param  string $message  Popis chyby
     * @param  int    $status   HTTP stavovy kod (vychozi 400)
     * @param  mixed  $errors   Volitelne detailni informace o chybach
     * @return never
     */
    public static function error(
        string $message,
        int $status = 400,
        mixed $errors = null,
    ): never {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Odesle odpoved 401 Unauthorized.
     *
     * @param  string $message
     * @return never
     */
    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    /**
     * Odesle odpoved 403 Forbidden.
     *
     * @param  string $message
     * @return never
     */
    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    /**
     * Odesle odpoved 404 Not Found.
     *
     * @param  string $message
     * @return never
     */
    public static function notFound(string $message = 'Not Found'): never
    {
        self::error($message, 404);
    }

    /**
     * Odesle odpoved 422 Unprocessable Entity (validacni chyba).
     *
     * @param  array  $errors   Pole validacnich chyb (pole => chyba)
     * @param  string $message
     * @return never
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
    ): never {
        self::error($message, 422, $errors);
    }

    /**
     * Odesle uspesnou JSON odpoved s automaticky aplikovanym factory z requestu.
     * Pouzij misto kombinace success() + applyFactory() v list() metodach.
     * Pokud request neobsahuje factory, chova se stejne jako success().
     *
     * Transformuje data z formatu { items: [...], total, page, limit, totalPages }
     * na format { success, message, data: [...], meta: { total, page, limit, totalPages } }
     *
     * @param  array<string, mixed> $data     Musi obsahovat klic 'items'
     * @param  Request              $request
     * @return never
     */
    public static function successWithFactory(array $data, Request $request): never
    {
        $factory = $request->factory();
        if ($factory !== null && isset($data['items'])) {
            $data['items'] = self::applyFactory($data['items'], $factory);
        }

        // Extrahuj metadata z data
        $items = $data['items'] ?? [];
        $meta  = [
            'total'      => $data['total'] ?? 0,
            'page'       => $data['page'] ?? 1,
            'limit'      => $data['limit'] ?? 20,
            'totalPages' => $data['totalPages'] ?? 0,
            'skip'       => (($data['page'] ?? 1) - 1) * ($data['limit'] ?? 20),
        ];

        // Vrať s items v data a metadata v meta
        self::json([
            'success' => true,
            'message' => 'OK',
            'data'    => $items,
            'meta'    => $meta,
        ]);
    }

    /**
     * Odesle uspesnou JSON odpoved pro jeden zaznam s aplikovanym factory.
     * Pouzij misto kombinace success() + applyFactory() v get() metodach.
     * Pokud request neobsahuje factory, chova se stejne jako success().
     *
     * @param  array<string, mixed> $item
     * @param  Request              $request
     * @return never
     */
    public static function successItemWithFactory(array $item, Request $request): never
    {
        $factory = $request->factory();
        if ($factory !== null) {
            $item = self::applyFactory([$item], $factory)[0];
        }
        self::success($item);
    }

    /**
     * Aplikuje factory sablony na kazdy zaznam v poli items.
     *
     * Factory je objekt kde klic = nazev generovaneho pole, hodnota = sablona.
     * Sablona muze obsahovat:
     *   ${field}  — nahrazeno hodnotou pole `field` (nebo prazdnym retezcem)
     *   $${field} — nahrazeno literalnim `$` + hodnotou pole `field`
     *
     * Vysledek je vlozeny jako `gen_data` objekt do kazdeho zaznamu.
     *
     * @param  list<array<string, mixed>>  $items     Pole zaznamu
     * @param  array<string, string>       $factory   Mapa nazev → sablona
     * @return list<array<string, mixed>>
     */
    public static function applyFactory(array $items, array $factory): array
    {
        if (empty($factory)) {
            return $items;
        }

        foreach ($items as &$item) {
            $flat = self::flattenForFactory($item);
            $gen  = [];

            foreach ($factory as $key => $template) {
                // First replace $${ (escaped dollar) then ${ (plain interpolation)
                $result = preg_replace_callback(
                    '/\$\$\{([^}]+)\}/',
                    fn($m) => '$' . ($flat[$m[1]] ?? ''),
                    $template,
                );
                $result = preg_replace_callback(
                    '/\$\{([^}]+)\}/',
                    fn($m) => (string) ($flat[$m[1]] ?? ''),
                    $result,
                );
                $gen[$key] = $result;
            }

            $item['gen_data'] = $gen;
        }
        unset($item);

        return $items;
    }

    /**
     * Flatten a row for factory template resolution.
     * Nested arrays (e.g. data.quality) are accessible via dot-notation key.
     *
     * @param  array<string, mixed> $row
     * @param  string               $prefix
     * @return array<string, string>
     */
    private static function flattenForFactory(array $row, string $prefix = ''): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, self::flattenForFactory($value, $fullKey));
            } else {
                $result[$fullKey] = (string) ($value ?? '');
                // Also register short key without prefix for convenience
                if ($prefix !== '') {
                    $result[$key] ??= (string) ($value ?? '');
                }
            }
        }
        return $result;
    }
}
