<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

use App\Modules\Auth\Auth;

final class EtymologService
{
    /** @param array<string,EtymologRepository> $repositories */
    public function __construct(private readonly array $repositories, private readonly Auth $auth, private readonly EtymologSyncRepository $sync, private readonly EtymologStoryRepository $stories, private readonly EtymologExternalRepository $external, private readonly ?EtymologBackgroundService $background = null)
    {
    }

    public function startSync(array $input): array
    {
        $this->auth->requireRole('admin');
        if ($input !== []) { throw new EtymologException('Synchronization accepts no parameters'); }
        return ($this->background ?? throw new EtymologException('Worker unavailable', 503))->start($this->auth->id());
    }

    public function syncStatus(): ?array
    {
        $this->auth->requireRole('admin');
        return ($this->background ?? throw new EtymologException('Worker unavailable', 503))->status();
    }

    public function imports(int $nameId): array
    {
        $this->get('names', $nameId);
        return $this->sync->imports($nameId);
    }

    public function storyImports(int $entryId): array
    {
        $this->get('entries', $entryId);
        return [...$this->stories->imports($entryId), ...$this->external->imports('entries', $entryId)];
    }

    public function externalImports(string $resource, int $id): array
    {
        $this->get($resource, $id);
        return $this->external->imports($resource, $id);
    }

    public function resetJob(int $id): array
    {
        $repo = $this->repository('sync-jobs');
        return $repo->exclusive(fn () => $repo->transaction(function () use ($repo, $id) {
            $this->get('sync-jobs', $id);
            return $repo->update($id, ['cursor' => null, 'next_run_at' => null, 'last_error' => null, 'last_status' => 'reset', 'updated_by' => $this->auth->id()]);
        }));
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
            if ($resource === 'sources' && $id !== null && $repo->hasPublishedEvidenceUse($id)) {
                throw new EtymologException('Unpublish dependent cultural entries or calendar dates before editing their source', 409);
            }
            if ($resource === 'citations' && $id !== null && ($data['entry_id'] !== (int)$existing['entry_id'] || $this->repositories['entries']->isPublishedCultural((int)$existing['entry_id']))) {
                $this->guardCitation($resource, $existing);
            }
            $actor = $this->auth->id();
            $data['updated_by'] = $actor;
            if ($id === null) {
                $data['created_by'] = $actor;
                return $repo->create($data);
            }
            // A different discovery query must start from the beginning.
            if ($resource === 'sync-jobs' && ($data['kind'] !== $existing['kind'] || $data['language'] !== $existing['language'] || $data['provider'] !== $existing['provider'])) {
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
        if ($resource === 'sync-jobs') {
            $valid = match ($data['provider']) {
                'wikipedia-names' => in_array($data['kind'], ['etymologies', 'culture'], true) && $data['language'] === 'cs' && $data['batch_size'] <= 3,
                'erben-folklore' => $data['kind'] === 'folklore' && $data['language'] === 'cs' && $data['batch_size'] <= 4,
                'czech-namedays' => $data['kind'] === 'calendar' && $data['language'] === 'cs',
                'wikidata' => in_array($data['kind'], ['given', 'surname'], true) && $data['batch_size'] <= 50,
                'wikisource' => $data['kind'] === 'stories' && $data['language'] === 'cs' && $data['batch_size'] <= 4,
                'wiktionary', 'wiktionary-cs', 'wiktionary-fr' => in_array($data['kind'], ['given', 'surname', 'given_priority', 'surname_priority'], true) && $data['batch_size'] <= 3 && ($data['provider'] === 'wiktionary' || $data['language'] === 'cs') && (!str_ends_with($data['kind'], '_priority') || $data['language'] === 'cs'),
                'csu-baby-names' => $data['kind'] === 'births_2025' && $data['language'] === 'cs',
                'poland-pesel' => in_array($data['kind'], ['surname_male', 'surname_female'], true) && $data['language'] === 'pl',
            };
            if (!$valid) { throw new EtymologException('Invalid provider language/kind/batch_size combination'); }
        }
        if ($resource === 'entry-names' && $this->repositories['entry-names']->hasActiveLink($data['entry_id'], $data['name_id'], isset($existing['id']) ? (int)$existing['id'] : null)) {
            throw new EtymologException('This entry is already linked to the name', 409);
        }
        if ($resource === 'occurrences' && $data['observed_on'] !== null && (int)substr($data['observed_on'], 0, 4) !== $data['observed_year']) {
            throw new EtymologException('observed_on must match observed_year');
        }
        if ($resource === 'calendar-days') {
            if ($data['date_kind'] === 'fixed') {
                if ($data['month'] === null || $data['day'] === null || !checkdate($data['month'], $data['day'], 2000) || $data['date_rule'] !== null) {
                    throw new EtymologException('Fixed dates require valid month/day and no date_rule');
                }
            } elseif ($data['month'] !== null || $data['day'] !== null || !$data['date_rule']) {
                throw new EtymologException('Movable dates require date_rule and no fixed month/day');
            }
            if ($data['kind'] === 'name_day' && ($data['name_id'] === null || $this->repositories['names']->findById($data['name_id'])['kind'] !== 'given')) {
                throw new EtymologException('Name days require a given name');
            }
            if ($data['kind'] === 'folklore' && $data['entry_id'] === null) { throw new EtymologException('Folklore date requires an entry'); }
            $source = $this->repositories['sources']->findById($data['source_id']);
            if ($data['published'] && (!$source['license'] || (!$source['attribution'] && !$source['author']))) {
                throw new EtymologException('Published calendar dates require source licence and attribution');
            }
        }
        if ($resource === 'entries') {
            if (in_array($data['type'], ResourceRegistry::CULTURAL_TYPES, true)) {
                if (!$data['source_url']) { throw new EtymologException('Cultural content requires its original web source_url; AI invention is not allowed'); }
                if ($data['published'] && (!isset($existing['id']) || !$this->repositories['entries']->hasWebQuotation((int)$existing['id'], $data['source_url'], $data['body']))) {
                    throw new EtymologException('Publishing cultural content requires a licensed web citation and verbatim source quotation matching the body');
                }
            }
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
        if (in_array($type, ['id', 'year', 'count', 'batch', 'interval', 'bool', 'month', 'day'], true)) {
            if ($type === 'bool' && is_bool($value)) {
                return (int)$value;
            }
            if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                return $fail();
            }
            $number = (int)$value;
            [$min, $max] = match ($type) {
                'month' => [1, 12], 'day' => [1, 31], 'id' => [1, 2147483647], 'year' => [-10000, 3000], 'count' => [0, 2147483647],
                'batch' => [1, 500], 'interval' => [300, 2592000], 'bool' => [0, 1],
            };
            return $number >= $min && $number <= $max ? $number : $fail();
        }
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")) {
            return $fail();
        }
        $value = trim($value);
        if ($type === 'date') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value ? $value : $fail();
        }
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
            $item = $force
                ? ($repo->findIncludingDeleted($id) ?? throw new EtymologException('Record not found', 404))
                : $this->get($resource, $id);
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
            if ($entry && $entry['published'] && ($entry['certainty'] === 'documented' || in_array($entry['type'], ResourceRegistry::CULTURAL_TYPES, true))) {
                throw new EtymologException('Unpublish the documented entry before removing its citation', 409);
            }
        }
    }
}
