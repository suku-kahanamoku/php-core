<?php

declare(strict_types=1);

namespace App\Modules\Text;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Utils\QueryPolicy;

class TextService extends BaseService
{
    private const PUBLIC_FIELDS = ['id', 'syscode', 'title', 'content', 'language', 'published'];
    private const PUBLIC_FILTERS = ['id', 'syscode', 'title', 'language'];
    private const PUBLIC_SORTS = ['id', 'syscode', 'title', 'language', 'created_at', 'updated_at'];
    private TextRepository $_text;

    /**
     * Konstruktor tridy TextService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_text = new TextRepository($db, $franchiseCode);
        $this->_auth = $auth;
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
        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin) {
            $filter = QueryPolicy::filter($filter, self::PUBLIC_FILTERS, [
                'deleted' => 0,
                'published' => ['value' => 1],
            ]);
            $sort = QueryPolicy::sort($sort, self::PUBLIC_SORTS);
            $projection = QueryPolicy::projection($projection, self::PUBLIC_FIELDS);
        }
        $result = $this->_text->findAll(
            $page,
            $limit,
            $sort,
            $filter,
            $projection
        );
        return $isAdmin ? $result : QueryPolicy::listFields($result, self::PUBLIC_FIELDS);
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
        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin) {
            $visibility = $this->_text->findById($id, ['published']);
            if (!$visibility || (int) ($visibility['published'] ?? 0) !== 1) {
                Response::notFound('Text not found');
            }
            $projection = QueryPolicy::projection($projection, self::PUBLIC_FIELDS);
        }
        $text = $this->_text->findById($id, $projection);
        if (!$text) {
            Response::notFound('Text not found');
        }
        return $isAdmin ? $text : QueryPolicy::fields($text, self::PUBLIC_FIELDS);
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
        $text = $this->_text->findByKey($key, $language);
        if (!$text || (!$this->_auth->hasRole('admin') && (int) ($text['published'] ?? 0) !== 1)) {
            Response::notFound("Text with key '$key' not found");
        }
        return $this->_auth->hasRole('admin')
            ? $text
            : QueryPolicy::fields($text, self::PUBLIC_FIELDS);
    }

    /**
     * Vytvori novy CMS text. Vyzaduje roli admin.
     * Kombinace syscode + language musi byt unikatni.
     *
     * @param  string               $key
     * @param  string               $title
     * @param  string               $language
     * @param  array<string, mixed> $input  content, published
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
        $this->_auth->requireRole('admin');

        if ($this->_text->keyExists($key, $language)) {
            Response::error(
                "Syscode '$key' already exists for language '$language'",
                409
            );
        }

        return $this->_text->create([
            'syscode'    => $key,
            'title'      => $title,
            'content'    => $input['content'] ?? '',
            'language'   => $language,
            'published'  => (int) ($input['published'] ?? 1),
        ], $projection);
    }

    /**
     * Castecna aktualizace CMS textu (PATCH). Vyzaduje roli admin.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input  syscode, title, content, language, published
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        if (!$this->_text->findById($id)) {
            Response::notFound('Text not found');
        }

        $set        = [];
        $textFields = ['syscode', 'title', 'content', 'language'];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }
        if (array_key_exists('published', $input) && $input['published'] !== null) {
            $set['published'] = (int) $input['published'];
        }

        if (!empty($set)) {
            $this->_text->update($id, $set);
        }

        return $this->_text->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada CMS textu (PUT). Vyzaduje roli admin. Povinna pole: syscode, title.
     *
     * @param  int                  $id
     * @param  string               $key
     * @param  string               $title
     * @param  array<string, mixed> $input  content, language, published
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
        $this->_auth->requireRole('admin');

        if (!$this->_text->findById($id)) {
            Response::notFound('Text not found');
        }

        $this->_text->update($id, [
            'syscode'   => $key,
            'title'     => $title,
            'content'   => (string) ($input['content'] ?? ''),
            'language'  => (string) ($input['language'] ?? 'cs'),
            'published' => (int)    ($input['published'] ?? 1),
        ]);

        return $this->_text->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze CMS text. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->_auth->requireRole('admin');

        if (!$this->_text->findById($id)) {
            Response::notFound('Text not found');
        }

        return $this->_text->hardDelete($id);
    }

    /**
     * Soft-smazani textu (oznaci jako smazany, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_text->findById($id), 'Text not found');

        return $this->_text->softDelete($id);
    }
}
