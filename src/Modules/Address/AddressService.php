<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\ServiceAuthTrait;
use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class AddressService
{
    use ServiceAuthTrait;

    private AddressRepository $address;
    private Auth $auth;

    /**
     * Inicializuje AddressService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->address = new AddressRepository($db, $franchiseCode);
        $this->auth    = $auth;
    }

    protected function getAuth(): Auth
    {
        return $this->auth;
    }

    /**
     * Vrati strankovany seznam adres daneho uzivatele.
     * Vyzaduje prihlaseni; uzivatel vidi pouze vlastni adresy, admin vidi vsechny.
     *
     * @param  int         $userId
     * @param  string|null $type
     * @param  string      $sort
     * @param  int         $page
     * @param  int         $limit
     * @param  string      $filter
     * @param  array|null  $projection
     * @return array{
     *   items: list<array<string, mixed>>, 
     *   total: int, 
     *   page: int, 
     *   limit: int, 
     *   totalPages: int
     * }
     */
    public function listByUser(
        int $userId,
        ?string $type,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
        string $filter = '',
        ?array $projection = null,
    ): array {
        $this->auth->require();

        if (!$this->auth->hasRole('admin') && $this->auth->id() !== $userId) {
            Response::forbidden();
        }

        return $this->address->findByUser(
            $userId,
            $type,
            $sort,
            $page,
            $limit,
            $filter,
            $projection
        );
    }

    /**
     * Vrati adresu dle ID.
     * Vyzaduje prihlaseni; uzivatel vidi pouze vlastni adresy, admin vidi vsechny.
     * Pokud adresa neexistuje, vola Response::notFound() a ukonci request (404).
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $this->auth->require();

        $address = $this->requireEntity(
            $this->address->findById($id, $projection),
            'Address not found',
        );
        $this->requireOwnerOrAdmin($address);

        return $address;
    }

    /**
     * Vytvori novou adresu pro prihlaseneho uzivatele (nebo pro $overrideUserId pokud je caller admin).
     * Pokud je is_default=1, zrusi predchozi default stejneho typu.
     * Vyzaduje validaci: street, city, zip jsou povinna pole.
     *
     * @param  array<string, mixed> $input
     * @param  int|null             $overrideUserId
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(
        array $input,
        ?int $overrideUserId = null,
        ?array $projection = null
    ): array {
        $this->auth->require();

        $userId = ($this->auth->hasRole('admin') && $overrideUserId !== null)
            ? $overrideUserId
            : $this->auth->id();

        VALIDATOR($input)->required(['street', 'city', 'zip'])->validate();

        $isDefault = (int) ($input['is_default'] ?? 0);

        // If setting as default, clear other defaults of same type first
        if ($isDefault) {
            $this->address->clearDefault($userId, $input['type'] ?? 'billing');
        }

        return $this->address->create([
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
     * Castecna aktualizace adresy (PATCH).
     * Vyzaduje prihlaseni; pouze vlastnik nebo admin.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->auth->require();

        $address = $this->requireEntity($this->address->findById($id), 'Address not found');
        $this->requireOwnerOrAdmin($address);

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
                $this->address->clearDefault((int) $address['user_id'], $type);
            }
            $set['is_default'] = $isDefault;
        }

        if (!empty($set)) {
            $this->address->update($id, $set);
        }

        return $this->address->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada adresy (PUT). Povinna pole: street, city, zip, country.
     * Vyzaduje prihlaseni; pouze vlastnik nebo admin.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function replace(int $id, array $input, ?array $projection = null): array
    {
        $this->auth->require();

        $address = $this->requireEntity($this->address->findById($id), 'Address not found');
        $this->requireOwnerOrAdmin($address);

        VALIDATOR($input)
            ->required(['street', 'city', 'zip', 'country'])
            ->validate();

        $isDefault = (int) ($input['is_default'] ?? 0);
        if ($isDefault) {
            $type = $input['type'] ?? 'billing';
            $this->address->clearDefault((int) $address['user_id'], $type);
        }

        $this->address->update($id, [
            'type'       => $input['type']    ?? 'billing',
            'company'    => $input['company'] ?? '',
            'name'       => $input['name']    ?? null,
            'street'     => $input['street'],
            'city'       => $input['city'],
            'zip'        => $input['zip'],
            'country'    => $input['country'],
            'is_default' => $isDefault,
        ]);

        return $this->address->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze adresu.
     * Vyzaduje prihlaseni; pouze vlastnik nebo admin.
     *
     * @param int $id
     * @return int
     */
    public function delete(int $id): int
    {
        $this->auth->require();

        $address = $this->requireEntity($this->address->findById($id), 'Address not found');
        $this->requireOwnerOrAdmin($address);

        return $this->address->delete($id);
    }
}
