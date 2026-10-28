<?php
declare(strict_types=1);

/** Run explicitly with php8.2. No app bootstrap, external DB or network. */
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/fixtures/password-database.php';

use App\Modules\User\UserRepository;

$checks = 0;
function hardeningCheck(bool $ok, string $name): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException($name);
    echo "PASS: $name\n";
}

foreach (['password-change', 'password-incorrect', 'contact-same-email', 'contact-rotating-email',
    'newsletter-same-email', 'newsletter-rotating-email', 'contact-independent-ip', 'newsletter-independent-ip'] as $case) {
    $child = proc_open([PHP_BINARY, '-d', 'allow_url_fopen=0', __DIR__ . '/deep-hostile.audit.php', $case],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($child)) throw new RuntimeException('Cannot start probe');
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($child);
    preg_match('/DEEP_AUDIT_RESULT:(\{[^\n]+\})/', $output, $match);
    $data = json_decode($match[1] ?? 'null', true);
    hardeningCheck($exit === 0 && $errors === '' && is_array($data) && !isset($data['harness_error']), "$case harness");
    if ($case === 'password-change') {
        hardeningCheck($data['new_password_accepted'] && !$data['old_session_accepted']
            && !$data['second_session_accepted'] && $data['other_user_session_accepted'], 'new password; all own tokens revoked; other user preserved');
    } elseif ($case === 'password-incorrect') {
        preg_match('/PASSWORD_STATE:(\{[^\n]+\})/', $output, $match);
        $state = json_decode($match[1] ?? 'null', true);
        hardeningCheck($data['status'] === 401 && $state['old_password_accepted'] && $state['old_token_accepted'], 'wrong current password changes nothing');
    } elseif (str_ends_with($case, 'independent-ip')) {
        hardeningCheck($data['status'] === 200 && $data['mail_calls'] === 6, "$case allows independent clients");
    } else {
        hardeningCheck($data['status'] === 429 && $data['mail_calls'] === 5, "$case blocks sixth request despite forged X-Forwarded-For");
    }
}

// Real repository + real SQL transactions, entirely in SQLite memory.
$db = passwordAuditDatabase();
$users = new UserRepository($db, 'audit');
$old = $users->findPasswordHash(1);
$new = password_hash('new-synthetic-password', PASSWORD_BCRYPT, ['cost' => 4]);
$db->getPdo()->exec("CREATE TRIGGER fail_revoke BEFORE DELETE ON user_token BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
$failed = false;
try { $users->changePasswordAndRevokeTokens(1, $old, $new); }
catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'synthetic failure'); }
hardeningCheck($failed && $users->findPasswordHash(1) === $old && !$db->getPdo()->inTransaction()
    && (int) $db->fetchOne('SELECT COUNT(*) AS n FROM user_token')['n'] === 3, 'revoke failure rolls back password and tokens');
$db->getPdo()->exec('DROP TRIGGER fail_revoke');
$foreignHash = (new UserRepository($db, 'other'))->findPasswordHash(2);
hardeningCheck(!$users->changePasswordAndRevokeTokens(2, $foreignHash, $new)
    && (new UserRepository($db, 'other'))->findPasswordHash(2) === $foreignHash,
    'foreign tenant cannot change password even with correct stored hash');
$db->update('user', ['status' => 'blocked'], 'id = ?', [1]);
hardeningCheck(!$users->changePasswordAndRevokeTokens(1, $old, $new), 'blocked account cannot change password');
$db->update('user', ['status' => 'active', 'deleted' => 1], 'id = ?', [1]);
hardeningCheck(!$users->changePasswordAndRevokeTokens(1, $old, $new), 'deleted account cannot change password');
$db->update('user', ['deleted' => 0], 'id = ?', [1]);
hardeningCheck($users->changePasswordAndRevokeTokens(1, $old, $new), 'transaction commits password and token revocation');
$db->insert('user_token', ['user_id' => 1, 'token' => 'synthetic-new-session', 'expires_at' => '2099-01-01 00:00:00']);
hardeningCheck(!$users->changePasswordAndRevokeTokens(1, $old, 'stale-request-hash')
    && $users->findPasswordHash(1) === $new
    && (int) $db->fetchOne('SELECT COUNT(*) AS n FROM user_token WHERE user_id = 1')['n'] === 1,
    'stale concurrent change cannot overwrite new password or revoke new session');
echo "$checks checks passed.\n";
