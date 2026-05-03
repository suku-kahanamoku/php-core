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

    public function list(
        ?string $type,
        ?bool $isActive,
        string $sort = '',
        int $page = 1,
        int $limit = 20,
    ): array {
        return $this->enum->findAll($type, $isActive, $sort, $page, $limit);
    }

    public function types(): array
    {
        return $this->enum->getTypes();
    }

    public function get(int $id): array
    {
        $item = $this->enum->findById($id);
        if (!$item) {
            Response::notFound('Enumeration not found');
        }
        return $item;
    }

    public function create(string $type, string $code, string $label, array $input): int
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
        ]);
    }

    public function update(int $id, array $input): void
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
    }

    public function replace(
        int $id,
        string $type,
        string $code,
        string $label,
        array $input,
    ): void {
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
    }

    public function delete(int $id): void
    {
        $this->auth->requireRole('admin');

        if (!$this->enum->findById($id)) {
            Response::notFound('Enumeration not found');
        }

        $this->enum->delete($id);
    }
}
