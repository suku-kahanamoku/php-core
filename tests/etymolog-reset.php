<?php
/** Included only by schema.php on its disposable MySQL server. No live sync. */
declare(strict_types=1);
if (!isset($pdo, $dsn) || !str_starts_with($dsn, 'mysql:unix_socket=/tmp/php-core-schema-test.')) {
    throw new RuntimeException('Disposable schema test DB required');
}
$pdo->exec('USE schema_test');
function resetFixture(string $tenant, string $table, array $data): int {
    global $pdo;
    $data = ['franchise_code' => $tenant] + $data;
    $columns = implode(',', array_map(fn($key) => '`'.$key.'`', array_keys($data)));
    $pdo->prepare('INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(',', array_fill(0, count($data), '?')).')')->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}
function resetExecute(PDO $connection): array {
    $query = $connection->query(file_get_contents(dirname(__DIR__).'/migrations/etymolog_reset_content.sql'));
    $result = [];
    do {
        if ($query->columnCount()) { $result = $query->fetchAll(PDO::FETCH_ASSOC); }
    } while ($query->nextRowset());
    return $result[0];
}
$resetTables = ['external_record','import_record','story_import','citation','calendar_day','entry_name','variant','occurrence','entry','name','calendar','sync_run','sync_batch'];
foreach (['etymolog', 'reset-other'] as $tenant) {
    $source = resetFixture($tenant, 'etymolog_source', ['title'=>'Preserve source', 'license'=>'CC0', 'import_key'=>'reset-test-source']);
    $name = resetFixture($tenant, 'etymolog_name', ['name'=>'ANNA', 'kind'=>'given', 'published'=>1]);
    $otherName = resetFixture($tenant, 'etymolog_name', ['name'=>'Anna', 'kind'=>'surname', 'deleted'=>1]);
    $entry = resetFixture($tenant, 'etymolog_entry', ['name_id'=>$name, 'type'=>'etymology', 'title'=>'Manual entry', 'body'=>'Fixture', 'published'=>1]);
    $story = resetFixture($tenant, 'etymolog_entry', ['type'=>'legend', 'title'=>'Shared story', 'body'=>'Fixture']);
    resetFixture($tenant, 'etymolog_entry_name', ['entry_id'=>$story, 'name_id'=>$name]);
    resetFixture($tenant, 'etymolog_entry_name', ['entry_id'=>$story, 'name_id'=>$otherName]);
    resetFixture($tenant, 'etymolog_citation', ['entry_id'=>$entry, 'source_id'=>$source]);
    resetFixture($tenant, 'etymolog_variant', ['name_id'=>$name, 'target_name_id'=>$otherName, 'source_id'=>$source, 'variant'=>'Anna']);
    $occurrence = resetFixture($tenant, 'etymolog_occurrence', ['name_id'=>$name, 'source_id'=>$source, 'country_code'=>'CZ', 'observed_year'=>2025]);
    $calendar = resetFixture($tenant, 'etymolog_calendar', ['title'=>'Calendar', 'country_code'=>'CZ', 'tradition'=>'Fixture']);
    $day = resetFixture($tenant, 'etymolog_calendar_day', ['calendar_id'=>$calendar, 'source_id'=>$source, 'name_id'=>$name, 'entry_id'=>$story, 'title'=>'Day', 'source_url'=>'https://example.org']);
    $provenance = ['provider'=>'wiktionary', 'external_id'=>'fixture', 'revision'=>'1', 'source_url'=>'https://example.org', 'license'=>'CC0', 'license_url'=>'https://example.org/license', 'attribution'=>'Fixture', 'payload'=>'{}', 'content_hash'=>str_repeat('a',64), 'fetched_at'=>'2026-01-01 00:00:00'];
    resetFixture($tenant, 'etymolog_external_record', $provenance + ['source_id'=>$source, 'name_id'=>$name, 'entry_id'=>$entry, 'occurrence_id'=>$occurrence, 'calendar_day_id'=>$day]);
    resetFixture($tenant, 'etymolog_story_import', $provenance + ['source_id'=>$source, 'entry_id'=>$story]);
    resetFixture($tenant, 'etymolog_import_record', $provenance + ['name_id'=>$name]);
    foreach ([[1,0],[0,0],[1,1]] as [$enabled,$deleted]) {
        $job = resetFixture($tenant, 'etymolog_sync_job', ['title'=>'Reset fixture', 'enabled'=>$enabled, 'deleted'=>$deleted, 'cursor'=>'saved', 'next_run_at'=>'2030-01-01 00:00:00', 'last_status'=>'failed', 'last_error'=>'fixture']);
        resetFixture($tenant, 'etymolog_sync_run', ['job_id'=>$job, 'status'=>'failed']);
    }
    resetFixture($tenant, 'etymolog_sync_batch', ['request_id'=>str_repeat('a',32), 'status'=>'completed', 'created_at'=>'2026-01-01 00:00:00', 'heartbeat_at'=>'2026-01-01 00:00:00']);
    resetFixture($tenant, 'etymolog_sync_schedule', ['scheduled_date'=>'2026-01-01', 'request_id'=>str_repeat('a',32)]);
}
$role = (int)$pdo->query("SELECT id FROM role WHERE franchise_code='etymolog' AND name='admin'")->fetchColumn();
resetFixture('etymolog', 'user', ['first_name'=>'Admin', 'last_name'=>'Fixture', 'email'=>'reset-test@example.org', 'password'=>'unchanged-test-hash', 'role_id'=>$role]);
$resetBefore = snapshot();
$lockConnection = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['ety-worker:', 'ety-request:', 'etymolog:'] as $prefix) {
    $key = $prefix.substr(hash('sha256','etymolog'),0,48);
    $lockConnection->prepare('SELECT GET_LOCK(?,0)')->execute([$key]);
    verify(resetExecute($pdo)['result'] === 'SKIPPED_BUSY', 'reset refuses occupied '.$prefix.' lock');
    verify(snapshot() === $resetBefore, 'occupied lock preserves entire database');
    $lockConnection->prepare('SELECT RELEASE_LOCK(?)')->execute([$key]);
}
foreach (['queued', 'running'] as $status) {
    $pdo->exec("UPDATE etymolog_sync_batch SET status='$status' WHERE franchise_code='etymolog'");
    $busyBefore = snapshot();
    verify(resetExecute($pdo)['result'] === 'SKIPPED_BUSY', 'reset refuses '.$status.' batch between HTTP steps');
    verify(snapshot() === $busyBefore, 'active batch preserves entire database');
}
$pdo->exec("UPDATE etymolog_sync_batch SET status='completed' WHERE franchise_code='etymolog'");
// A new unexpected dependent table forces a mid-reset error: no partial deletion may survive rollback.
$pdo->exec('CREATE TABLE reset_fk_guard (name_id INT UNSIGNED NOT NULL, FOREIGN KEY(name_id) REFERENCES etymolog_name(id)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO reset_fk_guard SELECT id FROM etymolog_name WHERE franchise_code='etymolog' LIMIT 1");
$failureBefore = snapshot();
$failed = false;
try { resetExecute($lockConnection); }
catch (PDOException $error) {
    $failed = $error->getCode() === '23000';
    $lockConnection->exec('ROLLBACK');
    $lockConnection->query('SELECT RELEASE_ALL_LOCKS()')->fetchColumn();
}
verify($failed && snapshot() === $failureBefore, 'SQL error rolls back all earlier deletions using documented recovery');
$pdo->exec('DROP TABLE reset_fk_guard');
$beforeOther = [];
foreach ($resetTables as $table) { $beforeOther[$table] = rows("SELECT * FROM etymolog_$table WHERE franchise_code='reset-other' ORDER BY 1"); }
$jobsBefore = rows("SELECT * FROM etymolog_sync_job ORDER BY id");
$result = resetExecute($pdo);
verify($result['result'] === 'RESET' && (int)$result['deleted_rows'] > 0, 'full reset succeeds');
foreach ($resetTables as $table) {
    verify(rows("SELECT * FROM etymolog_$table WHERE franchise_code='etymolog'") === [], 'empties '.$table.' including drafts and tombstones');
    verify(rows("SELECT * FROM etymolog_$table WHERE franchise_code='reset-other' ORDER BY 1") === $beforeOther[$table], 'preserves other tenant '.$table);
}
foreach ($jobsBefore as &$job) {
    if ($job['franchise_code'] === 'etymolog') {
        foreach (['cursor','next_run_at','last_status','last_error'] as $field) { $job[$field] = null; }
    }
}
unset($job);
verify(rows('SELECT * FROM etymolog_sync_job ORDER BY id') === $jobsBefore, 'only progress reset on all retained jobs, including disabled and deleted');
$resetAfter = snapshot();
foreach ($resetBefore as $table=>$hash) {
    if (!in_array($table, array_map(fn($suffix) => 'etymolog_'.$suffix, $resetTables), true) && $table !== 'etymolog_sync_job') {
        verify($resetAfter[$table] === $hash, 'preserves '.$table.' exactly');
    }
}
verify((int)$pdo->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn() === 1, 'foreign keys remain enabled');
verify(resetExecute($pdo)['result'] === 'RESET' && snapshot() === $resetAfter, 'repeat reset is harmless');
foreach (['ety-worker:', 'ety-request:', 'etymolog:'] as $prefix) {
    verify((int)$pdo->query("SELECT IS_FREE_LOCK('".$prefix.substr(hash('sha256','etymolog'),0,48)."')")->fetchColumn() === 1, 'releases '.$prefix.' lock');
}
