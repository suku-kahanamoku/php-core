<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * TextController – manages CMS-style text blocks / pages
 * Table: text (id, key, title, content, language, is_active, created_by, created_at, updated_at)
 */
class TextController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** GET /texts */
    public function index(Request $request): void
    {
        $lang     = $request->get('language', 'cs');
        $isActive = $request->get('is_active');
        $search   = $request->get('search');

        $where  = ['language = ?'];
        $params = [$lang];

        if ($isActive !== null) {
            $where[]  = 'is_active = ?';
            $params[] = (int) $isActive;
        }
        if ($search) {
            $where[]  = '(`key` LIKE ? OR title LIKE ? OR content LIKE ?)';
            $s = '%' . $search . '%';
            $params = [...$params, $s, $s, $s];
        }

        $whereStr = implode(' AND ', $where);
        $items = $this->db->fetchAll(
            "SELECT id, `key`, title, language, is_active, created_at, updated_at
             FROM text WHERE {$whereStr} ORDER BY `key` ASC",
            $params
        );

        Response::success($items);
    }

    /** GET /texts/:id */
    public function show(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        $text = $this->db->fetchOne('SELECT * FROM text WHERE id = ?', [$id]);
        if (!$text) {
            Response::notFound('Text not found');
        }

        Response::success($text);
    }

    /** GET /texts/by-key/:key */
    public function showByKey(Request $request, array $params): void
    {
        $key  = $params['key'];
        $lang = $request->get('language', 'cs');

        $text = $this->db->fetchOne(
            'SELECT * FROM text WHERE `key` = ? AND language = ?',
            [$key, $lang]
        );

        if (!$text) {
            Response::notFound("Text with key '{$key}' not found");
        }

        Response::success($text);
    }

    /** POST /texts */
    public function store(Request $request): void
    {
        Auth::requireRole('admin');

        $key   = trim((string) $request->get('key', ''));
        $title = trim((string) $request->get('title', ''));
        $lang  = trim((string) $request->get('language', 'cs'));

        $errors = [];
        if ($key   === '') $errors['key']   = 'Required';
        if ($title === '') $errors['title']  = 'Required';

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $exists = $this->db->fetchOne('SELECT id FROM text WHERE `key` = ? AND language = ?', [$key, $lang]);
        if ($exists) {
            Response::error("Key '{$key}' already exists for language '{$lang}'", 409);
        }

        $id = $this->db->insert('text', [
            'key'        => $key,
            'title'      => $title,
            'content'    => $request->get('content') ?? '',
            'language'   => $lang,
            'is_active'  => (int) ($request->get('is_active') ?? 1),
            'created_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::created(['id' => $id], 'Text created');
    }

    /** PUT /texts/:id */
    public function update(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $text = $this->db->fetchOne('SELECT id FROM text WHERE id = ?', [$id]);
        if (!$text) {
            Response::notFound('Text not found');
        }

        $set = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['key', 'title', 'content', 'language'] as $f) {
            if (($v = $request->get($f)) !== null) $set[$f] = trim((string) $v);
        }
        if (($v = $request->get('is_active')) !== null) {
            $set['is_active'] = (int) $v;
        }

        $this->db->update('text', $set, 'id = ?', [$id]);
        Response::success(null, 'Text updated');
    }

    /** DELETE /texts/:id */
    public function destroy(Request $request, array $params): void
    {
        Auth::requireRole('admin');
        $id = (int) $params['id'];

        $text = $this->db->fetchOne('SELECT id FROM text WHERE id = ?', [$id]);
        if (!$text) {
            Response::notFound('Text not found');
        }

        $this->db->delete('text', 'id = ?', [$id]);
        Response::success(null, 'Text deleted');
    }
}
