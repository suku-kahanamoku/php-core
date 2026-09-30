<?php

declare(strict_types=1);

namespace App\Modules\Sry;

use PDO;

/**
 * Úzký, parametrizovaný SQL přístupový bod modulu Sry.
 *
 * Modul Sry používá agregované dotazy nad více tabulkami, které se nevejdou do
 * obecného API `Database`, proto má vlastní PDO wrapper. Hodnoty se vždy
 * předávají jako vazané parametry; služby navíc musí do každého dotazu zahrnout
 * rozsah rodiny nebo člena, aby nebylo možné číst cizí záznamy.
 */
class SrySqlRepository
{
    /**
     * @param  PDO $pdo Připojení k databázi předané z composition rootu.
     * @return void
     */
    public function __construct(public readonly PDO $pdo) {}

    /**
     * Vykoná dotaz a vrátí všechny řádky.
     *
     * @param  string                $sql  SQL s placeholdery `?`.
     * @param  array<array-key,mixed> $args Vazané hodnoty v pořadí placeholderů.
     * @return list<array<string, mixed>>  Načtené řádky jako asociativní pole.
     */
    public function all(string $sql, array $args = []): array
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Vykoná dotaz a vrátí první řádek.
     *
     * @param  string                 $sql  SQL s placeholdery `?`.
     * @param  array<array-key,mixed> $args Vazané hodnoty v pořadí placeholderů.
     * @return array<string, mixed>|null    První řádek, nebo null pokud dotaz nic nenašel.
     */
    public function one(string $sql, array $args = []): ?array
    {
        return $this->all($sql, $args)[0] ?? null;
    }
    /**
     * Vykoná příkaz bez čtení výsledku (INSERT, UPDATE, DELETE).
     *
     * @param  string                 $sql  SQL s placeholdery `?`.
     * @param  array<array-key,mixed> $args Vazané hodnoty v pořadí placeholderů.
     * @return void                        Vedlejší efekt: změna databáze.
     */
    public function execute(string $sql, array $args = []): void
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($args);
    }
    /**
     * Vloží řádek sestavený z klíčů a hodnot pole.
     *
     * @param  string                 $table Název tabulky.
     * @param  array<string, mixed>   $data  Sloupce a hodnoty.
     * @return int                         ID vloženého záznamu.
     */
    public function insert(string $table, array $data): int
    {
        $keys = array_keys($data);
        $this->execute(
            "INSERT INTO " .
                $table .
                " (" .
                implode(",", $keys) .
                ") VALUES (" .
                implode(",", array_fill(0, count($keys), "?")) .
                ")",
            array_values($data),
        );
        return (int) $this->pdo->lastInsertId();
    }
    /**
     * Provede callback v jedné transakci.
     *
     * @param  callable $fn Callback bez parametrů; jeho návratová hodnota se předá dál.
     * @return mixed        Návratová hodnota callbacku.
     * @throws \Throwable  Výjimka z callbacku se po rollbacku znovu vyhodí.
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    /**
     * Vrátí příponu pro řádkový zámek podle typu ovladače.
     *
     * @return string ' FOR UPDATE' pro MySQL, jinak prázdný řetězec.
     */
    public function lock(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === "mysql"
            ? " FOR UPDATE"
            : "";
    }
}
