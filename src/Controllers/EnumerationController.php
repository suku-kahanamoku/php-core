<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Request;
use App\Core\Response;

class EnumerationController
{
    private Database $db;
    private string   $code;

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->code = Franchise::code();
    }

    /** GET /enumerations */
    public function list(Request $request): void
    {
        $type     = $request->get('type');
        $isActive = $request->get('is_active');

        $where  = ['franchise_code = ?'];
        $params = [$this->code];

        if ($type) {
            $where[]  = 'type = ?';
            $params[] = $type;
        }
        if ($isActive !== null) {
            $where[]  = 'is_active = ?';
            $params[] = (int) $isActive;
        }

        $whereStr = implode(' AND ', $where);
        $items = $this->db->fetchAll(
            "SELECT * FROM enumeration WHERE {$whereStr} ORDER BY type ASC, sort_order ASC, label ASC",
            $params
        );

        if (!$type) {
            $grouped = [];
            foreach ($items as $item) {
                $grouped[$item['type']][] = $item;
            }
            Response::success($grouped);
        }

        Response::success($items);
    }

    /** GET /enumerations/types */
    public function types(Request $request): void
    {
        $types = $this->db->fetchAll(
            'SELECT DISTINCT type FROM enumeration WHERE franchise_code = ? ORDER BY type ASC',
            [$this->code]
        );

        Response::success(array_column($types, 'type'));
    }

    /** GET /enumerations/:id */
    public function get(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        $item = $this->db->fetchOne(
            'SELECT * FROM enumeration WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$item) {
            Response::notFound('Enumeration not found');
        }

        Response::success($item);
    }

    /** POST /enumerations */
    public function create(Request $request): void
    {
        Auth::requireRole('admin');

        $type  = trim((string) $request->get('type',  ''));
        $code  = trim((string) $request->get('code',  ''));
        $label = trim((string) $request->get('label', ''));

        $errors = [];
        if ($type  === '') $errors['type']  = 'Required';
        if ($code  === '') $errors['code']  = 'Required';
        if ($label === '') $errors['label'] = 'Required';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM enumeration WHERE franchise_code = ? AND type = ? AND code = ?',
            [$this->code, $type, $code]
        );
        if ($exists) {
            Response::error("Code '$code' already exists for type '$type'", 409);
        }

        $id = $this->db->insert('enumeration', [
            'franchise_code' => $this->code,
            'type'         => $type,
            'code'         => $code,
            'label'        => $label,
            'value'        => $request->get('value') ?? $code,
            'sort_order'   => (int) ($request->get('sort_order') ?? 0),
            'is_active'    => (int) ($request->get('is_active') ?? 1),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Enumeration created');
    }

    /** PATCH /enumerations/:id */
    public function update(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $item = $this->db->fetchOne(
            'SELECT id FROM enumeration WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$item) {
            Response::notFound('Enumeration not found');
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['type', 'code', 'label', 'value'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = trim((string) $v);
        }
        foreach (['sort_order', 'is_active'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = (int) $v;
        }

        $this->db->update('enumeration', $set, 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Enumeration updated');
    }

    /** PUT /enumerations/:id */
    public function replace(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $item = $this->db->fetchOne(
            'SELECT id FROM enumeration WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$item) {
            Response::notFound('Enumeration not found');
        }

        $errors = [];
        $type  = trim((string) $request->get('type',  ''));
        $code  = trim((string) $request->get('code',  ''));
        $label = trim((string) $request->get('label', ''));

        if ($type  === '') $errors['type']  = 'Required';
        if ($code  === '') $errors['code']  = 'Required';
        if ($label === '') $errors['label'] = 'Required';
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $this->db->update('enumeration', [
            'type'       => $type,
            'code'       => $code,
            'label'      => $label,
            'value'      => (string) ($request->get('value')      ?? $code),
            'sort_order' => (int)    ($request->get('sort_order') ?? 0),
            'is_active'  => (int)    ($request->get('is_active')  ?? 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND franchise_code = ?', [$id, $this->code]);

        Response::success(null, 'Enumeration replaced');
    }

    /** DELETE /enumerations/:id */
    public function delete(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $item = $this->db->fetchOne(
            'SELECT id FROM enumeration WHERE id = ? AND franchise_code = ?',
            [$id, $this->code]
        );
        if (!$item) {
            Response::notFound('Enumeration not found');
        }

        $this->db->delete('enumeration', 'id = ? AND franchise_code = ?', [$id, $this->code]);
        Response::success(null, 'Enumeration deleted');
    }
}
