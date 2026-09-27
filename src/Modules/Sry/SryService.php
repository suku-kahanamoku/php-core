<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class SryService
{
    public function __construct(
        private SryStore $db,
        private CloudflareGateway $cloud,
    ) {}
    private function admin(array $a): void
    {
        if ($a["role"] !== "admin") {
            throw new SryError("forbidden", 403);
        }
    }
    private function integer(
        array $b,
        string $key,
        int $min = 1,
        int $max = 100000,
    ): int {
        $v = $b[$key] ?? null;
        if (!is_int($v) || $v < $min || $v > $max) {
            throw new SryError("invalidInput");
        }
        return $v;
    }
    public function today(array $a): string
    {
        $f = $this->db->one("SELECT timezone FROM sry_family WHERE id=?", [
            $a["family_id"],
        ]);
        return (new \DateTimeImmutable(
            "now",
            new \DateTimeZone($f["timezone"]),
        ))->format("Y-m-d");
    }
    public function member(array $a, int $id): array
    {
        $m = $this->db->one(
            "SELECT id,family_id,name,role,daily_target,wifi_allowed,data_allowed FROM sry_member WHERE id=? AND family_id=? AND active=1",
            [$id, $a["family_id"]],
        );
        if (
            !$m ||
            ($a["role"] !== "admin" &&
                $id !== $a["id"] &&
                $m["role"] !== "admin")
        ) {
            throw new SryError("notFound", 404);
        }
        return $m;
    }
    public function family(array $a): array
    {
        $members = $this->db->all(
            "SELECT id,family_id,name,role,daily_target,wifi_allowed,data_allowed FROM sry_member WHERE family_id=? AND active=1 ORDER BY role,id",
            [$a["family_id"]],
        );
        if ($a["role"] === "user") {
            $members = array_values(
                array_filter(
                    $members,
                    fn($m) => $m["role"] === "admin" ||
                        (int) $m["id"] === $a["id"],
                ),
            );
        }
        foreach ($members as &$m) {
            $earned = $this->db->one(
                "SELECT COALESCE(SUM(points),0) AS total FROM task_points WHERE member_id=? AND earned_on=?",
                [$m["id"], $this->today($a)],
            );
            foreach (["id", "family_id", "daily_target"] as $key) {
                $m[$key] = (int) $m[$key];
            }
            $m["points_today"] = (int) $earned["total"];
            $m["wifi_allowed"] = (bool) $m["wifi_allowed"];
            $m["data_allowed"] = (bool) $m["data_allowed"];
            $m["internet_earned"] = $m["points_today"] >= $m["daily_target"];
            $m["desired_wifi"] = $m["wifi_allowed"] && $m["internet_earned"];
            $m["desired_data"] = $m["data_allowed"] && $m["internet_earned"];
            $m["enforcement"] = "unsupported";
        }
        unset($m);
        return ["members" => $members, "today" => $this->today($a)];
    }
    public function addChild(array $a, array $b): array
    {
        $this->admin($a);
        $name = SryAuth::text($b, "name");
        $email = empty($b["email"]) ? null : SryAuth::email($b);
        $password = $email ? SryAuth::password($b) : null;
        return $this->db->transaction(function () use (
            $a,
            $name,
            $email,
            $password,
        ) {
            $userId = null;
            if ($email) {
                if (
                    $this->db->one(
                        "SELECT id FROM user WHERE franchise_code='sry' AND email=?",
                        [$email],
                    )
                ) {
                    throw new SryError("accountExists", 409);
                }
                $role = $this->db->one(
                    "SELECT id FROM role WHERE franchise_code='sry' AND name='user' AND deleted=0",
                );
                if (!$role) {
                    throw new SryError("notConfigured", 503);
                }
                $userId = $this->db->insert("user", [
                    "franchise_code" => "sry",
                    "first_name" => $name,
                    "last_name" => "",
                    "email" => $email,
                    "password" => password_hash($password, PASSWORD_BCRYPT, [
                        "cost" => 12,
                    ]),
                    "role_id" => $role["id"],
                    "status" => "active",
                ]);
            }
            $id = $this->db->insert("sry_member", [
                "family_id" => $a["family_id"],
                "name" => $name,
                "role" => "user",
                "user_id" => $userId,
            ]);
            $this->event($a, "family", $id, "child_added", [$a["id"]]);
            return $this->member($a, $id);
        });
    }
    public function updateChild(array $a, int $id, array $b): array
    {
        $this->admin($a);
        $m = $this->member($a, $id);
        if ($m["role"] !== "user") {
            throw new SryError("forbidden", 403);
        }
        $target = $this->integer($b, "daily_target", 0, 10000);
        if (
            !is_bool($b["wifi_allowed"] ?? null) ||
            !is_bool($b["data_allowed"] ?? null)
        ) {
            throw new SryError("invalidInput");
        }
        return $this->db->transaction(function () use ($a, $id, $b, $target) {
            $this->db->execute(
                "UPDATE sry_member SET daily_target=?,wifi_allowed=?,data_allowed=? WHERE id=? AND family_id=?",
                [
                    $target,
                    (int) $b["wifi_allowed"],
                    (int) $b["data_allowed"],
                    $id,
                    $a["family_id"],
                ],
            );
            $this->event($a, "family", $id, "policy_changed", [$id, $a["id"]]);
            return $this->member($a, $id);
        });
    }
    public function catalog(): array
    {
        return [
            "categories" => $this->db->all(
                "SELECT id,syscode,name FROM category WHERE franchise_code='sry' AND deleted=0 AND published=1 ORDER BY position,id",
            ),
            "enumerations" => $this->db->all(
                "SELECT id,type,syscode,label,value FROM enumeration WHERE franchise_code='sry' AND deleted=0 AND published=1 ORDER BY position,id",
            ),
        ];
    }
    public function tasks(array $a): array
    {
        $args = [$a["family_id"]];
        $scope = "";
        if ($a["role"] === "user") {
            $scope = " AND a.member_id=?";
            $args[] = $a["id"];
        }
        return $this->db->all(
            "SELECT a.id,a.task_id,a.member_id,a.due_date,a.points,a.status,a.revision,a.current_submission_id,t.title,t.description,t.category_id,t.enumeration_id,m.name AS member_name FROM task_assignment a JOIN tasks t ON t.id=a.task_id JOIN sry_member m ON m.id=a.member_id WHERE a.family_id=?$scope ORDER BY a.due_date DESC,a.id DESC LIMIT 200",
            $args,
        );
    }
    public function assignment(array $a, int $id, bool $lock = false): array
    {
        $row = $this->db->one(
            "SELECT a.*,t.title,t.description,m.name AS member_name FROM task_assignment a JOIN tasks t ON t.id=a.task_id JOIN sry_member m ON m.id=a.member_id WHERE a.id=? AND a.family_id=?" .
                ($lock ? $this->db->lock() : ""),
            [$id, $a["family_id"]],
        );
        if (
            !$row ||
            ($a["role"] === "user" && (int) $row["member_id"] !== $a["id"])
        ) {
            throw new SryError("notFound", 404);
        }
        return $row;
    }
    public function detail(array $a, int $id): array
    {
        $row = $this->assignment($a, $id);
        $row["submissions"] = $this->db->all(
            "SELECT s.id,s.media_id,s.note,s.created_at,r.decision,r.note AS review_note FROM task_submission s LEFT JOIN task_review r ON r.submission_id=s.id WHERE s.assignment_id=? AND s.family_id=? ORDER BY s.id DESC",
            [$id, $a["family_id"]],
        );
        $row["media_ids"] = array_column(
            $this->db->all(
                "SELECT media_id FROM task_media WHERE task_id=? AND family_id=?",
                [$row["task_id"], $a["family_id"]],
            ),
            "media_id",
        );
        return $row;
    }
    public function createTask(array $a, array $b): array
    {
        $this->admin($a);
        $member = $this->member($a, $this->integer($b, "member_id"));
        if ($member["role"] !== "user") {
            throw new SryError("invalidInput");
        }
        $title = SryAuth::text($b, "title", 160);
        $description = SryAuth::text($b, "description", 4000, false);
        $points = $this->integer($b, "points", 1, 1000);
        $date = SryAuth::text($b, "due_date", 10);
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d", $date);
        if (!$d || $d->format("Y-m-d") !== $date) {
            throw new SryError("invalidInput");
        }
        $category = $b["category_id"] ?? null;
        $enum = $b["enumeration_id"] ?? null;
        if (
            $category !== null &&
            !$this->db->one(
                "SELECT id FROM category WHERE id=? AND franchise_code='sry' AND deleted=0 AND published=1",
                [$category],
            )
        ) {
            throw new SryError("invalidInput");
        }
        if (
            $enum !== null &&
            !$this->db->one(
                "SELECT id FROM enumeration WHERE id=? AND franchise_code='sry' AND deleted=0 AND published=1",
                [$enum],
            )
        ) {
            throw new SryError("invalidInput");
        }
        $media = $b["media_ids"] ?? [];
        if (!is_array($media) || count($media) > 5) {
            throw new SryError("invalidInput");
        }
        foreach ($media as $mid) {
            $this->ownedMedia($a, (int) $mid);
        }
        return $this->db->transaction(function () use (
            $a,
            $b,
            $member,
            $title,
            $description,
            $points,
            $date,
            $category,
            $enum,
            $media,
        ) {
            $task = $this->db->insert("tasks", [
                "family_id" => $a["family_id"],
                "created_by" => $a["id"],
                "title" => $title,
                "description" => $description,
                "points" => $points,
                "category_id" => $category,
                "enumeration_id" => $enum,
            ]);
            foreach (array_unique($media) as $mid) {
                $this->db->insert("task_media", [
                    "task_id" => $task,
                    "media_id" => $mid,
                    "family_id" => $a["family_id"],
                ]);
            }
            $id = $this->db->insert("task_assignment", [
                "family_id" => $a["family_id"],
                "task_id" => $task,
                "member_id" => $member["id"],
                "due_date" => $date,
                "points" => $points,
            ]);
            $this->event($a, "tasks", $id, "task_assigned", [
                (int) $member["id"],
                $a["id"],
            ]);
            return $this->detail($a, $id);
        });
    }
    public function submit(array $a, int $id, array $b): array
    {
        if ($a["role"] !== "user") {
            throw new SryError("forbidden", 403);
        }
        $media = $this->ownedMedia($a, $this->integer($b, "media_id"));
        $note = SryAuth::text($b, "note", 2000, false);
        if (!str_starts_with($media["mime"], "image/")) {
            throw new SryError("imageRequired");
        }
        $revision = $this->integer($b, "revision");
        return $this->db->transaction(function () use (
            $a,
            $id,
            $b,
            $media,
            $note,
            $revision,
        ) {
            $task = $this->assignment($a, $id, true);
            if (
                (int) $task["revision"] !== $revision ||
                !in_array($task["status"], ["assigned", "returned"], true)
            ) {
                throw new SryError("conflict", 409);
            }
            if ($task["due_date"] > $this->today($a)) {
                throw new SryError("notDue", 409);
            }
            $sid = $this->db->insert("task_submission", [
                "assignment_id" => $id,
                "family_id" => $a["family_id"],
                "member_id" => $a["id"],
                "media_id" => $media["id"],
                "note" => $note,
            ]);
            $this->db->execute(
                "UPDATE task_assignment SET status='submitted',current_submission_id=?,revision=revision+1 WHERE id=?",
                [$sid, $id],
            );
            $this->event($a, "tasks", $id, "task_submitted", [
                $a["id"],
                ...$this->parents($a),
            ]);
            return $this->detail($a, $id);
        });
    }
    public function review(array $a, int $id, array $b): array
    {
        $this->admin($a);
        $revision = $this->integer($b, "revision");
        $decision = $b["decision"] ?? "";
        if (!in_array($decision, ["approved", "returned"], true)) {
            throw new SryError("invalidInput");
        }
        $note = SryAuth::text($b, "note", 2000, $decision === "returned");
        return $this->db->transaction(function () use (
            $a,
            $id,
            $revision,
            $decision,
            $note,
        ) {
            $task = $this->assignment($a, $id, true);
            if (
                (int) $task["revision"] !== $revision ||
                $task["status"] !== "submitted"
            ) {
                throw new SryError("conflict", 409);
            }
            $this->db->insert("task_review", [
                "submission_id" => $task["current_submission_id"],
                "reviewer_id" => $a["id"],
                "decision" => $decision,
                "note" => $note,
            ]);
            $this->db->execute(
                "UPDATE task_assignment SET status=?,revision=revision+1 WHERE id=?",
                [$decision, $id],
            );
            if ($decision === "approved") {
                $this->db->insert("task_points", [
                    "assignment_id" => $id,
                    "member_id" => $task["member_id"],
                    "points" => $task["points"],
                    "earned_on" => $this->today($a),
                ]);
            }
            $this->event($a, "tasks", $id, "task_" . $decision, [
                (int) $task["member_id"],
                $a["id"],
            ]);
            $this->event(
                $a,
                "family",
                (int) $task["member_id"],
                "points_changed",
                [(int) $task["member_id"], $a["id"]],
                false,
            );
            return $this->detail($a, $id);
        });
    }
    private function parents(array $a): array
    {
        return array_map(
            "intval",
            array_column(
                $this->db->all(
                    "SELECT id FROM sry_member WHERE family_id=? AND role='admin' AND active=1",
                    [$a["family_id"]],
                ),
                "id",
            ),
        );
    }
    private function ownedMedia(array $a, int $id): array
    {
        $m = $this->db->one(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND member_id=? AND state='ready'",
            [$id, $a["family_id"], $a["id"]],
        );
        if (!$m) {
            throw new SryError("notFound", 404);
        }
        return $m;
    }
    public function prepareMedia(array $a, array $b): array
    {
        $mime = $b["mime"] ?? "";
        $size = $this->integer($b, "size", 1, 25 * 1024 * 1024);
        if (
            !in_array(
                $mime,
                [
                    "image/jpeg",
                    "image/png",
                    "image/webp",
                    "video/mp4",
                    "audio/mp4",
                    "audio/mpeg",
                    "audio/webm",
                ],
                true,
            )
        ) {
            throw new SryError("invalidInput");
        }
        $key =
            "sry/" .
            $a["family_id"] .
            "/" .
            $a["id"] .
            "/" .
            bin2hex(random_bytes(24));
        $url = $this->cloud->url("/media", [
            "op" => "put",
            "key" => $key,
            "mime" => $mime,
            "size" => $size,
        ]);
        $id = $this->db->insert("sry_media", [
            "family_id" => $a["family_id"],
            "member_id" => $a["id"],
            "object_key" => $key,
            "mime" => $mime,
            "byte_size" => $size,
        ]);
        return ["id" => $id, "url" => $url];
    }
    public function completeMedia(array $a, int $id): array
    {
        $m = $this->db->one(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND member_id=?",
            [$id, $a["family_id"], $a["id"]],
        );
        if (!$m) {
            throw new SryError("notFound", 404);
        }
        $head = $this->cloud->call("/media/meta", [
            "op" => "head",
            "key" => $m["object_key"],
        ]);
        if (
            ($head["size"] ?? 0) !== (int) $m["byte_size"] ||
            ($head["mime"] ?? "") !== $m["mime"]
        ) {
            throw new SryError("uploadFailed", 409);
        }
        $this->db->execute("UPDATE sry_media SET state='ready' WHERE id=?", [
            $id,
        ]);
        return ["id" => $id];
    }
    public function mediaUrl(array $a, int $id): array
    {
        $m = $this->db->one(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND state='ready'",
            [$id, $a["family_id"]],
        );
        if (!$m) {
            throw new SryError("notFound", 404);
        }
        if ($a["role"] === "user" && (int) $m["member_id"] !== $a["id"]) {
            if (
                !$this->db->one(
                    "SELECT tm.task_id FROM task_media tm JOIN task_assignment a ON a.task_id=tm.task_id AND a.family_id=tm.family_id WHERE tm.media_id=? AND a.member_id=? AND a.family_id=?",
                    [$id, $a["id"], $a["family_id"]],
                )
            ) {
                throw new SryError("notFound", 404);
            }
        }
        return [
            "url" => $this->cloud->url("/media", [
                "op" => "get",
                "key" => $m["object_key"],
            ]),
        ];
    }
    private function event(
        array $a,
        string $topic,
        int $id,
        string $event,
        array $recipients,
        bool $notify = true,
    ): void {
        foreach (array_unique($recipients) as $member) {
            if ($notify) {
                $this->db->insert("sry_notification", [
                    "member_id" => $member,
                    "event" => $event,
                    "entity_id" => $id,
                ]);
            }
            $this->db->insert("sry_outbox", [
                "member_id" => $member,
                "topic" => $topic,
                "entity_id" => $id,
                "event" => $notify ? $event : "",
            ]);
        }
    }
    public function notifications(array $a): array
    {
        return $this->db->all(
            "SELECT id,event,entity_id,read_at,created_at FROM sry_notification WHERE member_id=? ORDER BY id DESC LIMIT 100",
            [$a["id"]],
        );
    }
    public function markRead(array $a, int $id): array
    {
        $this->db->execute(
            "UPDATE sry_notification SET read_at=? WHERE id=? AND member_id=?",
            [gmdate("Y-m-d H:i:s"), $id, $a["id"]],
        );
        return ["updated" => true];
    }
    public function push(array $a, array $b): array
    {
        $token = SryAuth::text($b, "token", 255);
        if (
            !preg_match(
                '/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$/',
                $token,
            )
        ) {
            throw new SryError("invalidInput");
        }
        $language = ($b["language"] ?? "cs") === "en" ? "en" : "cs";
        $this->db->execute(
            "INSERT INTO sry_push_device(token,member_id,language) VALUES(?,?,?) ON DUPLICATE KEY UPDATE member_id=VALUES(member_id),language=VALUES(language)",
            [$token, $a["id"], $language],
        );
        return ["updated" => true];
    }
    public function removePush(array $a, array $b): array
    {
        $this->db->execute(
            "DELETE FROM sry_push_device WHERE token=? AND member_id=?",
            [SryAuth::text($b, "token", 255), $a["id"]],
        );
        return ["updated" => true];
    }
    public function realtime(array $a): array
    {
        return [
            "url" => preg_replace(
                "/^https:/",
                "wss:",
                $this->cloud->url("/connect", [
                    "op" => "connect",
                    "member" => $a["id"],
                    "family" => $a["family_id"],
                ]),
            ),
        ];
    }
    public function messages(array $a): array
    {
        return $this->db->all(
            "SELECT id,sender_id,recipient_id,body,created_at FROM sry_chat WHERE family_id=? AND (sender_id=? OR recipient_id=?) ORDER BY id DESC LIMIT 100",
            [$a["family_id"], $a["id"], $a["id"]],
        );
    }
    public function sendMessage(array $a, array $b): array
    {
        $to = $this->member($a, $this->integer($b, "recipient_id"));
        if ((int) $to["id"] === $a["id"]) {
            throw new SryError("invalidInput");
        }
        $text = SryAuth::text($b, "body", 2000);
        return $this->db->transaction(function () use ($a, $to, $text) {
            $id = $this->db->insert("sry_chat", [
                "family_id" => $a["family_id"],
                "sender_id" => $a["id"],
                "recipient_id" => $to["id"],
                "body" => $text,
            ]);
            $this->event($a, "chat", $id, "chat_message", [
                $a["id"],
                (int) $to["id"],
            ]);
            return ["id" => $id];
        });
    }
}
