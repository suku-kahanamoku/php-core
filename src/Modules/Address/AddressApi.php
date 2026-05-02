<?php

declare(strict_types=1);

namespace App\Modules\Address;

use App\Core\Request;
use App\Core\Response;

class AddressApi
{
    private AddressService $service;

    public function __construct()
    {
        $this->service = new AddressService();
    }

    /** GET /users/:userId/addresses */
    public function list(Request $request, array $params): void
    {
        Response::success($this->service->listByUser(
            (int) $params['userId'],
            $request->get('type'),
            (string) $request->get('sort_by', 'is_default'),
            (string) $request->get('sort_dir', 'DESC')
        ));
    }

    /** GET /addresses/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /addresses */
    public function create(Request $request): void
    {
        $id = $this->service->create([
            'type'       => $request->get('type', 'billing'),
            'company'    => $request->get('company',    ''),
            'first_name' => $request->get('first_name', ''),
            'last_name'  => $request->get('last_name',  ''),
            'street'     => trim((string) $request->get('street',  '')),
            'city'       => trim((string) $request->get('city',    '')),
            'zip'        => trim((string) $request->get('zip',     '')),
            'country'    => trim((string) $request->get('country', 'CZ')),
            'is_default' => $request->get('is_default', 0),
        ], $request->get('user_id') !== null ? (int) $request->get('user_id') : null);

        Response::created(['id' => $id], 'Address created');
    }

    /** PATCH /addresses/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'type'       => $request->get('type'),
            'company'    => $request->get('company'),
            'first_name' => $request->get('first_name'),
            'last_name'  => $request->get('last_name'),
            'street'     => $request->get('street'),
            'city'       => $request->get('city'),
            'zip'        => $request->get('zip'),
            'country'    => $request->get('country'),
            'is_default' => $request->get('is_default'),
        ]);
        Response::success(null, 'Address updated');
    }

    /** PUT /addresses/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace((int) $params['id'], [
            'type'       => $request->get('type',       'billing'),
            'company'    => $request->get('company',    ''),
            'first_name' => $request->get('first_name', ''),
            'last_name'  => $request->get('last_name',  ''),
            'street'     => trim((string) $request->get('street',  '')),
            'city'       => trim((string) $request->get('city',    '')),
            'zip'        => trim((string) $request->get('zip',     '')),
            'country'    => trim((string) $request->get('country', '')),
            'is_default' => $request->get('is_default', 0),
        ]);
        Response::success(null, 'Address replaced');
    }

    /** DELETE /addresses/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Address deleted');
    }
}
