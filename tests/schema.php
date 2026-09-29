<?php
/** Integration checks restricted to scripts/test-schema.sh's disposable MySQL. */
declare(strict_types=1);
$dsn = getenv('SCHEMA_TEST_DSN');
if (!$dsn || !preg_match('~^mysql:unix_socket=/tmp/php-core-schema-test\.[^/]+/mysql\.sock;dbname=schema_test;charset=utf8mb4$~D', $dsn)) {
    throw new RuntimeException('Disposable schema test DB required');
}
$pdo = new PDO(explode(';dbname=', $dsn)[0], 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE schema_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE schema_test');
$checks = 0;
function verify(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    ++$checks;
    echo 'PASS '.$label.PHP_EOL;
}
function apply(string $name): void {
    global $pdo;
    $pdo->exec(file_get_contents(dirname(__DIR__).'/migrations/'.$name.'.sql'));
}
function rows(string $sql): array {
    global $pdo;
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
function snapshot(): array {
    global $pdo;
    $result = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $data = rows('SELECT * FROM `'.$table.'`');
        $encoded = array_map('json_encode', $data);
        sort($encoded);
        $result[$table] = hash('sha256', implode("\n", $encoded));
    }
    return $result;
}
$schemas = ['schema', 'etymolog_schema', 'tram_schema', 'sry_schema', 'zoo_schema', 'fann_schema', 'zajeci_schema'];
$seeds = ['schema_seed', 'zajeci_seed', 'zoo_seed', 'fann_seed', 'etymolog_seed', 'tram_seed', 'sry_seed'];
foreach ($schemas as $file) { apply($file); }
verify(count(rows('SHOW TABLES')) === 75, 'all 75 current tables created');
foreach (rows('SHOW TABLES') as $row) {
    $table = reset($row);
    verify((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() === 0, $table.' schema contains no seed data');
}
// Allocate different IDs than the historical scripts: every seed FK must resolve naturally.
foreach (['role', 'enumeration', 'user', 'category', 'customer_profile', 'product', 'etymolog_sync_job'] as $table) {
    $pdo->exec('ALTER TABLE `'.$table.'` AUTO_INCREMENT=5000');
}
foreach ($seeds as $file) { apply($file); }
foreach (['role'=>12, 'user'=>13, 'product'=>88, 'category'=>37, 'enumeration'=>60,
          'customer_profile'=>20, 'product_category'=>107, 'product_alternative'=>27,
          'product_customer_profile_probability'=>338, 'user_customer_profile'=>10,
          'etymolog_sync_job'=>10] as $table=>$count) {
    verify((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() === $count, $table.' final seed coverage');
}
verify((int)$pdo->query("SELECT COUNT(*) FROM product_category pc JOIN product p ON p.id=pc.product_id JOIN category c ON c.id=pc.category_id WHERE p.franchise_code<>c.franchise_code")->fetchColumn() === 0, 'seed links preserve tenants with nonhistorical IDs');
$before = snapshot();
foreach ($schemas as $file) { apply($file); }
foreach ($seeds as $file) { apply($file); }
verify(snapshot() === $before, 'second application preserves every row and timestamp without duplicates');
$pdo->exec("UPDATE `user` SET password='changed-test-hash' WHERE franchise_code='fann'");
$pdo->exec("UPDATE product SET name='Edited', price=123.45, deleted=1 WHERE franchise_code='fann'");
$pdo->exec("UPDATE etymolog_sync_job SET `cursor`='saved-progress', enabled=0, deleted=1, next_run_at='2030-01-01 03:00:00' WHERE provider='wikidata'");
$before = snapshot();
foreach ($seeds as $file) { apply($file); }
verify(snapshot() === $before, 'seeds preserve passwords, edited products, tombstones and sync progress');
// Simulate a partially upgraded existing DB, not the application database.
$pdo->exec("INSERT INTO etymolog_name(franchise_code,name,kind) VALUES ('upgrade-fixture','Anna','given')");
$upgradeNameId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO etymolog_import_record(franchise_code,name_id,provider,external_id,source_url,license,license_url,attribution,revision,payload,content_hash,fetched_at) VALUES ('upgrade-fixture',{$upgradeNameId},'wikidata','Q1','https://example.org','CC0','https://example.org/license','Source','1','{}',REPEAT('a',64),UTC_TIMESTAMP())");
$importBefore = rows('SELECT * FROM etymolog_import_record');
$pdo->exec('ALTER TABLE etymolog_import_record DROP INDEX uq_etymolog_import, ADD UNIQUE KEY uq_etymolog_import (franchise_code,name_id,provider)');
$pdo->exec('ALTER TABLE product DROP COLUMN stock_quantity');
$pdo->exec('ALTER TABLE etymolog_name DROP INDEX idx_etymolog_name_identity, DROP COLUMN normalized_name');
$pdo->exec('ALTER TABLE etymolog_sync_batch DROP COLUMN retry_at, DROP COLUMN retry_count, DROP COLUMN pending_jobs');
$pdo->exec('ALTER TABLE etymolog_sync_job MODIFY COLUMN `cursor` VARCHAR(255) NULL');
$pdo->exec('ALTER TABLE etymolog_entry MODIFY COLUMN name_id INT UNSIGNED NOT NULL');
$pdo->exec('ALTER TABLE etymolog_external_record MODIFY COLUMN name_id INT UNSIGNED NOT NULL');
$pdo->exec('ALTER TABLE category DROP INDEX idx_cat_deleted');
$pdo->exec('ALTER TABLE customer_profile_question DROP FOREIGN KEY fk_customer_profile_question_profile');
$pdo->exec("CREATE TABLE partial_sentinel (id INT PRIMARY KEY, body TEXT)");
$pdo->exec("INSERT INTO partial_sentinel VALUES (1, 'Keep unrelated data')");
foreach ($schemas as $file) { apply($file); }
verify($pdo->query("SELECT normalized_name FROM etymolog_name WHERE id={$upgradeNameId}")->fetchColumn() === 'anna', 'schema upgrade computes indexed identity for existing names');
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_name' AND INDEX_NAME='idx_etymolog_name_identity'")->fetchColumn() === 5, 'schema upgrade restores name identity index');
verify(rows('SELECT * FROM etymolog_import_record') === $importBefore, 'Wikidata index upgrade preserves existing snapshots and name links');
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_import_record' AND INDEX_NAME='uq_etymolog_import'")->fetchColumn() === 4, 'existing Wikidata identity index now retains multiple source IDs per name');
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_batch' AND COLUMN_NAME IN ('retry_at','retry_count','pending_jobs')")->fetchColumn() === 3, 'missing worker columns restored');
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('etymolog_entry','etymolog_external_record') AND COLUMN_NAME='name_id' AND IS_NULLABLE='YES'")->fetchColumn() === 2, 'historical name links safely widened to nullable');
verify((int)$pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='etymolog_sync_job' AND COLUMN_NAME='cursor'")->fetchColumn() === 2048, 'historical sync cursor widened');
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category' AND INDEX_NAME='idx_cat_deleted'")->fetchColumn() === 1, 'missing index restored');
verify((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='customer_profile_question' AND CONSTRAINT_NAME='fk_customer_profile_question_profile'")->fetchColumn() === 1, 'missing FK restored');
verify($pdo->query('SELECT body FROM partial_sentinel')->fetchColumn() === 'Keep unrelated data', 'unrelated table preserved');
verify((int)$pdo->query("SELECT COUNT(*) FROM product WHERE franchise_code='fann' AND name='Edited' AND deleted=1")->fetchColumn() === 60, 'schema upgrade preserves edited products');
// A missing primary-key column must be added together with its required index.
$pdo->exec('CREATE DATABASE schema_partial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE schema_partial');
$pdo->exec('CREATE TABLE role (franchise_code VARCHAR(64) NOT NULL, name VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$pdo->exec("INSERT INTO role VALUES ('sentinel','custom')");
apply('schema');
verify($pdo->query("SELECT id FROM role WHERE franchise_code='sentinel'")->fetchColumn() !== false, 'partial table gets id, columns and indexes without losing row');
verify((int)$pdo->query('SELECT COUNT(*) FROM role')->fetchColumn() === 1, 'partial schema never inserts bootstrap data');
require __DIR__.'/etymolog-reset.php';
require __DIR__.'/etymolog-reset-cz.php';
echo "Checks: $checks passed\n";
