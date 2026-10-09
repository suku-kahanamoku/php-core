<?php
/** Destructive migration checks only against this script's disposable MySQL socket. */
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Modules\Database\Maintenance\TramCleanupRepository;
$dsn = getenv('TRAM_CLEANUP_TEST_DSN');
if (!$dsn || !preg_match('~^mysql:unix_socket=/tmp/php-core-tram-cleanup-test\.[^/]+/mysql\.sock;dbname=tram_cleanup_test;charset=utf8mb4$~D', $dsn)) {
    throw new RuntimeException('Disposable cleanup database required.');
}
$db = new PDO(explode(';dbname=', $dsn)[0], 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE tram_cleanup_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE tram_cleanup_test');
$db->exec(file_get_contents(dirname(__DIR__) . '/migrations/schema.sql'));
$checks = 0;
function check(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); ++$checks; echo 'PASS ' . $label . PHP_EOL; }
function rejects(callable $call, string $label, string $reason): void { try { $call(); } catch (RuntimeException $error) { check(str_contains($error->getMessage(), $reason), $label); return; } throw new RuntimeException('Expected rejection: ' . $label); }
function snapshot(PDO $db): array {
    $result = [];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows = $db->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_map('json_encode', $rows); sort($rows);
        $result[$table] = hash('sha256', implode("\n", $rows));
    }
    return $result;
}
foreach (['tram', 'other'] as $tenant) {
    $db->exec("INSERT INTO role(franchise_code,name,label) VALUES ('$tenant','admin','Admin')");
    $role = $db->lastInsertId();
    $db->exec("INSERT INTO user(franchise_code,first_name,last_name,email,password,role_id,status) VALUES ('$tenant','Test','User','$tenant@example.test','unchanged-hash',$role,'active')");
    $user = $db->lastInsertId();
    $db->exec("INSERT INTO user_token(user_id,token,expires_at) VALUES ($user,'$tenant-synthetic-session','2030-01-01')");
    $db->exec("INSERT INTO oauth_identity(franchise_code,user_id,provider,provider_subject,email) VALUES ('$tenant',$user,'test','$tenant-subject','$tenant@example.test')");
    $db->exec("INSERT INTO password_reset_token(franchise_code,user_id,token_hash,expires_at) VALUES ('$tenant',$user,SHA2('$tenant',256),'2030-01-01')");
    $db->exec("INSERT INTO enumeration(franchise_code,type,syscode,label) VALUES ('$tenant','transport_mode','bus','Bus')");
    foreach (array_merge(TramCleanupRepository::AUTH_ACTIONS, ['transport_read', 'transport_tracking']) as $action) {
        $db->exec("INSERT INTO api_rate_limit(franchise_code,action,subject_hash,window_started_at,attempts,expires_at) VALUES ('$tenant','$action',REPEAT('b',64),'2026-10-01 00:00:00',2,'2030-01-01 00:00:00')");
    }
}
foreach (TramCleanupRepository::TABLES as $table) {
    $db->exec("CREATE TABLE `$table` (id INT PRIMARY KEY, franchise_code VARCHAR(64), payload TEXT)");
    $db->exec("INSERT INTO `$table` VALUES (1,'tram','retired-data')");
}
$db->exec('ALTER TABLE transport_stop_time ADD CONSTRAINT fk_test_stop FOREIGN KEY(id) REFERENCES transport_stop(id)');
$cleanup = new TramCleanupRepository($db);
$initial = snapshot($db);
$plan = $cleanup->plan();
check(count($plan['drop']) === 17 && $plan['delete'] === ['api_rate_limit'=>2,'enumeration'=>1], 'exact scope, all transport tables and only non-auth shared rows');
check(snapshot($db) === $initial, 'plan changes no rows');
$db->exec("INSERT INTO transport_stop VALUES (2,'other','must keep')");
rejects(fn() => $cleanup->apply('/tmp/unused-tram-cleanup-backup'), 'another transport tenant aborts before mutation', 'Transport table contains another tenant');
$db->exec('DELETE FROM transport_stop WHERE id=2');
$db->exec("INSERT INTO product(franchise_code,sku,name) VALUES ('tram','legacy','Unexpected')");
rejects(fn() => $cleanup->plan(), 'unexpected shared TRAM data requires review', 'Unexpected TRAM rows');
$db->exec("DELETE FROM product WHERE franchise_code='tram'");
$db->exec('CREATE TABLE tram_unknown(id INT)');
rejects(fn() => $cleanup->plan(), 'unknown legacy table aborts', 'Unknown legacy SQL object');
$db->exec('DROP TABLE tram_unknown');
$db->exec('CREATE VIEW legacy_transport_view AS SELECT * FROM transport_stop');
rejects(fn() => $cleanup->plan(), 'transport SQL view aborts', 'SQL object requires review');
$db->exec('DROP VIEW legacy_transport_view');
$db->exec('CREATE TABLE external_reference(id INT PRIMARY KEY, FOREIGN KEY(id) REFERENCES transport_stop(id))');
rejects(fn() => $cleanup->plan(), 'external foreign key aborts without disabling constraints', 'External foreign key');
$db->exec('DROP TABLE external_reference');
$before = snapshot($db);
rejects(fn() => $cleanup->apply(dirname(__DIR__)), 'backup inside web checkout rejected', 'outside the web checkout');
check(snapshot($db) === $before, 'failed backup leaves every row intact');
$private = dirname(explode(';', substr($dsn, strlen('mysql:unix_socket=')))[0]) . '/backup';
$result = $cleanup->apply($private);
check($result['after']['drop'] === [] && array_sum($result['after']['delete']) === 0, 'all retired transport storage removed');
check((fileperms($result['backup']) & 0077) === 0, 'backup has private permissions');
$backup = gzdecode(file_get_contents($result['backup']));
check(str_contains($backup, 'retired-data') && !str_contains($backup, 'unchanged-hash') && !str_contains($backup, 'synthetic-session'), 'backup contains removed data without Auth secrets');
$after = snapshot($db);
foreach ($before as $table => $hash) {
    if (in_array($table, TramCleanupRepository::TABLES, true) || in_array($table, ['enumeration', 'api_rate_limit'], true)) continue;
    check(($after[$table] ?? null) === $hash, 'unchanged Auth and unrelated table ' . $table);
}
check((int)$db->query("SELECT COUNT(*) FROM enumeration WHERE franchise_code='other'")->fetchColumn() === 1, 'other tenant enumeration kept');
check((int)$db->query("SELECT COUNT(*) FROM api_rate_limit WHERE franchise_code='other'")->fetchColumn() === 6, 'other tenant counters kept');
check((int)$db->query("SELECT COUNT(*) FROM api_rate_limit WHERE franchise_code='tram'")->fetchColumn() === 4, 'all auth protection kept');
$once = snapshot($db);
check($cleanup->apply($private)['backup'] === null && snapshot($db) === $once, 'second application is a no-op');
$db->exec('CREATE DATABASE tram_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec('USE tram_restore_test');
$db->exec(file_get_contents(dirname(__DIR__) . '/migrations/schema.sql'));
$db->exec($backup);
foreach (TramCleanupRepository::TABLES as $table) check((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() === 1, 'backup restores ' . $table);
echo "$checks cleanup checks passed.\n";
