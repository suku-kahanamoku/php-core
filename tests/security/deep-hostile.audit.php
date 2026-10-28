<?php
declare(strict_types=1);

/** Explicit diagnostic only. No app bootstrap/.env, MySQL, SMTP, PDF renderer or network.
 * Password probes use disposable in-memory SQLite and real repositories.
 * Each real API/service probe runs in a child process because Response methods exit.
 * Run: php8.2 tests/security/deep-hostile.audit.php
 */
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/fixtures/password-database.php';

use App\Middleware\InternalAuthMiddleware;
use App\Modules\Auth\{Auth, AuthService, UserTokenRepository};
use App\Modules\Database\Database;
use App\Modules\Invoice\{InvoiceApi, InvoiceRepository, InvoiceService};
use App\Modules\Mailer\{MailerApi, MailerService};
use App\Modules\Order\OrderRepository;
use App\Modules\Router\{Request, Response, Router};
use App\Modules\User\{UserRepository, UserService};
use App\Utils\RateLimiter;

final class DeepAuditEffect extends RuntimeException {}

final class DeepAuditAuth extends Auth
{
    public int $checks = 0;
    public function __construct(public bool $logged = false, public string $actorRole = 'user') {}
    public function check(): bool { ++$this->checks; return $this->logged; }
    public function id(): ?int { return $this->logged ? 1 : null; }
    public function hasRole(string $role): bool { return $this->logged && $role === $this->actorRole; }
    public function require(): void { ++$this->checks; if (!$this->logged) Response::unauthorized('Audit denied'); }
    public function requireRole(string $role): void { $this->require(); if (!$this->hasRole($role)) Response::forbidden('Audit denied'); }
}

final class DeepAuditMailer extends MailerService
{
    public array $calls = [];
    public bool $interrupt = false;
    public function __construct() {}
    public function sendMail(string|array $to, string $subject, string $template, array $templateData = [], array $attachments = [], string|array|null $bcc = null): bool
    {
        $this->calls[] = ['attachments' => count($attachments)];
        if ($this->interrupt) throw new DeepAuditEffect('Reached fake mail adapter');
        return true;
    }
    public function sendTestMail(string $to): bool { $this->calls[] = []; return true; }
}

final class DeepAuditUsers extends UserRepository
{
    public array $updates = [];
    public string $hash;
    public function __construct() { $this->hash = password_hash('old-synthetic-password', PASSWORD_BCRYPT, ['cost' => 4]); }
    public function findById(int $id, ?array $projection = null): ?array
    {
        return ['id' => $id, 'email' => 'audit@example.invalid', 'role' => 'user', 'first_name' => 'Audit', 'last_name' => 'User', 'password' => $this->hash];
    }
    public function findPasswordHash(int $id): ?string { return $this->hash; }
    public function update(int $id, array $data, ?array $projection = null): array
    {
        $this->updates[] = $data;
        if (isset($data['password'])) $this->hash = $data['password'];
        return $this->findById($id);
    }
}

final class DeepAuditOrders extends OrderRepository
{
    public function __construct() {}
    public function findById(int $id, ?array $projection = null): ?array
    {
        return ['id' => $id, 'user_id' => 22, 'order_number' => 'SYNTHETIC-701', 'currency' => 'CZK',
            'total_price' => 100, 'total_price_with_vat' => 121, 'total_price_all' => 100, 'total_price_all_with_vat' => 121];
    }
}

final class DeepAuditInvoices extends InvoiceRepository
{
    public int $writes = 0;
    public function __construct() {}
    public function generateNumber(): string { return 'SYNTHETIC-INVOICE'; }
    public function create(array $data, ?array $projection = null): array
    {
        ++$this->writes;
        throw new DeepAuditEffect('Reached fake invoice persistence; stopped before PDF or other work');
    }
}

final class DeepAuditStatement extends PDOStatement { public function __construct() {} }
final class DeepAuditDatabase extends Database
{
    public array $rows = [];
    public array $inserts = [];
    public function __construct() {}
    public function query(string $sql, array $params = []): PDOStatement
    {
        $key = json_encode(array_slice($params, 0, 4));
        $this->rows[$key] = ($this->rows[$key] ?? 0) + 1;
        return new DeepAuditStatement();
    }
    public function fetchOne(string $sql, array $params = []): array|false { return ['attempts' => $this->rows[json_encode($params)] ?? 0]; }
    public function insert(string $table, array $data): int { $this->inserts[] = $data; return 1; }
}

function deepObject(string $class, array $properties): object
{
    $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach ($properties as $name => $value) (new ReflectionProperty($class, $name))->setValue($object, $value);
    return $object;
}

$case = $argv[1] ?? null;
if ($case !== null) {
    $_ENV = ['INTERNAL_API_KEY' => 'synthetic-deep-audit-key', 'FRANCHISE_CODES' => 'audit.invalid:audit'];
    $_GET = $_POST = $_FILES = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/mailer/send', 'SCRIPT_NAME' => '/api/mailer/index.php',
        'SCRIPT_FILENAME' => dirname(__DIR__, 2) . '/api/mailer/index.php', 'HTTP_HOST' => 'audit.invalid',
        'HTTP_X_INTERNAL_KEY' => 'synthetic-deep-audit-key', 'REMOTE_ADDR' => '192.0.2.10', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'];
    $result = ['case' => $case];
    $root = sys_get_temp_dir() . '/php-core-deep-audit-' . bin2hex(random_bytes(6));
    mkdir($root . '/files/audit', 0700, true);
    file_put_contents($root . '/files/audit/private.pdf', 'SYNTHETIC PRIVATE ATTACHMENT');
    $_ENV['FILE_ROOT'] = $root;
    $auth = new DeepAuditAuth(!str_contains($case, 'guest'), str_contains($case, 'admin') ? 'admin' : 'user');
    $mailer = new DeepAuditMailer();
    $invoice = new DeepAuditInvoices();
    register_shutdown_function(static function () use (&$result, $auth, $mailer, $invoice, $root): void {
        $result += ['status' => http_response_code() ?: 200, 'auth_checks' => $auth->checks,
            'mail_calls' => count($mailer->calls), 'attachment_count' => array_sum(array_column($mailer->calls, 'attachments')), 'invoice_writes' => $invoice->writes];
        unlink($root . '/files/audit/private.pdf');
        rmdir($root . '/files/audit'); rmdir($root . '/files'); rmdir($root);
        echo "\nDEEP_AUDIT_RESULT:" . json_encode($result) . "\n";
    });
    set_error_handler(static function ($severity, $message, $file, $line): void { throw new ErrorException($message, 0, $severity, $file, $line); });
    try {
        if (str_starts_with($case, 'mailer-')) {
            $_POST = ['to' => 'recipient@example.invalid', 'subject' => 'Synthetic audit', 'template' => 'test',
                'fromEmail' => 'audit@example.invalid', 'fromName' => 'Audit', 'fromPhone' => '123', 'attachments' => 'files/audit/private.pdf'];
            if ($case === 'mailer-guest-test') {
                $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/api/mailer/test'; $_GET['email'] = 'audit@example.invalid';
            }
            if ($case === 'mailer-guest-no-key') unset($_SERVER['HTTP_X_INTERNAL_KEY']);
            $request = new Request(); (new InternalAuthMiddleware())($request);
            $api = deepObject(MailerApi::class, ['_service' => $mailer, '_code' => 'audit', '_auth' => $auth]);
            $router = new Router(); $api->registerRoutes($router); $router->dispatch($request);
        } elseif (str_starts_with($case, 'invoice-')) {
            $_SERVER['SCRIPT_NAME'] = '/api/invoices/index.php'; $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 2) . '/api/invoices/index.php';
            $_SERVER['REQUEST_URI'] = '/api/invoices/'; $_POST = ['order_id' => 701];
            if ($case === 'invoice-guest-no-key') unset($_SERVER['HTTP_X_INTERNAL_KEY']);
            $request = new Request(); (new InternalAuthMiddleware())($request);
            $service = deepObject(InvoiceService::class, ['_auth' => $auth, '_invoice' => $invoice, '_order' => new DeepAuditOrders(), '_user' => new DeepAuditUsers()]);
            $api = deepObject(InvoiceApi::class, ['_auth' => $auth, '_service' => $service]);
            $router = new Router(); $api->registerRoutes($router); $router->dispatch($request);
        } elseif (in_array($case, ['password-change', 'password-incorrect'], true)) {
            $database = passwordAuditDatabase();
            $users = new UserRepository($database, 'audit');
            $tokens = new UserTokenRepository($database);
            $service = deepObject(AuthService::class, ['_auth' => $auth, '_users' => $users, '_tokens' => $tokens]);
            register_shutdown_function(static function () use ($users, $tokens): void {
                echo "\nPASSWORD_STATE:" . json_encode([
                    'old_password_accepted' => password_verify('old-synthetic-password', $users->findPasswordHash(1)),
                    'old_token_accepted' => $tokens->findUserByToken('synthetic-old-session', 'audit') !== null,
                ]) . "\n";
            });
            $service->changePassword($case === 'password-incorrect' ? 'wrong-synthetic-password' : 'old-synthetic-password', 'new-synthetic-password');
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer synthetic-old-session';
            $oldSession = deepObject(Auth::class, ['_userToken' => $tokens]);
            $result += ['new_password_accepted' => password_verify('new-synthetic-password', $users->findPasswordHash(1)),
                'old_session_accepted' => $oldSession->check(),
                'second_session_accepted' => $tokens->findUserByToken('synthetic-second-session', 'audit') !== null,
                'other_user_session_accepted' => $tokens->findUserByToken('synthetic-other-session', 'other') !== null];
        } elseif ($case === 'user-self-mass-assignment') {
            $users = new DeepAuditUsers();
            $service = deepObject(UserService::class, ['_auth' => $auth, '_user' => $users]);
            $answer = $service->update(1, ['first_name' => 'Audit', 'role_id' => 999, 'status' => 'active', 'email' => 'changed@example.invalid']);
            $result += ['written_fields' => array_keys($users->updates[0]), 'response_has_password' => isset($answer['password'])];
        } elseif ($case === 'user-foreign-read') {
            $service = deepObject(UserService::class, ['_auth' => $auth, '_user' => new DeepAuditUsers()]);
            $service->get(22);
        } elseif (str_starts_with($case, 'contact-') || str_starts_with($case, 'newsletter-')) {
            $_ENV['MAILER_ADMIN_EMAIL'] = 'admin@example.invalid';
            $database = new DeepAuditDatabase();
            $mailer->interrupt = true;
            $api = deepObject(MailerApi::class, ['_service' => $mailer, '_code' => 'audit', '_auth' => $auth, '_rateLimiter' => new RateLimiter($database, 'audit')]);
            $handler = new ReflectionMethod(MailerApi::class, str_starts_with($case, 'newsletter-') ? 'sendNewsletter' : 'sendContactForm');
            for ($i = 0; $i < 6; ++$i) {
                if (str_ends_with($case, 'independent-ip')) $_SERVER['REMOTE_ADDR'] = '192.0.2.' . ($i + 10);
                $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.' . ($i + 10);
                $_POST = ['name' => 'Synthetic', 'email' => (str_ends_with($case, 'same-email') ? 'same' : 'user' . $i) . '@example.invalid', 'project' => 'Audit', 'message' => 'Synthetic only'];
                try { $handler->invoke($api, new Request()); } catch (DeepAuditEffect $reached) {}
            }
            $result['rate_buckets'] = count($database->rows);
        } elseif ($case === 'token-at-rest') {
            $database = new DeepAuditDatabase();
            (new UserTokenRepository($database))->create(1, 'synthetic-token-not-a-real-secret', '2099-01-01 00:00:00');
            $result['raw_token_persisted'] = in_array('synthetic-token-not-a-real-secret', $database->inserts[0], true);
        } else throw new RuntimeException('Unknown audit case');
    } catch (DeepAuditEffect $reached) {
        $result['effect_reached'] = true;
    } catch (Throwable $failure) {
        $result['harness_error'] = get_class($failure) . ': ' . $failure->getMessage();
        exit(2);
    }
    exit(0);
}

$cases = ['mailer-guest-no-key', 'mailer-guest-send', 'mailer-user-send', 'mailer-admin-send', 'mailer-guest-test',
    'invoice-guest-no-key', 'invoice-guest-create', 'invoice-user-foreign', 'invoice-admin-create', 'password-change', 'user-self-mass-assignment',
    'user-foreign-read', 'contact-same-email', 'contact-rotating-email', 'token-at-rest'];
$checks = [];
foreach ($cases as $name) {
    $process = proc_open([PHP_BINARY, '-d', 'allow_url_fopen=0', __FILE__, $name], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start isolated PHP test');
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
    preg_match('/DEEP_AUDIT_RESULT:(\{[^\n]+\})/', $output, $match);
    $evidence = json_decode($match[1] ?? 'null', true);
    $harness = $exit !== 0 || !is_array($evidence) || isset($evidence['harness_error']);
    $ok = !$harness && match ($name) {
        'mailer-guest-no-key', 'invoice-guest-no-key' => $evidence['status'] === 401 && $evidence['mail_calls'] === 0 && $evidence['invoice_writes'] === 0,
        // API.md explicitly permits trusted internal callers without end-user Bearer.
        'mailer-admin-send', 'mailer-guest-send', 'mailer-user-send', 'mailer-guest-test' => $evidence['status'] === 200 && $evidence['mail_calls'] === 1,
        'invoice-admin-create', 'invoice-guest-create', 'invoice-user-foreign' => $evidence['invoice_writes'] === 1,
        'password-change' => $evidence['new_password_accepted'] && !$evidence['old_session_accepted']
            && !$evidence['second_session_accepted'] && $evidence['other_user_session_accepted'],
        'user-self-mass-assignment' => $evidence['written_fields'] === ['first_name'] && !$evidence['response_has_password'],
        'user-foreign-read' => $evidence['status'] === 404,
        'contact-same-email' => $evidence['status'] === 429 && $evidence['mail_calls'] === 5,
        'contact-rotating-email' => $evidence['status'] === 429 && $evidence['mail_calls'] <= 5,
        'token-at-rest' => !$evidence['raw_token_persisted'],
        default => in_array($evidence['status'], [401, 403, 404], true) && $evidence['mail_calls'] === 0 && $evidence['invoice_writes'] === 0,
    };
    // Explicit user decision on 28 Oct: retain raw session tokens; keep the evidence.
    $accepted = $name === 'token-at-rest' && !$harness && $evidence['raw_token_persisted'];
    $checks[] = ['id' => $name, 'result' => $harness ? 'BLOCKED' : ($accepted ? 'ACCEPTED_RISK' : ($ok ? 'PASS' : 'FAIL')), 'evidence' => $evidence, 'stderr' => $errors];
}
echo json_encode(['mode' => 'real_router_and_domain_code_with_memory_adapters_and_disposable_sqlite', 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(count(array_filter($checks, fn($c) => $c['result'] === 'BLOCKED')) ? 2 : (count(array_filter($checks, fn($c) => $c['result'] === 'FAIL')) ? 1 : 0));
