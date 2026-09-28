<?php

declare(strict_types=1);

namespace App\Modules\Etymolog;

/** Fixed resource/column allowlist shared by CRUD validation and repositories. */
final class ResourceRegistry
{
    public const CULTURAL_TYPES = ['legend', 'mythology', 'fiction', 'tradition', 'proverb'];
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
                ], 'system' => ['import_key'],
            ],
            'entries' => [
                'table' => 'etymolog_entry', 'required' => ['type', 'title', 'body'],
                'fields' => [
                    'name_id' => ['id', null],
                    'type' => ['enum:etymology,history,clerical_error,legend,mythology,fiction,tradition,proverb', 'etymology'],
                    'title' => ['text:255', ''], 'body' => ['text:60000', ''],
                    'source_url' => ['url', null],
                    'certainty' => ['enum:documented,hypothesis,unverified,fiction', 'unverified'],
                    'language' => ['language', 'cs'], 'region' => ['text:255', null],
                    'year_from' => ['year', null], 'year_to' => ['year', null],
                    'published' => ['bool', 0],
                ], 'references' => ['name_id' => 'names'],
            ],
            'entry-names' => [
                'table' => 'etymolog_entry_name', 'required' => ['entry_id', 'name_id'],
                'fields' => [
                    'entry_id' => ['id', 0], 'name_id' => ['id', 0],
                    'relation' => ['enum:mentioned,story_subject,name_origin', 'mentioned'],
                    'reviewed' => ['bool', 0], 'notes' => ['text:20000', null],
                ], 'references' => ['entry_id' => 'entries', 'name_id' => 'names'],
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
                    'observed_year' => ['year', 0], 'observed_on' => ['date', null],
                    'sex' => ['enum:male,female,all', null], 'measure' => ['enum:living_persons,births,historical_attestation', null], 'count' => ['count', null],
                    'original_spelling' => ['text:255', null], 'locator' => ['text:1000', null],
                    'notes' => ['text:20000', null],
                ], 'references' => ['name_id' => 'names', 'source_id' => 'sources'],
            ],
            'citations' => [
                'table' => 'etymolog_citation', 'required' => ['entry_id', 'source_id'],
                'fields' => [
                    'entry_id' => ['id', 0], 'source_id' => ['id', 0],
                    'url' => ['url', null], 'locator' => ['text:1000', null],
                    'quotation' => ['text:60000', null], 'notes' => ['text:20000', null],
                ], 'references' => ['entry_id' => 'entries', 'source_id' => 'sources'],
            ],
            'calendars' => [
                'table' => 'etymolog_calendar', 'required' => ['title', 'country_code', 'system', 'tradition'],
                'fields' => [
                    'title' => ['text:255', ''], 'country_code' => ['country', 'CZ'],
                    'system' => ['enum:gregorian,julian', 'gregorian'],
                    'tradition' => ['text:255', ''], 'region' => ['text:255', null],
                    'year_from' => ['year', null], 'year_to' => ['year', null],
                    'notes' => ['text:20000', null],
                ], 'system' => ['import_key'],
            ],
            'calendar-days' => [
                'table' => 'etymolog_calendar_day', 'required' => ['calendar_id', 'source_id', 'title', 'source_url'],
                'fields' => [
                    'calendar_id' => ['id', 0], 'source_id' => ['id', 0],
                    'name_id' => ['id', null], 'entry_id' => ['id', null],
                    'title' => ['text:255', ''], 'kind' => ['enum:name_day,feast,observance,folklore', 'name_day'],
                    'date_kind' => ['enum:fixed,movable', 'fixed'],
                    'month' => ['month', null], 'day' => ['day', null], 'date_rule' => ['text:1000', null],
                    'source_url' => ['url', ''], 'locator' => ['text:1000', null],
                    'notes' => ['text:20000', null], 'published' => ['bool', 0],
                ], 'references' => ['calendar_id' => 'calendars', 'source_id' => 'sources', 'name_id' => 'names', 'entry_id' => 'entries'],
                'system' => ['import_key'],
            ],
            'sync-jobs' => [
                'table' => 'etymolog_sync_job', 'required' => ['title'], 'admin' => true,
                'fields' => [
                    'title' => ['text:255', ''], 'provider' => ['enum:wikidata,wikisource,wiktionary,poland-pesel,csu-baby-names,erben-folklore,czech-namedays', 'wikidata'],
                    'language' => ['enum:cs,sk,pl,uk,de,en', 'cs'],
                    'kind' => ['enum:given,surname,stories,surname_male,surname_female,births_2025,folklore,calendar', 'surname'],
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
