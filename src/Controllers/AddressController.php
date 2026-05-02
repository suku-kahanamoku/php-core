<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class AddressController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** GET /users/:userId/addresses */
    public function index(Request $request, array $params): void
    {
        Auth::require();
        $userId = (int) $params['userId'];

        if (!Auth::hasRole('admin') && Auth::id() !== $userId) {
            Response::forbidden();
        }

        $items = $this->db->fetchAll(
            'SELECT * FROM address WHERE user_id = ? ORDER BY type ASC, created_at DESC',
            [$userId]
        );

        Response::success($items);
    }

    /** GET /addresses/:id */
    public function show(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ?', [$id]);

        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!Auth::hasRole('admin') && (int) $address['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        Response::success($address);
    }

    /** POST /addresses */
    public function store(Request $request): void
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
            'user_id'    => $userId,
            'type'       => $request->get('type', 'billing'),
            'company'    => $request->get('company', ''),
            'first_name' => $request->get('first_name', ''),
            'last_name'  => $request->get('last_name',  ''),
            'street'     => $street,
            'city'       => $city,
            'zip'        => $zip,
            'country'    => $country,
            'is_default' => (int) ($request->get('is_default', 0)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Address created');
    }

    /** PUT /addresses/:id */
    public function update(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ?', [$id]);

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

        $this->db->update('address', $set, 'id = ?', [$id]);
        Response::success(null, 'Address updated');
    }

    /** DELETE /addresses/:id */
    public function destroy(Request $request, array $params): void
    {
        Auth::require();
        $id      = (int) $params['id'];
        $address = $this->db->fetchOne('SELECT * FROM address WHERE id = ?', [$id]);

        if (!$address) {
            Response::notFound('Address not found');
        }

        if (!Auth::hasRole('admin') && (int) $address['user_id'] !== Auth::id()) {
            Response::forbidden();
        }

        $this->db->delete('address', 'id = ?', [$id]);
        Response::success(null, 'Address deleted');
    }
}
