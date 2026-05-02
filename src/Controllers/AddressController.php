<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Request;
use App\Core\Response;

class AddressController
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->code = Franchise::code();
    }

    /** GET /users/:userId/addresses */
    public function list(Request $request, array $params): void
    {
        Auth::require();
        $userId = (int) $params['userId'];

        if (!Auth::hasRole('admin') && Auth::id() !== $userId) {
            Response::forbidden();
        }

        $items = $this->db->fetchAll(
            'SELECT * FROM address WHERE franchise_code = ? AND user_id = ? ORDER BY type ASC, created_at DESC',
            [$this->code, $userId]
        );

        Response::success($items);
    }

    /** GET /addresses/:id */
    public function get(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ? AND franchise_code = ?', [$id, $this->code]);

        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!Auth::hasRole('admin') && (int) $address['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        Response::success($address);
    }

    /** POST /addresses */
    public function create(Request $request): void
    {
        Auth::require();

        $userId  = Auth::hasRole('admin') && $request->get('user_id')
            ? (int) $request->get('user_id')
            : Auth::id();

        $errors  = [];
        $street  = trim((string) $request->get('street', ''));
        $city    = trim((string) $request->get('city',   ''));
        $zip     = trim((string) $request->get('zip',    ''));
        $country = trim((string) $request->get('country', 'CZ'));

        if ($street  === '') $errors['street']  = 'Required';
        if ($city    === '') $errors['city']    = 'Required';
        if ($zip     === '') $errors['zip']     = 'Required';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $id = $this->db->insert('address', [
            'franchise_code' => $this->code,
            'user_id'      => $userId,
            'type'         => $request->get('type', 'billing'),
            'company'      => $request->get('company', ''),
            'first_name'   => $request->get('first_name', ''),
            'last_name'    => $request->get('last_name',  ''),
            'street'       => $street,
            'city'         => $city,
            'zip'          => $zip,
            'country'      => $country,
            'is_default'   => (int) ($request->get('is_default', 0)),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Address created');
    }

    /** PATCH /addresses/:id */
    public function update(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ? AND franchise_code = ?', [$id, $this->code]);

        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!Auth::hasRole('admin') && (int) $address['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['type', 'company', 'first_name', 'last_name', 'street', 'city', 'zip', 'country'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = trim((string) $v);
        }
        if (($v = $request->get('is_default')) !== null) {
            $set['is_default'] = (int) $v;
        }

        $this->db->update('address', $set, 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Address updated');
    }

    /** PUT /addresses/:id */
    public function replace(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ? AND franchise_code = ?', [$id, $this->code]);

        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!Auth::hasRole('admin') && (int) $address['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        $errors  = [];
        $street  = trim((string) $request->get('street',  ''));
        $city    = trim((string) $request->get('city',    ''));
        $zip     = trim((string) $request->get('zip',     ''));
        $country = trim((string) $request->get('country', ''));

        if ($street  === '') $errors['street']  = 'Required';
        if ($city    === '') $errors['city']    = 'Required';
        if ($zip     === '') $errors['zip']     = 'Required';
        if ($country === '') $errors['country'] = 'Required';
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $this->db->update('address', [
            'type'       => (string) ($request->get('type')       ?? 'billing'),
            'company'    => (string) ($request->get('company')    ?? ''),
            'first_name' => (string) ($request->get('first_name') ?? ''),
            'last_name'  => (string) ($request->get('last_name')  ?? ''),
            'street'     => $street,
            'city'       => $city,
            'zip'        => $zip,
            'country'    => $country,
            'is_default' => (int) ($request->get('is_default') ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        Response::success(null, 'Address replaced');
    }

    /** DELETE /addresses/:id */
    public function delete(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ? AND franchise_code = ?', [$id, $this->code]);

        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!Auth::hasRole('admin') && (int) $address['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        $this->db->delete('address', 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Address deleted');
    }
}
