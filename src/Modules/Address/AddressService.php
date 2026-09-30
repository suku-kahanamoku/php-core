<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

/**
 * Doménová služba modulu Address — autorizace a sestavování dat pro adresy.
 *
 * Služba vynucuje, že přihlášený uživatel vidí pouze vlastní adresy, pokud není
 * administrátor. Vstup z API je před filtrováním validován jen na povinná pole;
 * zbytek tvaru řídí repozitář.
 */
class AddressService extends BaseService
{
    private AddressRepository $_address;

    /**
     * Inicializuje AddressService.
     *
     * @param Database $db            Připojení k databázi pro repozitář adres.
     * @param string   $franchiseCode Kód okurku (tenanta).
     * @param Auth     $auth          Kontext autentizace pro kontrolu rolí a vlastnictví.
     * @return void
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_address = new AddressRepository($db, $franchiseCode);
        $this->_auth    = $auth;
    }

    /**
     * Vrátí stránkovaný seznam adres (admin only).
     *
     * Bez `internalRead` musí být přihlášen admin, pokud není výslovně požadován
     * seznam cizího uživatele — ten je povolen jen vlastníkovi nebo adminovi.
     *
     * @param  int        $page          Číslo stránky (od 1).
     * @param  int        $limit         Počet záznamů na stránku.
     * @param  string     $sort          Řazení ve tvaru `sloupec ASC|DESC` nebo JSON pole.
     * @param  string     $filter        JSON filtr dle standardního dotazového kontraktu.
     * @param  array|null $projection    Požadované sloupce, nebo null pro všechny.
     * @param  int|null   $userId        Volitelný filtr na uživatele; jinak se filtr neuplatní.
     * @param  bool       $internalRead  true při interním volání z jiného modulu (autentizace už proběhla).
     * @return array                   Stránkovací odpověď s `data`, `total`, `page`, `limit`, `totalPages`.
     * @throws \Throwable               Při nedostatečných rolích request ukončí 401/404.
     */
    public function list(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
        ?int $userId = null,
        bool $internalRead = false,
    ): array {
        if (!$internalRead) {
            if ($userId === null) {
                $this->_auth->requireRole('admin');
            } else {
                $this->_auth->require();
                if (!$this->_auth->hasRole('admin') && $this->_auth->id() !== $userId) {
                    Response::notFound('User not found');
                }
            }
        }

        if ($userId !== null) {
            $decoded = json_decode($filter, true);
            $decoded = is_array($decoded) ? $decoded : [];
            $decoded['user_id'] = ['value' => $userId];
            $filter = (string) json_encode($decoded);
        }

        return $this->_address->findAll($page, $limit, $sort, $filter, $projection);
    }

    /**
     * Vrátí adresu dle ID.
     * Vyžaduje přihlášení; uživatel vidí pouze vlastní adresy, admin vidí všechny.
     * Pokud adresa neexistuje, volá Response::notFound() a ukončí request (404).
     *
     * @param  int        $id
     * @param  array|null $projection
     * @param  bool       $internalRead
     * @return array<string, mixed>
     */
    public function get(
        int $id,
        ?array $projection = null,
        bool $internalRead = false,
    ): array
    {
        if (!$internalRead) {
            $this->_auth->require();
            $this->_assertOwner($id);
        }

        $address = $this->_address->findById($id, $projection);
        $this->_requireEntity($address, 'Address not found');

        return $address;
    }

    /**
     * Vytvoří novou adresu pro přihlášeného uživatele.
     * Pokud je is_default=1, zruší předchozí default stejného typu.
     * Vyžaduje validaci: street, city a zip jsou povinná pole.
     *
     * @param  array<string, mixed> $input     Vstupní atributy adresy; street, city a zip jsou povinné.
     * @param  array|null           $projection Požadované sloupce odpovědi, nebo null pro všechny.
     * @return array<string, mixed>             Vytvořená adresa.
     */
    public function create(
        array $input,
        ?array $projection = null
    ): array {
        $this->_auth->require();

        $userId = $this->_auth->id();

        $isDefault = (int) ($input['is_default'] ?? 0);

        // Pri nastaveni jako vychozi vycisti ostatni vychozi adresy stejneho typu
        if ($isDefault) {
            $this->_address->clearDefault($userId, $input['type'] ?? 'billing');
        }

        return $this->_address->create([
            'user_id'    => $userId,
            'type'       => $input['type']    ?? 'billing',
            'company'    => $input['company'] ?? '',
            'name'       => $input['name']    ?? null,
            'street'     => $input['street'],
            'city'       => $input['city'],
            'zip'        => $input['zip'],
            'country'    => $input['country'] ?? 'CZ',
            'is_default' => $isDefault,
        ], $projection);
    }

    /**
     * Částečná aktualizace adresy (PATCH).
     * Vyžaduje přihlášení; pouze vlastník nebo admin.
     *
     * @param  int                  $id        ID adresy k aktualizaci.
     * @param  array<string, mixed> $input     Patch: pouze zaslané sloupce se ukládají, hodnoty null se ignorují.
     * @param  array|null           $projection Požadované sloupce odpovědi, nebo null pro všechny.
     * @return array<string, mixed>             Aktualizovaná adresa.
     * @throws \Throwable                        Při chybějícím záznamu nebo cizím vlastnictví končí request 404.
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->require();

        $address = $this->_address->findById($id);
        $this->_requireEntity($address, 'Address not found');
        $this->_assertOwner($id, $address);

        $set        = [];
        $textFields = [
            'type',
            'company',
            'name',
            'street',
            'city',
            'zip',
            'country',
        ];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }
        if (array_key_exists('is_default', $input) && $input['is_default'] !== null) {
            $isDefault = (int) $input['is_default'];
            if ($isDefault) {
                $type = $set['type'] ?? $address['type'];
                $this->_address->clearDefault((int) $address['user_id'], $type);
            }
            $set['is_default'] = $isDefault;
        }

        if (!empty($set)) {
            $this->_address->update($id, $set);
        }

        return $this->_address->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Úplná náhrada adresy (PUT). Povinná pole: street, city, zip, country.
     * Vyžaduje přihlášení; pouze vlastník nebo admin.
     *
     * @param  int                  $id        ID adresy k úplnému přepisu.
     * @param  array<string, mixed> $input     Kompletní sada atributů adresy.
     * @param  array|null           $projection Požadované sloupce odpovědi, nebo null pro všechny.
     * @return array<string, mixed>             Nahrazená adresa.
     * @throws \Throwable                        Při chybějícím záznamu nebo cizím vlastnictví končí request 404.
     */
    public function replace(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->require();

        $address = $this->_address->findById($id);
        $this->_requireEntity($address, 'Address not found');
        $this->_assertOwner($id, $address);

        $isDefault = (int) ($input['is_default'] ?? 0);
        if ($isDefault) {
            $type = $input['type'] ?? 'billing';
            $this->_address->clearDefault((int) $address['user_id'], $type);
        }

        $this->_address->update($id, [
            'type'       => $input['type']    ?? 'billing',
            'company'    => $input['company'] ?? '',
            'name'       => $input['name']    ?? null,
            'street'     => $input['street'],
            'city'       => $input['city'],
            'zip'        => $input['zip'],
            'country'    => $input['country'],
            'is_default' => $isDefault,
        ]);

        return $this->_address->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaže adresu.
     * Vyžaduje přihlášení; pouze vlastník nebo admin.
     *
     * @param  int $id ID adresy k tvrdému smazání.
     * @return int     Počet ovlivněných záznamů (0 nebo 1).
     * @throws \Throwable Při chybějícím záznamu nebo cizím vlastnictví končí request 404.
     */
    public function delete(int $id): int
    {
        $this->_auth->require();

        $address = $this->_address->findById($id);
        $this->_requireEntity($address, 'Address not found');
        $this->_assertOwner($id, $address);

        return $this->_address->hardDelete($id);
    }

    /**
     * Soft-smazání adresy (označí jako smazanou, ponechá v DB).
     * Vyžaduje přihlášení.
     *
     * @param  int $id ID adresy k označení jako smazaná.
     * @return int     Počet ovlivněných záznamů (0 nebo 1).
     * @throws \Throwable Při chybějícím záznamu nebo cizím vlastnictví končí request 404.
     */
    public function remove(int $id): int
    {
        $this->_auth->require();

        $address = $this->_address->findById($id);
        $this->_requireEntity($address, 'Address not found');
        $this->_assertOwner($id, $address);

        return $this->_address->softDelete($id);
    }

    /**
     * Ověří, že adresu smí upravit vlastník nebo administrátor.
     *
     * @param  int                        $id      ID adresy.
     * @param  array<string, mixed>|null $address Již načtený záznam; jinak se dohledá.
     * @return void                                Vedlejší efekt: při nesouladu ukončí request 404.
     * @throws \Throwable                          Při nesouladu je request ukončen přes Response::notFound().
     */
    private function _assertOwner(int $id, ?array $address = null): void
    {
        if ($this->_auth->hasRole('admin')) {
            return;
        }
        $address ??= $this->_address->findById($id);
        if (!$address || (int) ($address['user_id'] ?? 0) !== $this->_auth->id()) {
            Response::notFound('Address not found');
        }
    }
}
