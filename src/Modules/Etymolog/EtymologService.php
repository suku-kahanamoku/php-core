<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

use App\Modules\Auth\Auth;

final class EtymologService
{
    /** @param array<string,EtymologRepository> $repositories */
    public function __construct(private readonly array $repositories, private readonly Auth $auth, private readonly EtymologSyncRepository $sync)
    {
    }

    public function imports(int $nameId): array
    {
        $this->get('names', $nameId);
        return $this->sync->imports($nameId);
    }

    public function runs(int $jobId, int $page, int $limit): array
    {
        $this->get('sync-jobs', $jobId);
        return $this->sync->runs($jobId, $page, $limit);
    }

    private function repository(string $resource): EtymologRepository
    {
        $this->auth->require();
        $definition = ResourceRegistry::get($resource);
        if ($definition['admin'] ?? false) {
            $this->auth->requireRole('admin');
        }
        return $this->repositories[$resource];
    }

    public function list(string $resource, int $page, int $limit, string $sort, string $filter, ?array $projection): array
    {
        $repo = $this->repository($resource);
        if ($filter !== '') {
            $decoded = json_decode($filter, true);
            if (!is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
                throw new EtymologException('q must be a JSON object');
            }
            // The shared filter supports arrays only as operator specifications/IN/range values.
            array_walk_recursive($decoded, static function ($value): void {
                if (!is_scalar($value) && $value !== null) {
                    throw new EtymologException('Invalid filter');
                }
            });
            foreach ($decoded as $spec) {
                if (is_array($spec)) {
                    $value = $spec['value'] ?? null;
                    $operator = $spec['operator'] ?? 'eq';
                    if (is_array($operator)) {
                        $operator = $operator['value'] ?? '';
                    }
                    if (!is_string($operator) || (is_array($value) && !in_array(ltrim($operator, '$'), ['in', 'range'], true))) {
                        throw new EtymologException('Invalid filter operator or value');
                    }
                    foreach ([$value, $spec['$in'] ?? null] as $items) {
                        if (is_array($items) && count(array_filter($items, 'is_array')) > 0) {
                            throw new EtymologException('Nested filter values are not supported');
                        }
                    }
                    foreach (['$eq', '$ne', '$lt', '$lte', '$gt', '$gte', '$regex'] as $op) {
                        if (isset($spec[$op]) && !is_scalar($spec[$op])) {
                            throw new EtymologException('Invalid filter value');
                        }
                    }
                }
            }
        }
        return $repo->findAll($page, $limit, $sort, $filter, $projection);
    }

    public function get(string $resource, int $id, ?array $projection = null): array
    {
        return $this->repository($resource)->findById($id, $projection) ?? throw new EtymologException('Record not found', 404);
    }

    public function save(string $resource, ?int $id, array $input, bool $replace = false): array
    {
        $repo = $this->repository($resource);
        return $repo->exclusive(fn () => $repo->transaction(function () use ($repo, $resource, $id, $input, $replace) {
            $existing = $id === null ? [] : $this->get($resource, $id);
            $data = $this->validate($resource, $input, $existing, $id === null || $replace);
            if ($resource === 'citations' && $id !== null && $data['entry_id'] !== (int)$existing['entry_id']) {
                $this->guardCitation($resource, $existing);
            }
            $actor = $this->auth->id();
            $data['updated_by'] = $actor;
            if ($id === null) {
                $data['created_by'] = $actor;
                return $repo->create($data);
            }
            // A different discovery query must start from the beginning.
            if ($resource === 'sync-jobs' && ($data['kind'] !== $existing['kind'] || $data['language'] !== $existing['language'])) {
                $data['cursor'] = null;
                $data['next_run_at'] = null;
            }
            return $repo->update($id, $data);
        }));
    }

    private function validate(string $resource, array $input, array $existing, bool $replace): array
    {
        $definition = ResourceRegistry::get($resource);
        $unknown = array_diff(array_keys($input), array_keys($definition['fields']));
        if ($unknown !== []) {
            throw new EtymologException('Unknown or read-only fields: '.implode(', ', $unknown));
        }
        if ($replace) {
            foreach ($definition['required'] as $field) {
                if (!array_key_exists($field, $input)) {
                    throw new EtymologException('Required field: '.$field);
                }
            }
        }
        $data = [];
        foreach ($definition['fields'] as $field => [$type, $default]) {
            $value = array_key_exists($field, $input) ? $input[$field] : ($replace ? $default : ($existing[$field] ?? $default));
            if ($value === null && $default === null) {
                $data[$field] = null;
                continue;
            }
            $data[$field] = $this->value($field, $type, $value);
            if (in_array($field, $definition['required'], true) && ($data[$field] === '' || $data[$field] === null)) {
                throw new EtymologException('Required field: '.$field);
            }
        }
        foreach ($definition['references'] ?? [] as $field => $target) {
            if ($data[$field] !== null && !$this->repositories[$target]->findById($data[$field])) {
                throw new EtymologException('Invalid tenant reference: '.$field);
            }
        }
        if (isset($data['year_from'], $data['year_to']) && $data['year_from'] > $data['year_to']) {
            throw new EtymologException('year_from must not exceed year_to');
        }
        if ($resource === 'variants' && $data['target_name_id'] === $data['name_id']) {
            throw new EtymologException('A variant cannot link a name to itself');
        }
        if ($resource === 'entries') {
            if (($data['type'] === 'fiction') !== ($data['certainty'] === 'fiction')) {
                throw new EtymologException('Fiction requires both type and certainty = fiction');
            }
            if ($data['published'] && $data['certainty'] === 'documented' &&
                (!isset($existing['id']) || !$this->repositories['entries']->hasCitation((int)$existing['id']))) {
                throw new EtymologException('Add a citation before publishing a documented entry');
            }
            if ($data['published'] && $data['type'] === 'clerical_error' && $data['certainty'] !== 'documented') {
                throw new EtymologException('Published clerical errors require documented evidence');
            }
        }
        return $data;
    }

    private function value(string $field, string $type, mixed $value): mixed
    {
        $fail = static fn () => throw new EtymologException('Invalid field: '.$field);
        if (str_starts_with($type, 'enum:')) {
            return is_string($value) && in_array($value, explode(',', substr($type, 5)), true) ? $value : $fail();
        }
        if (in_array($type, ['id', 'year', 'count', 'batch', 'interval', 'bool'], true)) {
            if ($type === 'bool' && is_bool($value)) {
                return (int)$value;
            }
            if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                return $fail();
            }
            $number = (int)$value;
            [$min, $max] = match ($type) {
                'id' => [1, 2147483647], 'year' => [-10000, 3000], 'count' => [0, 2147483647],
                'batch' => [1, 50], 'interval' => [300, 2592000], 'bool' => [0, 1],
            };
            return $number >= $min && $number <= $max ? $number : $fail();
        }
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) {
            return $fail();
        }
        $value = trim($value);
        if ($type === 'language') {
            return preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $value) && strlen($value) <= 35 ? $value : $fail();
        }
        if ($type === 'country') {
            return preg_match('/^[A-Z]{2}$/D', $value) ? $value : $fail();
        }
        if ($type === 'url') {
            return strlen($value) <= 2048 && filter_var($value, FILTER_VALIDATE_URL) &&
                in_array(parse_url($value, PHP_URL_SCHEME), ['https', 'http'], true) && !parse_url($value, PHP_URL_USER) ? $value : $fail();
        }
        $max = (int)substr($type, 5);
        // Byte limit also fits the utf8mb4 SQL TEXT storage limit.
        return strlen($value) <= $max ? $value : $fail();
    }

    public function remove(string $resource, int $id, bool $force = false): void
    {
        $repo = $this->repository($resource);
        if ($force) {
            $this->auth->requireRole('admin');
        }
        $repo->exclusive(fn () => $repo->transaction(function () use ($repo, $resource, $id, $force) {
            $item = $this->get($resource, $id);
            if ($repo->hasDependants($id, $force)) {
                throw new EtymologException('Record is in use; remove dependent records first', 409);
            }
            $this->guardCitation($resource, $item);
            if ($force) {
                $repo->hardDelete($id);
            } else {
                $repo->update($id, ['deleted' => 1, 'updated_by' => $this->auth->id()]);
            }
        }));
    }

    /** Preserve published evidence when removing a citation. Unpublish the entry first. */
    private function guardCitation(string $resource, array $item): void
    {
        if ($resource === 'citations') {
            $entry = $this->repositories['entries']->findById((int)$item['entry_id']);
            if ($entry && $entry['published'] && $entry['certainty'] === 'documented') {
                throw new EtymologException('Unpublish the documented entry before removing its citation', 409);
            }
        }
    }
}
