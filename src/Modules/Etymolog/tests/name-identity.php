<?php
// All providers use the same identity inside a tenant lock + transaction. Fixture data only.
(function () use ($db): void {
    $tenant = 'identity-fixture';
    $owner = new App\Modules\Etymolog\EtymologRepository($db, $tenant, 'names');
    $names = new App\Modules\Etymolog\EtymologNameRepository($db, $tenant);
    $external = new App\Modules\Etymolog\EtymologExternalRepository($db, $tenant);
    $sync = new App\Modules\Etymolog\EtymologSyncRepository($db, $tenant);
    $stories = new App\Modules\Etymolog\EtymologStoryRepository($db, $tenant);
    $calendar = new App\Modules\Etymolog\EtymologCalendarRepository($db, $tenant);
    $write = fn (Closure $fn) => $owner->exclusive(fn () => $owner->transaction($fn));
    $base = ['name'=>' ANNA ', 'kind'=>'given', 'language'=>null, 'country_code'=>'CZ',
        'external_id'=>'first', 'revision'=>'1', 'source_url'=>'https://example.org/name',
        'license'=>'CC0-1.0', 'license_url'=>'https://example.org/license', 'attribution'=>'Test source',
        'payload'=>['original'=>' ANNA '], 'source_key'=>'test-source', 'source_title'=>'Fixture source', 'notes'=>null,
        'entry'=>['type'=>'etymology','title'=>'Fixture etymology','body'=>'Quoted fixture text','language'=>'cs'],
        'occurrence'=>['country_code'=>'CZ','observed_year'=>2025,'count'=>5,'original_spelling'=>' ANNA ']];
    // First a statistics-only uppercase record, then multiple dictionaries/countries/languages.
    $write(fn () => $external->import('csu-baby-names', $base));
    $anna = $db->fetchOne('SELECT * FROM etymolog_name WHERE franchise_code=?', [$tenant]);
    $id = (int)$anna['id'];
    check($anna['name']==='Anna' && $anna['published']===0, 'first statistics import normalizes casing without publishing');
    foreach (['wiktionary','wiktionary-cs','wiktionary-fr','wikipedia-names'] as $provider) {
        $item = array_replace($base, ['name'=>'aNnA', 'language'=>'pl', 'country_code'=>'PL']);
        $write(fn () => $external->import($provider, $item));
    }
    check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_name WHERE franchise_code=?', [$tenant])['n']===1, 'all dictionary and statistic providers reuse one name regardless of casing language and country');
    check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_entry WHERE franchise_code=? AND name_id=?', [$tenant,$id])['n']===4, 'distinct sourced interpretations attach to the existing name');
    $surname = array_replace($base, ['name'=>'Anna','kind'=>'surname']);
    $write(fn () => $external->import('poland-pesel', $surname));
    $surnameId = (int)$db->fetchOne("SELECT id FROM etymolog_name WHERE franchise_code=? AND kind='surname'", [$tenant])['id'];
    check($surnameId!==$id, 'same spelling as a surname has a distinct stored identity');
    foreach (['Q901','Q902'] as $qid) {
        $item = array_replace($base, ['name'=>'ANNA','external_id'=>$qid]);
        $write(fn () => $sync->import(['kind'=>'given','language'=>'cs'], $item));
        $write(fn () => $sync->import(['kind'=>'given','language'=>'de'], $item));
    }
    $write(fn () => $sync->import(['kind'=>'surname','language'=>'cs'], array_replace($base, ['external_id'=>'Q901'])));
    check(count($sync->imports($id))===2 && count($sync->imports($surnameId))===1, 'Wikidata preserves multiple QIDs and separate given/surname records without overwriting provenance');
    check((int)$db->fetchOne('SELECT COUNT(*) n FROM etymolog_name WHERE franchise_code=?', [$tenant])['n']===2, 'Wikidata repeat imports do not create more names');
    $story = array_replace($base, ['external_id'=>'story', 'title'=>'Fixture story', 'body'=>'Fixture source text',
        'bibliography'=>'Fixture edition', 'region'=>null, 'names'=>['ANNA','anna',['name'=>'Anna','kind'=>'surname']]]);
    $storyResult = $write(fn () => $stories->import($story));
    $links = $db->fetchAll('SELECT name_id,reviewed FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=?', [$tenant,$storyResult['entry_id']]);
    check(count($links)===2 && array_sum(array_column($links,'reviewed'))===0, 'story suggestions deduplicate casing while keeping both kinds separate and unreviewed');
    $day = array_replace($base, ['external_id'=>'day','name'=>'anna','title'=>'Anna day','kind'=>'nameday','month'=>7,'day'=>26]);
    $write(fn () => $calendar->import($day));
    $write(fn () => $calendar->import($day));
    check((int)$db->fetchOne('SELECT name_id FROM etymolog_calendar_day WHERE franchise_code=?', [$tenant])['name_id']===$id, 'calendar reuses the given name, never the matching surname');
    $write(fn () => $external->import('wikipedia-names', array_replace($base, ['external_id'=>'second','name'=>'Anna'])));
    $db->query("UPDATE etymolog_entry SET body='Editorial correction',published=1 WHERE franchise_code=? AND name_id=?", [$tenant,$id]);
    $write(fn () => $external->import('wikipedia-names', array_replace($base, ['revision'=>'2','name'=>'ANNA'])));
    check((int)$db->fetchOne("SELECT COUNT(*) n FROM etymolog_entry WHERE franchise_code=? AND name_id=? AND body='Editorial correction' AND published=1", [$tenant,$id])['n']===5, 'new source adds an interpretation; repeat source preserves editorial content');
    check($db->fetchOne('SELECT original_spelling FROM etymolog_occurrence WHERE franchise_code=? AND name_id=?', [$tenant,$id])['original_spelling']===' ANNA ', 'source spelling remains untouched in provenance');
    $accented = $write(fn () => $names->resolve('ŽANETA', 'given', 'cs', null, 'accented'));
    $unaccented = $write(fn () => $names->resolve('ZANETA', 'given', 'cs', null, 'unaccented'));
    check($accented!==$unaccented && $db->fetchOne('SELECT name FROM etymolog_name WHERE id=?', [$accented])['name']==='Žaneta', 'identity keeps diacritics and Unicode capitalization');
    $otherOwner = new App\Modules\Etymolog\EtymologRepository($db, 'identity-other', 'names');
    $otherId = $otherOwner->exclusive(fn () => $otherOwner->transaction(fn () => (new App\Modules\Etymolog\EtymologNameRepository($db, 'identity-other'))->resolve('anna','given',null,null,'other')));
    check($otherId!==$id, 'name identity is strictly tenant scoped');
    $db->query("UPDATE etymolog_name SET deleted=1 WHERE id=?", [$accented]);
    check($write(fn () => $names->resolve('žaneta','given',null,'DE','new-source'))===null, 'case-insensitive matching preserves tombstones across providers');
    // Existing uppercase imports refresh to a previously created mixed-case canonical row.
    $duplicate = $db->insert('etymolog_name', ['franchise_code'=>$tenant,'name'=>'ANNA','kind'=>'given']);
    $db->query('UPDATE etymolog_entry_name SET name_id=?,reviewed=1 WHERE franchise_code=? AND entry_id=? AND name_id=?', [$duplicate,$tenant,$storyResult['entry_id'],$id]);
    $write(fn () => $stories->import($story));
    check((int)$db->fetchOne('SELECT reviewed FROM etymolog_entry_name WHERE franchise_code=? AND entry_id=? AND name_id=?', [$tenant,$storyResult['entry_id'],$id])['reviewed']===1, 'story refresh reuses canonical name while preserving editorial link review');
    $record = $db->fetchOne("SELECT * FROM etymolog_external_record WHERE franchise_code=? AND provider='wikipedia-names' AND external_id='first'", [$tenant]);
    $db->query('UPDATE etymolog_external_record SET name_id=? WHERE id=?', [$duplicate,$record['id']]);
    $db->query('UPDATE etymolog_entry SET name_id=? WHERE id=?', [$duplicate,$record['entry_id']]);
    $write(fn () => $external->import('wikipedia-names', $base));
    check((int)$db->fetchOne('SELECT name_id FROM etymolog_entry WHERE id=?', [$record['entry_id']])['name_id']===$id, 'refresh reattaches legacy duplicate import to the canonical name without creating another');
})();
