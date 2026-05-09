<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;

class EnumerationService
{
    private EnumerationRepository $enum;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->enum = new EnumerationRepository($db, $franchiseCode);
        $this->auth = $auth;
    }

    /**
     * Vrati strankovany seznam ciselnikovych polozek. Verejne dostupne.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, limit: int, totalPages: int}
     */
    public function list(
        ?string $type,
        ?bool $isActive,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
        string $filter = '',
        ?array $projection = null,
    ): array {
        return $this->enum->findAll($type, $isActive, $sort, $page, $limit, $filter, $projection);
    }

    /**
     * Vrati seznam vsech unikatnich typu ciselnikú. Verejne dostupne.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return $this->enum->getTypes();
    }

    /**
     * Vrati ciselnikovou polozku dle ID. Verejne dostupne. Pokud polozka neexistuje, vraci 404.
     *
     * @return array<string, mixed>
     */
    public function get(int $id, ?array $projection = null): array
    {
        $item = $this->enum->findById($id, $projection);
        if (!$item) {
            Response::notFound('Enumeration not found');
        }
        return $item;
    }

    /**
     * Vytvori novou ciselnikovou polozku. Vyzaduje roli admin.
     * Kombinace type + syscode musi byt unikatni.
     *
     * @param  array<string, mixed> $input  value, position, is_active
     * @return array<string, mixed>
     */
    public function create(string $type, string $code, string $label, array $input, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        VALIDATOR(['type' => $type, 'syscode' => $code, 'label' => $label])
            ->required(['type', 'syscode', 'label'])
            ->validate();

        if ($this->enum->codeExists($type, $code)) {
            Response::error("Syscode '$code' already exists for type '$type'", 409);
        }

        return $this->enum->create([
            'type'      => $type,
            'syscode'   => $code,
            'label'     => $label,
            'value'     => $input['value'] ?? $code,
            'position'  => (int) ($input['position'] ?? 0),
            'is_active' => (int) ($input['is_active'] ?? 1),
        ], $projection);
    }

    /**
     * Castecna aktualizace ciselnikove polozky (PATCH). Vyzaduje roli admin.
     *
     * @param  array<string, mixed> $input  type, syscode, label, value, position, is_active
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $projection = null): array
    {
        $this->auth->requireRole('admin');

        if (!$this->enum->findById($id)) {
            Response::notFound('Enumeration not found');
        }

        $set        = [];
        $textFields = ['type', 'syscode', 'label', 'value'];
        $intFields  = ['position', 'is_active'];

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

        if (!empty($set)) {
            $this->enum->update($id, $set);
        }

        return $this->enum->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Uplna nahrada ciselnikove polozky (PUT). Vyzaduje roli admin.
     * Povinna pole: type, syscode, label.
     *
     * @param  array<string, mixed> $input  value, position, is_active
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
        $this->auth->requireRole('admin');

        if (!$this->enum->findById($id)) {
            Response::notFound('Enumeration not found');
        }

        VALIDATOR(['type' => $type, 'syscode' => $code, 'label' => $label])
            ->required(['type', 'syscode', 'label'])
            ->validate();

        $this->enum->update($id, [
            'type'      => $type,
            'syscode'   => $code,
            'label'     => $label,
            'value'     => (string) ($input['value'] ?? $code),
            'position'  => (int)    ($input['position'] ?? 0),
            'is_active' => (int)    ($input['is_active'] ?? 1),
        ]);

        return $this->enum->findById($id, $projection) ?? ['id' => $id];
    }

    /**
     * Smaze ciselnikovou polozku. Vyzaduje roli admin.
     *
     * @return int  Pocet smazanych zaznamu (0 nebo 1)
     */
    public function delete(int $id): int
    {
        $this->auth->requireRole('admin');

        if (!$this->enum->findById($id)) {
            Response::notFound('Enumeration not found');
        }

        return $this->enum->delete($id);
    }
}
