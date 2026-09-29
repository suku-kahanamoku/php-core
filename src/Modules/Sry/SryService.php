<?php

declare(strict_types=1);

namespace App\Modules\Sry;

final class SryService
{
    private FamilyRepository $family;
    private TaskRepository $tasks;
    private MediaRepository $media;
    private NotificationRepository $notifications;
    private ChatRepository $chat;
    private \App\Modules\Category\CategoryRepository $categories;
    private \App\Modules\Enumeration\EnumerationRepository $enumerations;
    private \App\Modules\User\UserRepository $users;
    private \App\Modules\Role\RoleRepository $roles;
    public function __construct(
        \App\Modules\Database\Database $db,
        private CloudflareGateway $cloud,
    ) {
        $this->family = new FamilyRepository($db, "sry");
        $this->tasks = new TaskRepository($db, "sry");
        $this->media = new MediaRepository($db, "sry");
        $this->notifications = new NotificationRepository($db, "sry");
        $this->chat = new ChatRepository($db, "sry");
        $this->categories = new \App\Modules\Category\CategoryRepository(
            $db,
            "sry",
        );
        $this->enumerations = new \App\Modules\Enumeration\EnumerationRepository(
            $db,
            "sry",
        );
        $this->users = new \App\Modules\User\UserRepository($db, "sry");
        $this->roles = new \App\Modules\Role\RoleRepository($db, "sry");
    }
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
        $f = $this->family->timezone($a["family_id"]);
        return (new \DateTimeImmutable(
            "now",
            new \DateTimeZone($f["timezone"]),
        ))->format("Y-m-d");
    }
    public function member(array $a, int $id): array
    {
        $m = $this->family->findMember($id, $a["family_id"]);
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
        $members = $this->family->members($a["family_id"]);
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
            $earned = $this->tasks->earnedPoints($m["id"], $this->today($a));
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
        $name = SryInput::text($b, "name");
        $email = empty($b["email"]) ? null : SryInput::email($b);
        $password = $email ? SryInput::password($b) : null;
        return $this->family->transaction(function () use (
            $a,
            $name,
            $email,
            $password,
        ) {
            $userId = null;
            if ($email) {
                if ($this->users->emailExists($email)) {
                    throw new SryError("accountExists", 409);
                }
                $role = $this->roles->findIdByName("user");
                if (!$role) {
                    throw new SryError("notConfigured", 503);
                }
                $userId = (int) $this->users->create(
                    [
                        "first_name" => $name,
                        "last_name" => "",
                        "email" => $email,
                        "password" => password_hash(
                            $password,
                            PASSWORD_BCRYPT,
                            ["cost" => 12],
                        ),
                        "role_id" => $role,
                        "status" => "active",
                    ],
                    ["id"],
                )["id"];
            }
            $id = $this->family->createMember([
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
        return $this->family->transaction(function () use (
            $a,
            $id,
            $b,
            $target,
        ) {
            $this->family->updatePolicy(
                $target,
                (int) $b["wifi_allowed"],
                (int) $b["data_allowed"],
                $id,
                $a["family_id"],
            );
            $this->event($a, "family", $id, "policy_changed", [$id, $a["id"]]);
            return $this->member($a, $id);
        });
    }
    public function catalog(): array
    {
        return [
            "categories" => $this->published($this->categories),
            "enumerations" => $this->published($this->enumerations),
        ];
    }
    private function published(object $repository): array
    {
        $items = [];
        $page = 1;
        do {
            $result = $repository->findAll(
                $page++,
                100,
                "",
                json_encode(["published" => 1]),
            );
            array_push($items, ...$result["data"]);
        } while ($page <= $result["totalPages"]);
        return $items;
    }
    public function tasks(array $a): array
    {
        return $this->tasks->forFamily(
            $a["family_id"],
            $a["role"] === "user" ? $a["id"] : null,
        );
    }
    public function assignment(array $a, int $id, bool $lock = false): array
    {
        $row = $this->tasks->findAssignment($id, $a["family_id"], $lock);
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
        $row["submissions"] = $this->tasks->submissions($id, $a["family_id"]);
        $row["media_ids"] = array_column(
            $this->tasks->media($row["task_id"], $a["family_id"]),
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
        $title = SryInput::text($b, "title", 160);
        $description = SryInput::text($b, "description", 4000, false);
        $points = $this->integer($b, "points", 1, 1000);
        $date = SryInput::text($b, "due_date", 10);
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d", $date);
        if (!$d || $d->format("Y-m-d") !== $date) {
            throw new SryError("invalidInput");
        }
        $category = $b["category_id"] ?? null;
        $enum = $b["enumeration_id"] ?? null;
        if (
            $category !== null &&
            !(
                $this->categories->findById((int) $category, ["published"])[
                    "published"
                ] ?? false
            )
        ) {
            throw new SryError("invalidInput");
        }
        if (
            $enum !== null &&
            !(
                $this->enumerations->findById((int) $enum, ["published"])[
                    "published"
                ] ?? false
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
        return $this->family->transaction(function () use (
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
            $task = $this->tasks->createTask([
                "family_id" => $a["family_id"],
                "created_by" => $a["id"],
                "title" => $title,
                "description" => $description,
                "points" => $points,
                "category_id" => $category,
                "enumeration_id" => $enum,
            ]);
            foreach (array_unique($media) as $mid) {
                $this->tasks->attachMedia([
                    "task_id" => $task,
                    "media_id" => $mid,
                    "family_id" => $a["family_id"],
                ]);
            }
            $id = $this->tasks->assign([
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
        $note = SryInput::text($b, "note", 2000, false);
        if (!str_starts_with($media["mime"], "image/")) {
            throw new SryError("imageRequired");
        }
        $revision = $this->integer($b, "revision");
        return $this->family->transaction(function () use (
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
            $sid = $this->tasks->createSubmission([
                "assignment_id" => $id,
                "family_id" => $a["family_id"],
                "member_id" => $a["id"],
                "media_id" => $media["id"],
                "note" => $note,
            ]);
            $this->tasks->markSubmitted($sid, $id);
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
        $note = SryInput::text($b, "note", 2000, $decision === "returned");
        return $this->family->transaction(function () use (
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
            $this->tasks->createReview([
                "submission_id" => $task["current_submission_id"],
                "reviewer_id" => $a["id"],
                "decision" => $decision,
                "note" => $note,
            ]);
            $this->tasks->markReviewed($decision, $id);
            if ($decision === "approved") {
                $this->tasks->awardPoints([
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
            array_column($this->family->parents($a["family_id"]), "id"),
        );
    }
    private function ownedMedia(array $a, int $id): array
    {
        $m = $this->media->ownedReady($id, $a["family_id"], $a["id"]);
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
        $id = $this->media->create([
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
        $m = $this->media->owned($id, $a["family_id"], $a["id"]);
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
        $this->media->markReady($id);
        return ["id" => $id];
    }
    public function mediaUrl(array $a, int $id): array
    {
        $m = $this->media->ready($id, $a["family_id"]);
        if (!$m) {
            throw new SryError("notFound", 404);
        }
        if ($a["role"] === "user" && (int) $m["member_id"] !== $a["id"]) {
            if (
                !$this->media->assignedReference($id, $a["id"], $a["family_id"])
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
                $this->notifications->create([
                    "member_id" => $member,
                    "event" => $event,
                    "entity_id" => $id,
                ]);
            }
            $this->notifications->enqueue([
                "member_id" => $member,
                "topic" => $topic,
                "entity_id" => $id,
                "event" => $notify ? $event : "",
            ]);
        }
    }
    public function notifications(array $a): array
    {
        return $this->notifications->forMember($a["id"]);
    }
    public function markRead(array $a, int $id): array
    {
        $this->notifications->markRead(gmdate("Y-m-d H:i:s"), $id, $a["id"]);
        return ["updated" => true];
    }
    public function push(array $a, array $b): array
    {
        $token = SryInput::text($b, "token", 255);
        if (
            !preg_match(
                '/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$/',
                $token,
            )
        ) {
            throw new SryError("invalidInput");
        }
        $language = ($b["language"] ?? "cs") === "en" ? "en" : "cs";
        $this->notifications->registerDevice($token, $a["id"], $language);
        return ["updated" => true];
    }
    public function removePush(array $a, array $b): array
    {
        $this->notifications->removeDevice(
            SryInput::text($b, "token", 255),
            $a["id"],
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
        return $this->chat->forMember($a["family_id"], $a["id"], $a["id"]);
    }
    public function sendMessage(array $a, array $b): array
    {
        $to = $this->member($a, $this->integer($b, "recipient_id"));
        if ((int) $to["id"] === $a["id"]) {
            throw new SryError("invalidInput");
        }
        $text = SryInput::text($b, "body", 2000);
        return $this->family->transaction(function () use ($a, $to, $text) {
            $id = $this->chat->create([
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
