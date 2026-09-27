<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

/** Fixed resource/column allowlist shared by CRUD validation and repositories. */
final class ResourceRegistry
{
    public const LANGUAGES = ['cs', 'sk', 'pl', 'uk', 'de', 'en'];

    public static function all(): array
    {
        // type, default; null defaults allow explicit null. Required fields are separate.
        return [
            'names' => [
                'table' => 'etymolog_name', 'required' => ['name', 'kind'],
                'fields' => [
                    'name' => ['text:255', ''], 'kind' => ['enum:given,surname', 'surname'],
                    'language' => ['language', null], 'country_code' => ['country', null],
                    'summary' => ['text:20000', null], 'published' => ['bool', 0],
                ], 'system' => ['import_key'],
            ],
            'sources' => [
                'table' => 'etymolog_source', 'required' => ['title'],
                'fields' => [
                    'title' => ['text:255', ''], 'author' => ['text:255', null],
                    'url' => ['url', null], 'license' => ['text:100', null],
                    'license_url' => ['url', null], 'attribution' => ['text:4000', null],
                    'notes' => ['text:20000', null],
                ],
            ],
            'entries' => [
                'table' => 'etymolog_entry', 'required' => ['name_id', 'type', 'title', 'body'],
                'fields' => [
                    'name_id' => ['id', 0],
                    'type' => ['enum:etymology,history,clerical_error,legend,fiction', 'etymology'],
                    'title' => ['text:255', ''], 'body' => ['text:60000', ''],
                    'certainty' => ['enum:documented,hypothesis,unverified,fiction', 'unverified'],
                    'language' => ['language', 'cs'], 'region' => ['text:255', null],
                    'year_from' => ['year', null], 'year_to' => ['year', null],
                    'published' => ['bool', 0],
                ], 'references' => ['name_id' => 'names'],
            ],
            'variants' => [
                'table' => 'etymolog_variant', 'required' => ['name_id', 'variant'],
                'fields' => [
                    'name_id' => ['id', 0], 'target_name_id' => ['id', null],
                    'variant' => ['text:255', ''],
                    'relation' => ['enum:spelling,historical,transliteration,feminine,related', 'spelling'],
                    'language' => ['language', null], 'region' => ['text:255', null],
                    'year_from' => ['year', null], 'year_to' => ['year', null],
                    'source_id' => ['id', null], 'notes' => ['text:20000', null],
                ], 'references' => ['name_id' => 'names', 'target_name_id' => 'names', 'source_id' => 'sources'],
            ],
            'occurrences' => [
                'table' => 'etymolog_occurrence', 'required' => ['name_id', 'source_id', 'country_code', 'observed_year'],
                'fields' => [
                    'name_id' => ['id', 0], 'source_id' => ['id', 0],
                    'country_code' => ['country', 'CZ'], 'region' => ['text:255', null],
                    'observed_year' => ['year', 0], 'count' => ['count', null],
                    'original_spelling' => ['text:255', null], 'locator' => ['text:1000', null],
                    'notes' => ['text:20000', null],
                ], 'references' => ['name_id' => 'names', 'source_id' => 'sources'],
            ],
            'citations' => [
                'table' => 'etymolog_citation', 'required' => ['entry_id', 'source_id'],
                'fields' => [
                    'entry_id' => ['id', 0], 'source_id' => ['id', 0],
                    'url' => ['url', null], 'locator' => ['text:1000', null],
                    'quotation' => ['text:10000', null], 'notes' => ['text:20000', null],
                ], 'references' => ['entry_id' => 'entries', 'source_id' => 'sources'],
            ],
            'sync-jobs' => [
                'table' => 'etymolog_sync_job', 'required' => ['title'], 'admin' => true,
                'fields' => [
                    'title' => ['text:255', ''], 'provider' => ['enum:wikidata', 'wikidata'],
                    'language' => ['enum:cs,sk,pl,uk,de,en', 'cs'],
                    'kind' => ['enum:given,surname', 'surname'],
                    'batch_size' => ['batch', 20], 'interval_seconds' => ['interval', 3600],
                    'enabled' => ['bool', 1],
                ], 'system' => ['cursor', 'next_run_at', 'last_status', 'last_error'],
            ],
        ];
    }

    public static function get(string $resource): array
    {
        return self::all()[$resource] ?? throw new EtymologException('Resource not found', 404);
    }
}
