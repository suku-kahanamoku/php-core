<?php
declare(strict_types=1);
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require __DIR__ . "/../bootstrap.php";
use App\Modules\Database\Database;
use App\Modules\Sry\{SrySqlRepository, CloudflareGateway};
$db = new SrySqlRepository(Database::getInstance()->getPdo());
$cloud = new CloudflareGateway(
    $_ENV["SRY_CLOUDFLARE_URL"] ?? "",
    $_ENV["SRY_CLOUDFLARE_SECRET"] ?? "",
);
$lock = $db->one("SELECT GET_LOCK('sry-outbox',0) AS acquired");
if ((int) $lock["acquired"] !== 1) {
    exit(0);
}
$watch = in_array("--watch", $argv, true);
try {
    do {
        $events = $db->all(
            "SELECT o.*,m.family_id FROM sry_outbox o JOIN sry_member m ON m.id=o.member_id WHERE o.delivered_at IS NULL AND m.active=1 ORDER BY o.attempts,o.id LIMIT 25",
        );
        foreach ($events as $event) {
            $db->execute(
                "UPDATE sry_outbox SET attempts=attempts+1 WHERE id=?",
                [$event["id"]],
            );
            try {
                if (!$event["realtime_at"]) {
                    $cloud->call(
                        "/publish",
                        [
                            "op" => "publish",
                            "family" => (int) $event["family_id"],
                            "member" => (int) $event["member_id"],
                        ],
                        [
                            "topic" => $event["topic"],
                            "id" => (int) $event["entity_id"],
                            "event_id" => (int) $event["id"],
                        ],
                    );
                    $db->execute(
                        "UPDATE sry_outbox SET realtime_at=? WHERE id=?",
                        [gmdate("Y-m-d H:i:s"), $event["id"]],
                    );
                }
                if (!$event["push_at"] && $event["event"] !== "") {
                    $devices = $db->all(
                        "SELECT token,language FROM sry_push_device WHERE member_id=?",
                        [$event["member_id"]],
                    );
                    foreach ($devices as $device) {
                        $messages = require __DIR__ .
                            "/../src/Modules/Sry/locales/" .
                            $device["language"] .
                            ".php";
                        $body = [
                            "to" => $device["token"],
                            "title" => "sorry–jako.",
                            "body" =>
                                $messages[$event["event"]] ??
                                $messages["update"],
                            "sound" => "default",
                            "channelId" => "tasks",
                            "data" => [
                                "topic" => $event["topic"],
                                "id" => (int) $event["entity_id"],
                                "event_id" => (int) $event["id"],
                            ],
                        ];
                        $headers = [
                            "Content-Type: application/json",
                            "Accept: application/json",
                        ];
                        if (!empty($_ENV["SRY_EXPO_ACCESS_TOKEN"])) {
                            $headers[] =
                                "Authorization: Bearer " .
                                $_ENV["SRY_EXPO_ACCESS_TOKEN"];
                        }
                        $response = \App\Modules\Http\HttpModule::client()->send(new \App\Modules\Http\HttpRequest(
                            'https://exp.host/--/api/v2/push/send', 'POST', $headers, $body, timeoutMs: 15000,
                        ));
                        if ($response->error !== null) {
                            throw new RuntimeException('Push gateway connection failed');
                        }
                        $raw = $response->body;
                        $status = $response->status;
                        $result = json_decode($raw ?: "{}", true);
                        $ticket = $result["data"] ?? [];
                        if (
                            ($ticket["details"]["error"] ?? "") ===
                            "DeviceNotRegistered"
                        ) {
                            $db->execute(
                                "DELETE FROM sry_push_device WHERE token=?",
                                [$device["token"]],
                            );
                            continue;
                        }
                        if (
                            $status !== 200 ||
                            ($ticket["status"] ?? "") !== "ok"
                        ) {
                            throw new RuntimeException(
                                "Push gateway not accepting notifications",
                            );
                        }
                    }
                }
                $db->execute(
                    "UPDATE sry_outbox SET push_at=?,delivered_at=? WHERE id=?",
                    [
                        gmdate("Y-m-d H:i:s"),
                        gmdate("Y-m-d H:i:s"),
                        $event["id"],
                    ],
                );
            } catch (Throwable $e) {
                fwrite(
                    STDERR,
                    "SRY outbox retry id=" . (int) $event["id"] . PHP_EOL,
                );
            }
        }
        if ($watch) {
            sleep($events ? 2 : 5);
        }
    } while ($watch);
} finally {
    $db->execute("SELECT RELEASE_LOCK('sry-outbox')");
}
