<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class TextService
{
    private TextRepository $text;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->text = new TextRepository($db, $franchiseCode);
        $this->auth = $auth;
    }

    public function list(
        string $language,
        ?bool $isActive,
        ?string $search,
        string $sortBy,
        string $sortDir,
    ): array {
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

    public function create(
        string $key,
        string $title,
        string $language,
        array $input,
    ): int {
        $this->auth->requireRole('admin');

        Validator::make(['syscode' => $key, 'title' => $title])
            ->required(['syscode', 'title'])
            ->validate();

        if ($this->text->keyExists($key, $language)) {
            Response::error("Syscode '$key' already exists for language '$language'", 409);
        }

        return $this->text->create([
            'syscode'    => $key,
            'title'      => $title,
            'content'    => $input['content'] ?? '',
            'language'   => $language,
            'is_active'  => (int) ($input['is_active'] ?? 1),
            'created_by' => $this->auth->id(),
        ]);
    }

    public function update(int $id, array $input): void
    {
        $this->auth->requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        $set        = [];
        $textFields = ['syscode', 'title', 'content', 'language'];

        foreach ($textFields as $f) {
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
        $this->auth->requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        Validator::make(['syscode' => $key, 'title' => $title])
            ->required(['syscode', 'title'])
            ->validate();

        $this->text->update($id, [
            'syscode'   => $key,
            'title'     => $title,
            'content'   => (string) ($input['content'] ?? ''),
            'language'  => (string) ($input['language'] ?? 'cs'),
            'is_active' => (int)    ($input['is_active'] ?? 1),
        ]);
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        $this->text->delete($id);
    }
}
