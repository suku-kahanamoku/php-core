<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class AddressService
{
    private Address $address;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->address = new Address($db, $franchiseCode);
        $this->auth = $auth;
    }

    public function listByUser(
        int $userId,
        ?string $type,
        string $sortBy,
        string $sortDir,
    ): array {
        $this->auth->require();

        if (!$this->auth->hasRole('admin') && $this->auth->id() !== $userId) {
            Response::forbidden();
        }

        return $this->address->findByUser($userId, $type, $sortBy, $sortDir);
    }

    public function get(int $id): array
    {
        $this->auth->require();

        $address = $this->address->findById($id);
        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $address['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        return $address;
    }

    public function create(array $input, ?int $overrideUserId = null): int
    {
        $this->auth->require();

        $userId = ($this->auth->hasRole('admin') && $overrideUserId !== null)
            ? $overrideUserId
            : $this->auth->id();

        Validator::make($input)->required(['street', 'city', 'zip'])->validate();

        $isDefault = (int) ($input['is_default'] ?? 0);

        // If setting as default, clear other defaults of same type first
        if ($isDefault) {
            $this->address->clearDefault($userId, $input['type'] ?? 'billing');
        }

        return $this->address->create([
            'user_id'    => $userId,
            'type'       => $input['type']       ?? 'billing',
            'company'    => $input['company']    ?? '',
            'first_name' => $input['first_name'] ?? '',
            'last_name'  => $input['last_name']  ?? '',
            'street'     => $input['street'],
            'city'       => $input['city'],
            'zip'        => $input['zip'],
            'country'    => $input['country'] ?? 'CZ',
            'is_default' => $isDefault,
        ]);
    }

    public function update(int $id, array $input): void
    {
        $this->auth->require();

        $address = $this->address->findById($id);
        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $address['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        $set        = [];
        $textFields = [
            'type', 'company', 'first_name', 'last_name',
            'street', 'city', 'zip', 'country',
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
    }

    public function replace(int $id, array $input): void
    {
        $this->auth->require();

        $address = $this->address->findById($id);
        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $address['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        Validator::make($input)
            ->required(['street', 'city', 'zip', 'country'])
            ->validate();

        $isDefault = (int) ($input['is_default'] ?? 0);
        if ($isDefault) {
            $type = $input['type'] ?? 'billing';
            $this->address->clearDefault((int) $address['user_id'], $type);
        }

        $this->address->update($id, [
            'type'       => $input['type']       ?? 'billing',
            'company'    => $input['company']    ?? '',
            'first_name' => $input['first_name'] ?? '',
            'last_name'  => $input['last_name']  ?? '',
            'street'     => $input['street'],
            'city'       => $input['city'],
            'zip'        => $input['zip'],
            'country'    => $input['country'],
            'is_default' => $isDefault,
        ]);
    }

    public function delete(int $id): void
    {
        $this->auth->require();

        $address = $this->address->findById($id);
        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!$this->auth->hasRole('admin') && (int) $address['user_id'] !== $this->auth->id()) {
            Response::forbidden();
        }

        $this->address->delete($id);
    }
}
