<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Franchise;
use App\Core\Response;

class TextService
{
    private Text $text;

    public function __construct()
    {
        $this->text = new Text(Database::getInstance(), Franchise::code());
    }

    public function list(string $language, ?bool $isActive, ?string $search, string $sortBy, string $sortDir): array
    {
        return $this->text->findAll($language, $isActive, $search, $sortBy, $sortDir);
    }

    public function get(int $id): array
    {
        $text = $this->text->findById($id);
        if (!$text) {
            Response::notFound('Text not found');
        }
        return $text;
    }

    public function getByKey(string $key, string $language): array
    {
        $text = $this->text->findByKey($key, $language);
        if (!$text) {
            Response::notFound("Text with key '$key' not found");
        }
        return $text;
    }

    public function create(string $key, string $title, string $language, array $input): int
    {
        Auth::requireRole('admin');

        $errors = [];
        if ($key   === '') $errors['key']   = 'Required';
        if ($title === '') $errors['title'] = 'Required';
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        if ($this->text->keyExists($key, $language)) {
            Response::error("Key '$key' already exists for language '$language'", 409);
        }

        return $this->text->create([
            'key'        => $key,
            'title'      => $title,
            'content'    => $input['content']   ?? '',
            'language'   => $language,
            'is_active'  => (int) ($input['is_active'] ?? 1),
            'created_by' => Auth::id(),
        ]);
    }

    public function update(int $id, array $input): void
    {
        Auth::requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        $set = [];
        foreach (['key', 'title', 'content', 'language'] as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }
        if (array_key_exists('is_active', $input) && $input['is_active'] !== null) {
            $set['is_active'] = (int) $input['is_active'];
        }

        if (!empty($set)) {
            $this->text->update($id, $set);
        }
    }

    public function replace(int $id, string $key, string $title, array $input): void
    {
        Auth::requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        $errors = [];
        if ($key   === '') $errors['key']   = 'Required';
        if ($title === '') $errors['title'] = 'Required';
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $this->text->update($id, [
            'key'       => $key,
            'title'     => $title,
            'content'   => (string) ($input['content']  ?? ''),
            'language'  => (string) ($input['language'] ?? 'cs'),
            'is_active' => (int)    ($input['is_active'] ?? 1),
        ]);
    }

    public function delete(int $id): void
    {
        Auth::requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        $this->text->delete($id);
    }
}
