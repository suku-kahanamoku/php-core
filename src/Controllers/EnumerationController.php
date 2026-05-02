<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * EnumerationController – manages enumeration/codebook values (ciselnik)
 * Table: enumeration (id, type, code, label, sort_order, is_active, created_at, updated_at)
 *
 * Examples of types: order_status, invoice_status, user_role, vat_rate, currency, country, payment_method
 */
class EnumerationController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** GET /enumerations */
    public function index(Request $request): void
    {
        Auth::require();

        $type     = $request->get('type');
        $isActive = $request->get('is_active');

        $where  = ['1=1'];
        $params = [];

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

        // Group by type if no filter
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
        Auth::require();

        $types = $this->db->fetchAll(
            'SELECT DISTINCT type FROM enumeration ORDER BY type ASC'
        );

        Response::success(array_column($types, 'type'));
    }

    /** GET /enumerations/:id */
    public function show(Request $request, array $params): void
    {
        Auth::require();
        $id = (int) $params['id'];

        $item = $this->db->fetchOne('SELECT * FROM enumeration WHERE id = ?', [$id]);
        if (!$item) {
            Response::notFound('Enumeration not found');
        }

        Response::success($item);
    }

    /** POST /enumerations */
    public function store(Request $request): void
    {
        Auth::requireRole('admin');

        $type  = trim((string) $request->get('type',  ''));
        $code  = trim((string) $request->get('code',  ''));
        $label = trim((string) $request->get('label', ''));

        $errors = [];
        if ($type  === '') $errors['type']  = 'Required';
        if ($code  === '') $errors['code']   = 'Required';
        if ($label === '') $errors['label']  = 'Required';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM enumeration WHERE type = ? AND code = ?',
            [$type, $code]
        );
        if ($exists) {
            Response::error("Code '{$code}' already exists for type '{$type}'", 409);
        }

        $id = $this->db->insert('enumeration', [
            'type'       => $type,
            'code'       => $code,
            'label'      => $label,
            'value'      => $request->get('value') ?? $code,
            'sort_order' => (int) ($request->get('sort_order') ?? 0),
            'is_active'  => (int) ($request->get('is_active') ?? 1),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Enumeration created');
    }

    /** PUT /enumerations/:id */
    public function update(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $item = $this->db->fetchOne('SELECT id FROM enumeration WHERE id = ?', [$id]);
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

        $this->db->update('enumeration', $set, 'id = ?', [$id]);
        Response::success(null, 'Enumeration updated');
    }

    /** DELETE /enumerations/:id */
    public function destroy(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $item = $this->db->fetchOne('SELECT id FROM enumeration WHERE id = ?', [$id]);
        if (!$item) {
            Response::notFound('Enumeration not found');
        }

        $this->db->delete('enumeration', 'id = ?', [$id]);
        Response::success(null, 'Enumeration deleted');
    }
}
