<?php
declare(strict_types=1);

/** Disposable SQLite database only; never loads bootstrap/.env or opens MySQL. */
function passwordAuditDatabase(): App\Modules\Database\Database
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
    $pdo->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, franchise_code TEXT, password TEXT,
        deleted INTEGER DEFAULT 0, status TEXT DEFAULT "active", email TEXT, role_id INTEGER,
        first_name TEXT, last_name TEXT)');
    $pdo->exec('CREATE TABLE role (id INTEGER PRIMARY KEY, name TEXT, deleted INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE user_token (id INTEGER PRIMARY KEY, user_id INTEGER, token TEXT,
        expires_at TEXT, deleted INTEGER DEFAULT 0)');
    $pdo->exec("INSERT INTO role (id, name) VALUES (1, 'user')");
    $statement = $pdo->prepare('INSERT INTO user (id, franchise_code, password, email, role_id, first_name, last_name)
        VALUES (?, ?, ?, ?, 1, ?, ?)');
    foreach ([1 => 'audit', 2 => 'other'] as $id => $tenant) {
        $statement->execute([$id, $tenant, password_hash('old-synthetic-password', PASSWORD_BCRYPT, ['cost' => 4]),
            'audit@example.invalid', 'Audit', 'User']);
    }
    $statement = $pdo->prepare('INSERT INTO user_token (user_id, token, expires_at) VALUES (?, ?, ?)');
    foreach ([1 => 'synthetic-old-session', 2 => 'synthetic-other-session'] as $id => $token) {
        $statement->execute([$id, $token, '2099-01-01 00:00:00']);
    }
    $statement->execute([1, 'synthetic-second-session', '2099-01-01 00:00:00']);
    $db = (new ReflectionClass(App\Modules\Database\Database::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(App\Modules\Database\Database::class, '_pdo'))->setValue($db, $pdo);
    return $db;
}
