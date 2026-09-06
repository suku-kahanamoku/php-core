<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Auth\Auth;
use App\Modules\BaseService;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Utils\QueryPolicy;

class EnumerationService extends BaseService
{
    private const PUBLIC_TYPES = [
        'contact', 'taste', 'payment', 'shipping', 'wine_color',
        'wine_quality', 'wine_kind', 'country_code',
    ];
    private const PUBLIC_FIELDS = [
        'id', 'type', 'syscode', 'label', 'value', 'position', 'published', 'data',
    ];
    private const PUBLIC_FILTERS = ['id', 'type', 'syscode', 'label', 'value'];
    private const PUBLIC_SORTS = ['id', 'type', 'syscode', 'label', 'value', 'position'];
    private EnumerationRepository $_enum;

    /**
     * Konstruktor tridy EnumerationService.
     *
     * @param Database $db
     * @param string   $franchiseCode
     * @param Auth     $auth
     */
    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->_enum = new EnumerationRepository($db, $franchiseCode);
        $this->_auth = $auth;
    }

    /**
     * Vrati strankovany seznam ciselnikovych polozek. Verejne dostupne.
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
            $decodedFilter = json_decode($filter, true);
            $requestedType = is_array($decodedFilter) ? ($decodedFilter['type'] ?? null) : null;
            $requestedType = is_array($requestedType)
                ? ($requestedType['value'] ?? $requestedType['$eq'] ?? null)
                : $requestedType;
            $typeConstraint = is_string($requestedType)
                && in_array($requestedType, self::PUBLIC_TYPES, true)
                    ? ['value' => $requestedType]
                    : ['$in' => self::PUBLIC_TYPES];
            $filter = QueryPolicy::filter($filter, self::PUBLIC_FILTERS, [
                'deleted' => 0,
                'published' => ['value' => 1],
                'type' => $typeConstraint,
            ]);
            $sort = QueryPolicy::sort($sort, self::PUBLIC_SORTS);
            $projection = QueryPolicy::projection($projection, self::PUBLIC_FIELDS);
        }
        $result = $this->_enum->findAll(
            $page,
            $limit,
            $sort,
            $filter,
            $projection
        );
        return $isAdmin ? $result : QueryPolicy::listFields($result, self::PUBLIC_FIELDS);
    }

    /**
     * Vrati seznam vsech unikatnich typu ciselniku. Verejne dostupne.
     *
     * @return list<string>
     */
    public function types(): array
    {
        if ($this->_auth->hasRole('admin')) {
            return $this->_enum->getTypes();
        }
        return array_values(array_intersect($this->_enum->getTypes(), self::PUBLIC_TYPES));
    }

    /**
     * Vrati ciselnikovou polozku dle ID. Verejne dostupne. Pokud polozka neexistuje, vraci 404.
     *
     * @param  int        $id
     * @param  array|null $projection
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $isAdmin = $this->_auth->hasRole('admin');
        if (!$isAdmin) {
            $visibility = $this->_enum->findById($id);
            if (!$visibility
                || (int) ($visibility['published'] ?? 0) !== 1
                || !in_array((string) ($visibility['type'] ?? ''), self::PUBLIC_TYPES, true)
            ) {
                Response::notFound('Enumeration not found');
            }
            $projection = QueryPolicy::projection($projection, self::PUBLIC_FIELDS);
        }
        $item = $this->_enum->findById($id, $projection);
        $this->_requireEntity($item, 'Enumeration not found');
        return $isAdmin ? $item : QueryPolicy::fields($item, self::PUBLIC_FIELDS);
    }

    /**
     * Vytvori novou ciselnikovou polozku. Vyzaduje roli admin.
     * Kombinace type + syscode musi byt unikatni.
     *
     * @param  string               $type
     * @param  string               $code
     * @param  string               $label
     * @param  array<string, mixed> $input  value, position, published
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function create(
        string $type,
        string $code,
        string $label,
        array $input,
        ?array $projection = null
    ): array {
        $this->_auth->requireRole('admin');

        if ($this->_enum->codeExists($type, $code)) {
            Response::error("Syscode '$code' already exists for type '$type'", 409);
        }

        return $this->_enum->create([
            'type'      => $type,
            'syscode'   => $code,
            'label'     => $label,
            'value'     => $input['value'] ?? $code,
            'position'  => (int) ($input['position'] ?? 0),
            'published' => (int) ($input['published'] ?? 1),
            'data'      => $input['data'] ?? null,
        ], $projection);
    }

    /**
     * Castecna aktualizace ciselnikove polozky (PATCH). Vyzaduje roli admin.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $input  type, syscode, label, value, position, published
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_enum->findById($id), 'Enumeration not found');

        $set        = [];
        $textFields = ['type', 'syscode', 'label', 'value'];
        $intFields  = ['position', 'published'];

        foreach ($textFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = trim((string) $input[$f]);
            }
        }
        foreach ($intFields as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null) {
                $set[$f] = (int) $input[$f];
            }
        }
        if (array_key_exists('data', $input) && $input['data'] !== null) {
            $set['data'] = $input['data'];
        }

        if (!empty($set)) {
            $this->_enum->update($id, $set);
        }

        return $this->_enum->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada ciselnikove polozky (PUT). Vyzaduje roli admin.
     * Povinna pole: type, syscode, label.
     *
     * @param  int                  $id
     * @param  string               $type
     * @param  string               $code
     * @param  string               $label
     * @param  array<string, mixed> $input  value, position, published
     * @param  array|null           $projection
     * @return array<string, mixed>
     */
    public function replace(
        int $id,
        string $type,
        string $code,
        string $label,
        array $input,
        ?array $projection = null,
    ): array {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_enum->findById($id), 'Enumeration not found');

        $this->_enum->update($id, [
            'type'      => $type,
            'syscode'   => $code,
            'label'     => $label,
            'value'     => (string) ($input['value'] ?? $code),
            'position'  => (int)    ($input['position'] ?? 0),
            'published' => (int)    ($input['published'] ?? 1),
            'data'      => $input['data'] ?? null,
        ]);

        return $this->_enum->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze ciselnikovou polozku. Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_enum->findById($id), 'Enumeration not found');

        return $this->_enum->hardDelete($id);
    }

    /**
     * Soft-smazani ciselnikove polozky (oznaci jako smazanou, ponecha v DB).
     * Vyzaduje roli admin.
     *
     * @param  int $id
     * @return int  Pocet ovlivnenych zaznamu (0 nebo 1)
     */
    public function remove(int $id): int
    {
        $this->_auth->requireRole('admin');

        $this->_requireEntity($this->_enum->findById($id), 'Enumeration not found');

        return $this->_enum->softDelete($id);
    }
}
