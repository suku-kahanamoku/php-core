<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;

use App\Utils\QueryPolicy;

/**
 * Aplikační služby veřejného vyhledávání v etymologii.
 *
 * Filtr `q` se zpracovává přes `QueryPolicy`, takže klient nemůže přistupovat
 * k nezamýšleným sloupcům ani vytvářet vlastní SQL. Diakritika zůstává
 * rozdělena (binární porovnání), ale vyhledávání bez ní funguje díky `LIKE`.
 */
final class EtymologPublicService
{
    /** Povolene sloupce ve verejnem filtru `q`. */
    private const PUBLIC_FILTERS = ['name', 'kind', 'language', 'country_code'];

    /** Maximalni velikost filtru `q` v bajtech; vetsi filtr je odmítnut. */
    private const MAX_FILTER_BYTES = 16000;

    /**
     * @param  EtymologPublicRepository $repository Repozitar pouze pro cteni, omezeny na okurk.
     * @return void
     */
    public function __construct(private readonly EtymologPublicRepository $repository) {}

    /** Today's overview uses the calendar date in Prague, independent of server timezone. */
    public function today(): array
    {
        return $this->repository->today(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague')));
    }

    /**
     * Vyhledá zveřejněná jména podle filtru.
     *
     * @param  mixed $query Filtr `q` (JSON objekt; i prostý text se přijme pro existující odkazy).
     * @param  mixed $kind  Volitelný druh jména: '', 'given' nebo 'surname'.
     * @param  mixed $page  Číslo stránky.
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int} Stránka výsledků.
     * @throws EtymologException      422 pri neplatnem filtru, 404 neni pouzito.
     */
    public function search(mixed $query, mixed $kind, mixed $page): array
    {
        $filter = $this->filter($query, $kind);
        $page = $this->positiveInteger($page);
        if ($page > 1000000) { throw new EtymologException('Invalid page', 422); }
        return $this->repository->search($filter, $page);
    }

    /**
     * Vrátí detail jednoho zveřejněného jména včetně všech souvisejících záznamů.
     *
     * @param  mixed $id ID jména.
     * @return array<string, mixed> Jméno, výklady, citace, varianty, výskyty, kalendářní dny a zdroje.
     * @throws EtymologException 404 'Name not found' nebo 422 pri neplatnem ID.
     */
    public function detail(mixed $id): array
    {
        return $this->repository->detail($this->positiveInteger($id)) ?? throw new EtymologException('Name not found', 404);
    }

    /**
     * Přeloží `q` (JSON objekt, standardní filtr syntaxe) na bezpečný JSON filtr.
     *
     * `kind` z query parametru se do filtru vloží jako dodatečná podmínka. Prostý
     * text místo JSONu se přijme, aby fungovaly existující veřejné odkazy.
     *
     * @param  mixed $query Filtr `q` z požadavku.
     * @param  mixed $kind  Druh jména z query parametru.
     * @return string        Normalizovaný JSON filtr předaný do `SQL_FILTER()`.
     * @throws EtymologException 422 pri neplatnem filtru, prilis dlouhém dotazu nebo prazdném jméně.
     */
    private function filter(mixed $query, mixed $kind): string
    {
        if (!is_string($kind) || !in_array($kind, ['', 'given', 'surname'], true)) {
            throw new EtymologException('Invalid search parameters', 422);
        }
        if (!is_string($query) || $query === '' || strlen($query) > self::MAX_FILTER_BYTES) {
            throw new EtymologException('q must be a JSON object', 422);
        }
        $decoded = json_decode($query, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Keep established plain-text public URLs working for existing callers.
            $decoded = ['name' => ['$regex' => trim($query)]];
        }
        if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            throw new EtymologException('q must be a JSON object', 422);
        }
        array_walk_recursive($decoded, static         /**
         * Kontrola, že filtr obsahuje jen skalární hodnoty.
         *
         * @param  mixed $value Hodnota z filtru `q`.
         * @return void          Bez návratu; při chybě vyhodí výjimku.
         * @throws EtymologException 'Invalid filter' (422), pokud hodnota není skalární ani null.
         */
function ($value): void {
            if (!is_scalar($value) && $value !== null) {
                throw new EtymologException('Invalid filter', 422);
            }
        });
        if ($kind !== '') {
            $decoded['kind'] = ['value' => $kind];
        }
        $safe = QueryPolicy::filter(json_encode($decoded), self::PUBLIC_FILTERS);
        if ($safe === '') {
            throw new EtymologException('Invalid search parameters', 422);
        }
        $name = $this->nameValue(json_decode($safe, true)['name'] ?? null);
        if ($name === null || mb_strlen(trim($name)) < 2 || mb_strlen(trim($name)) > 100) {
            throw new EtymologException('Invalid search parameters', 422);
        }
        return $safe;
    }

    /**
     * Vytáhne hledané jméno z libovolné podoby specifikace v filtru.
     *
     * @param  mixed $spec Řetězec nebo pole s `value`, `$regex` či `$eq`.
     * @return string|null  Hledané jméno, nebo null pokud ho specifikace neobsahuje.
     */
    private function nameValue(mixed $spec): ?string
    {
        if (is_string($spec)) {
            return $spec;
        }
        if (!is_array($spec)) {
            return null;
        }
        foreach (['value', '$regex', '$eq'] as $key) {
            if (isset($spec[$key]) && is_string($spec[$key])) {
                return $spec[$key];
            }
        }
        return null;
    }

    /**
     * Ověří, že hodnota je kladné celé číslo v rozsahu sloupce INT.
     *
     * @param  mixed $value Hodnota z path segmentu nebo query parametru.
     * @return int          Ciselna hodnota.
     * @throws EtymologException 422 'Expected positive integer'.
     */
    private function positiveInteger(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > 2147483647) {
            throw new EtymologException('Expected positive integer', 422);
        }
        return (int)$value;
    }
}
