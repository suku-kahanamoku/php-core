<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}

require __DIR__ . "/../../../../vendor/autoload.php";
require __DIR__ . "/database.php";
use App\Modules\Sry\{
    SrySqlRepository,
    SryAuth,
    SryService,
    SryError,
    CloudflareGateway,
    ManualReview,
};
// Never loads .env. Tests only a separately provisioned disposable MySQL database.
$dsn =
    getenv("SRY_TEST_DSN") ?:
    "mysql:unix_socket=/tmp/sry-mysql-test/mysql.sock;dbname=sry_test;charset=utf8mb4";
if (!str_contains($dsn, "dbname=sry_test")) {
    throw new RuntimeException("Only sry_test is permitted");
}
$pdo = new PDO(
    $dsn,
    getenv("SRY_TEST_USER") ?: "root",
    getenv("SRY_TEST_PASSWORD") ?: "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);
$db = new SrySqlRepository($pdo);
$auth = new SryAuth($db);
$cloud = new class ("https://media.example", str_repeat("a", 32)) extends
    CloudflareGateway
{
    public function call(
        string $path,
        array $claims,
        ?array $body = null,
    ): array {
        return ["size" => 12, "mime" => "image/jpeg"];
    }
};
$s = new SryService(testDatabase($pdo), $cloud);
$passed = 0;
function check(bool $condition, string $label): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException("FAIL: " . $label);
    }
    $passed++;
    echo "PASS $label\n";
}
function fails(callable $action, string $code): void
{
    try {
        $action();
        throw new RuntimeException("Expected " . $code);
    } catch (SryError $e) {
        check($e->errorCode === $code, "reject " . $code);
    }
}
$parent = $auth->signup([
    "name" => "Parent A",
    "email" => "parent-a@example.test",
    "password" => "a-long-password",
]);
$p = $parent["member"];
$parentB = $auth->signup([
    "name" => "Parent B",
    "email" => "parent-b@example.test",
    "password" => "a-long-password",
]);
$b = $parentB["member"];
check($p["role"] === "admin", "parent membership is admin");
check(
    $auth->login([
        "email" => "parent-a@example.test",
        "password" => "a-long-password",
    ])["member"]["id"] === $p["id"],
    "parent login",
);
fails(
    fn() => $auth->login([
        "email" => "parent-a@example.test",
        "password" => "bad",
    ]),
    "credentials",
);
$invite = $auth->invitation($p, []);
$joined = $auth->join(["token" => $invite["token"], "name" => "Child A"]);
$c = $joined["member"];
check(
    $c["role"] === "user" &&
        $c["family_id"] === $p["family_id"] &&
        $c["id"] !== $p["id"],
    "QR creates separate child identity",
);
check(
    $db->one("SELECT token_hash FROM sry_session WHERE token_hash=?", [
        hash("sha256", $joined["token"]),
    ]) !== null,
    "session stored hashed",
);
fails(
    fn() => $auth->join(["token" => $invite["token"], "name" => "Replay"]),
    "invitationInvalid",
);
fails(fn() => $auth->invitation($c, []), "forbidden");
$expired = $auth->invitation($p, []);
$db->execute("UPDATE sry_invitation SET expires_at=? WHERE token_hash=?", [
    "2000-01-01 00:00:00",
    hash("sha256", $expired["token"]),
]);
fails(
    fn() => $auth->join(["token" => $expired["token"], "name" => "Late"]),
    "invitationInvalid",
);
$other = $s->addChild($b, ["name" => "Other child"]);
fails(fn() => $auth->invitation($p, ["child_id" => $other["id"]]), "notFound");
$childProfile = $s->addChild($p, ["name" => "Sibling"]);
$reuse = $auth->invitation($p, ["child_id" => $childProfile["id"]]);
$sibling = $auth->join(["token" => $reuse["token"], "name" => "Ignored"])[
    "member"
];
check(
    $sibling["id"] === (int) $childProfile["id"],
    "link existing child without duplicating profile",
);
check(count($s->family($c)["members"]) === 2, "child cannot list siblings");
fails(
    fn() => $s->updateChild($c, $c["id"], [
        "daily_target" => 0,
        "wifi_allowed" => true,
        "data_allowed" => true,
    ]),
    "forbidden",
);
$s->updateChild($p, $c["id"], [
    "daily_target" => 10,
    "wifi_allowed" => true,
    "data_allowed" => false,
]);
$input = [
    "member_id" => $c["id"],
    "title" => "Clean room",
    "description" => "Clean the floor",
    "points" => 10,
    "due_date" => $s->today($p),
    "category_id" => null,
];
fails(fn() => $s->createTask($c, $input), "forbidden");
$f = $s->createTask($p, $input);
$id = (int) $f["id"];
fails(fn() => $s->detail($b, $id), "notFound");
fails(fn() => $s->detail($sibling, $id), "notFound");
check(count($s->tasks($c)) === 1, "child sees assigned task");
$upload = $s->prepareMedia($c, ["mime" => "image/jpeg", "size" => 12]);
fails(
    fn() => $s->submit($c, $id, [
        "revision" => 1,
        "media_id" => $upload["id"],
        "note" => "",
    ]),
    "notFound",
);
$s->completeMedia($c, $upload["id"]);
fails(fn() => $s->mediaUrl($b, $upload["id"]), "notFound");
fails(fn() => $s->mediaUrl($sibling, $upload["id"]), "notFound");
check(
    str_starts_with(
        $s->mediaUrl($p, $upload["id"])["url"],
        "https://media.example/media?ticket=",
    ),
    "parent gets scoped media ticket",
);
$s->submit($c, $id, [
    "revision" => 1,
    "media_id" => $upload["id"],
    "note" => "Done",
]);
fails(
    fn() => $s->submit($c, $id, [
        "revision" => 1,
        "media_id" => $upload["id"],
        "note" => "Replay",
    ]),
    "conflict",
);
fails(
    fn() => $s->review($c, $id, [
        "revision" => 2,
        "decision" => "approved",
        "note" => "",
    ]),
    "forbidden",
);
fails(
    fn() => $s->review($p, $id, [
        "revision" => 2,
        "decision" => "returned",
        "note" => "",
    ]),
    "invalidInput",
);
$s->review($p, $id, [
    "revision" => 2,
    "decision" => "returned",
    "note" => "Please finish the desk",
]);
check(
    (int) $db->one("SELECT COUNT(*) AS n FROM task_points")["n"] === 0,
    "returned task awards no points",
);
$s->submit($c, $id, [
    "revision" => 3,
    "media_id" => $upload["id"],
    "note" => "Desk done",
]);
fails(
    fn() => $s->review($p, $id, [
        "revision" => 2,
        "decision" => "approved",
        "note" => "stale",
    ]),
    "conflict",
);
$s->review($p, $id, [
    "revision" => 4,
    "decision" => "approved",
    "note" => "Good job",
]);
fails(
    fn() => $s->review($p, $id, [
        "revision" => 4,
        "decision" => "approved",
        "note" => "duplicate",
    ]),
    "conflict",
);
check(
    (int) $db->one("SELECT SUM(points) AS n FROM task_points")["n"] === 10,
    "approval awards exactly ten points once",
);
check(
    count($s->detail($p, $id)["submissions"]) === 2,
    "resubmission and review history retained",
);
$family = $s->family($c);
$child = array_values(
    array_filter($family["members"], fn($m) => $m["id"] === $c["id"]),
)[0];
check(
    $child["internet_earned"] &&
        $child["desired_wifi"] &&
        !$child["desired_data"] &&
        $child["enforcement"] === "unsupported",
    "points unlock intent without inventing native enforcement",
);
check(count($s->notifications($c)) > 0, "notifications persisted");
check(
    (int) $db->one("SELECT COUNT(*) AS n FROM sry_outbox")["n"] > 0,
    "realtime events transactional outbox",
);
fails(
    fn() => $s->sendMessage($c, [
        "recipient_id" => (int) $sibling["id"],
        "body" => "hidden",
    ]),
    "notFound",
);
$s->sendMessage($c, ["recipient_id" => $p["id"], "body" => "Thanks!"]);
check(
    count($s->messages($p)) === 1 && count($s->messages($b)) === 0,
    "chat limited to participants",
);
$old = $auth->session($p["id"]);
$resetToken = null;
$auth->reset(["email" => "parent-a@example.test"], "en", function (
    $email,
    $token,
    $lang,
) use (&$resetToken) {
    $resetToken = $token;
});
$auth->completeReset([
    "token" => $resetToken,
    "password" => "replacement-password",
]);
fails(fn() => $auth->identity($old["token"]), "unauthorized");
fails(
    fn() => $auth->completeReset([
        "token" => $resetToken,
        "password" => "another-password",
    ]),
    "invitationInvalid",
);
check(
    $auth->login([
        "email" => "parent-a@example.test",
        "password" => "replacement-password",
    ])["member"]["id"] === $p["id"],
    "reset updates password",
);
$auth->logout($joined["token"]);
fails(fn() => $auth->identity($joined["token"]), "unauthorized");
check(
    (new ManualReview())->suggest(1, [])["enabled"] === false,
    "AI stays disabled",
);
$account = $s->addChild($p, [
    "name" => "Email child",
    "email" => "email-child@example.test",
    "password" => "child-password-123",
]);
$childLogin = $auth->login([
    "email" => "email-child@example.test",
    "password" => "child-password-123",
]);
check(
    $childLogin["member"]["role"] === "user" &&
        $childLogin["member"]["family_id"] === $p["family_id"],
    "child password login remains family-scoped",
);
fails(fn() => $s->createTask($childLogin["member"], $input), "forbidden");
// Two independent MySQL connections race the same revision.
$concurrent = $s->createTask($p, $input);
$s->submit($c, (int) $concurrent["id"], [
    "revision" => 1,
    "media_id" => $upload["id"],
    "note" => "Concurrent evidence",
]);
$payload = json_encode([
    "actor" => $p,
    "id" => (int) $concurrent["id"],
    "start" => microtime(true) + 0.3,
]);
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $process = proc_open(
        [PHP_BINARY, __DIR__ . "/concurrent-review.php"],
        [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]],
        $pipes,
    );
    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    $workers[] = [$process, $pipes];
}
$results = [];
foreach ($workers as [$process, $pipes]) {
    $results[] = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(
        proc_close($process) === 0 && $error === "",
        "concurrent worker completed",
    );
}
sort($results);
check($results === ["conflict", "ok"], "concurrent approval has one winner");
check(
    (int) $db->one(
        "SELECT COUNT(*) AS n FROM task_points WHERE assignment_id=?",
        [$concurrent["id"]],
    )["n"] === 1,
    "concurrent approval writes one reward",
);
echo "$passed assertions passed\n";
