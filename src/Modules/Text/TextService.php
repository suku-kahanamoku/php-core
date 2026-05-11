<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class TextService
{
    private TextRepository $text;
    private Auth $auth;

    /**
     * TextService constructor.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->text = new TextRepository($db, $franchiseCode);
        $this->auth = $auth;
    }

    /**
     * Vrati strankovany seznam CMS textu. Verejne dostupne.
     *
     * @param  int         $page
     * @param  int         $limit
     * @param  string      $sort
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
    public function list(
        int $page = 1,
        int $limit = 20,
        string $sort = '',
        string $filter = '',
        ?array $projection = null,
    ): array {
        return $this->text->findAll(
            $page,
            $limit,
            $sort,
            $filter,
            $projection
        );
    }

    /**
     * Vrati CMS text dle ID. Verejne dostupne. Pokud text neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $text = $this->text->findById($id, $projection);
        if (!$text) {
            Response::notFound('Text not found');
        }
        return $text;
    }

    /**
     * Vrati CMS text dle syscode a jazyka. Verejne dostupne. Pokud text neexistuje, vraci 404.
     *
     * @param  string $key
     * @param  string $language
     * @return array<string, mixed>
     */
    public function getByKey(string $key, string $language): array
    {
        $text = $this->text->findByKey($key, $language);
        if (!$text) {
            Response::notFound("Text with key '$key' not found");
        }
        return $text;
    }

    /**
     * Vytvori novy CMS text. Vyzaduje roli admin.
     * Kombinace syscode + language musi byt unikatni.
     *
     * @param  string               $key
     * @param  string               $title
     * @param  string               $language
     * @param  array<string, mixed> $input  content, is_active
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(
        string $key,
        string $title,
        string $language,
        array $input,
        ?array $projection = null,
    ): array {
        $this->auth->requireRole('admin');

        VALIDATOR(['syscode' => $key, 'title' => $title])
            ->required(['syscode', 'title'])
            ->validate();

        if ($this->text->keyExists($key, $language)) {
            Response::error(
                "Syscode '$key' already exists for language '$language'",
                409
            );
        }

        return $this->text->create([
            'syscode'    => $key,
            'title'      => $title,
            'content'    => $input['content'] ?? '',
            'language'   => $language,
            'is_active'  => (int) ($input['is_active'] ?? 1),
            'created_by' => $this->auth->id(),
        ], $projection);
    }

    /**
     * Castecna aktualizace CMS textu (PATCH). Vyzaduje roli admin.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input  syscode, title, content, language, is_active
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
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

        return $this->text->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada CMS textu (PUT). Vyzaduje roli admin. Povinna pole: syscode, title.
     *
     * @param  int                  $id
     * @param  string               $key
     * @param  string               $title
     * @param  array<string, mixed> $input  content, language, is_active
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function replace(
        int $id,
        string $key,
        string $title,
        array $input,
        ?array $projection = null
    ): array {
        $this->auth->requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        VALIDATOR(['syscode' => $key, 'title' => $title])
            ->required(['syscode', 'title'])
            ->validate();

        $this->text->update($id, [
            'syscode'   => $key,
            'title'     => $title,
            'content'   => (string) ($input['content'] ?? ''),
            'language'  => (string) ($input['language'] ?? 'cs'),
            'is_active' => (int)    ($input['is_active'] ?? 1),
        ]);

        return $this->text->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze CMS text. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        if (!$this->text->findById($id)) {
            Response::notFound('Text not found');
        }

        return $this->text->delete($id);
    }
}
