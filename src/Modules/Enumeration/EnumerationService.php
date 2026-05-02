<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Auth\Auth;
use App\Modules\Database\Database;
use App\Modules\Router\Response;
use App\Modules\Validator\Validator;

class EnumerationService
{
    private Enumeration $enum;
    private Auth $auth;

    public function __construct(Database $db, string $franchiseCode, Auth $auth)
    {
        $this->enum = new Enumeration($db, $franchiseCode);
        $this->auth = $auth;
    }

    public function list(
        ?string $type,
        ?bool $isActive,
        string $sortBy,
        string $sortDir,
    ): array {
        $items = $this->enum->findAll($type, $isActive, $sortBy, $sortDir);

        if ($type === null) {
            $grouped = [];
            foreach ($items as $item) {
                $grouped[$item['type']][] = $item;
            }
            return $grouped;
        }

        return $items;
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

        Validator::make(['type' => $type, 'code' => $code, 'label' => $label])
            ->required(['type', 'code', 'label'])
            ->validate();

        if ($this->enum->codeExists($type, $code)) {
            Response::error("Code '$code' already exists for type '$type'", 409);
        }

        return $this->enum->create([
            'type'       => $type,
            'code'       => $code,
            'label'      => $label,
            'value'      => $input['value'] ?? $code,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active'  => (int) ($input['is_active'] ?? 1),
        ]);
    }

    public function update(int $id, array $input): void
    {
        $this->auth->requireRole('admin');

        if (!$this->enum->findById($id)) {
            Response::notFound('Enumeration not found');
        }

        $set        = [];
        $textFields = ['type', 'code', 'label', 'value'];
        $intFields  = ['sort_order', 'is_active'];

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

        Validator::make(['type' => $type, 'code' => $code, 'label' => $label])
            ->required(['type', 'code', 'label'])
            ->validate();

        $this->enum->update($id, [
            'type'       => $type,
            'code'       => $code,
            'label'      => $label,
            'value'      => (string) ($input['value'] ?? $code),
            'sort_order' => (int)    ($input['sort_order'] ?? 0),
            'is_active'  => (int)    ($input['is_active'] ?? 1),
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
