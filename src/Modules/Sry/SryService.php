<?php

declare(strict_types=1);

namespace App\Modules\Sry;

/**
 * Doménová služba aplikace „Školní řad“ (Sry).
 *
 * Centrální místo obchodní logiky rodinného systému: členové rodiny, denní body
 * a oprávnění k Wi-Fi a mobilnímu datu, úkoly a jejich revize, mediální soubory
 * v cloudu, notifikace, realtime a chat. Veškeré vstupy validuje přes `SryInput`
 * a chyby signalizuje výjimkou `SryError` s odpovídajícím HTTP kódem.
 *
 * Data patří vždy jednomu okurku (`sry`); hranice rodiny (`family_id`) se
 * kontroluje u každého dotazu, aby člen rodiny neviděl cizí záznamy.
 */
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
    /**
     * @param \App\Modules\Database\Database $db   Připojení k databázi pro repozitáře modulu.
     * @param  CloudflareGateway                 $cloud Brána pro podepsané URL a hlavičky k uploadu/stahování souborů.
     * @return void
     */
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
    /**
     * Ověří, že aktér v kontextu má roli `admin`.
     *
     * @param  array<string, mixed> $a Kontext aktéra (`role`, `id`, `family_id`).
     * @return void                    Vedlejší efekt: bez role `admin` vyhodí `SryError` s kódem 403.
     * @throws SryError                'forbidden' (403), pokud aktér není administrátor.
     */
    private function admin(array $a): void
    {
        if ($a["role"] !== "admin") {
            throw new SryError("forbidden", 403);
        }
    }
    /**
     * Načte a zvaliduje celočíselný parametr z těla požadavku.
     *
     * @param  array<string, mixed> $b   Tělo požadavku.
     * @param  string                $key Klíč parametru.
     * @param  int                   $min Dolní mez včetně.
     * @param  int                   $max Horní mez včetně.
     * @return int                         Hodnota parametru.
     * @throws SryError                   'invalidInput' (422), pokud chybí, není celé číslo nebo je mimo rozsah.
     */
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
    /**
     * Vrátí aktuální kalendářní den v časovém pásu rodiny.
     *
     * @param  array<string, mixed> $a Kontext aktéra s `family_id`.
     * @return string                   Datum ve formátu `Y-m-d`.
     */
    public function today(array $a): string
    {
        $f = $this->family->timezone($a["family_id"]);
        return (new \DateTimeImmutable(
            "now",
            new \DateTimeZone($f["timezone"]),
        ))->format("Y-m-d");
    }
    /**
     * Načte člena rodiny a zkontroluje, že ho aktér smí vidět.
     *
     * Člen rodiny (`user`) smí číst pouze sebe, administrátor všechny členy.
     *
     * @param  array<string, mixed> $a  Kontext aktéra (`role`, `id`, `family_id`).
     * @param  int                   $id ID člena.
     * @return array<string, mixed>      Záznam člena.
     * @throws SryError                  'notFound' (404), pokud člen neexistuje nebo není viditelný.
     */
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
    /**
     * Vrátí členy rodiny s vypočteným stavem bodů a oprávnění k internetu.
     *
     * @param  array<string, mixed> $a Kontext aktéra (`role`, `id`, `family_id`).
     * @return array<string, mixed>     `{ members: [...], today: 'Y-m-d' }`; člen role `user` vidí jen sebe a administrátory.
     */
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
    /**
     * Přidá dítě do rodiny; volitelně mu založí i přihlašovací účet.
     *
     * Celá operace běží v jedné transakci, aby nevznikl člen bez účtu.
     *
     * @param  array<string, mixed> $a Kontext aktéra; musí mít roli `admin`.
     * @param  array<string, mixed> $b Tělo požadavku: `name` povinné, `email` a `password` volitelné.
     * @return array<string, mixed>     Vytvořený člen.
     * @throws SryError                 'forbidden' (403), 'invalidInput' (422), 'accountExists' (409)
     *                                  nebo 'notConfigured' (503), pokud chybí role `user`.
     */
    public function addChild(array $a, array $b): array
    {
        $this->admin($a);
        $name = SryInput::text($b, "name");
        $email = empty($b["email"]) ? null : SryInput::email($b);
        $password = $email ? SryInput::password($b) : null;
        return $this->family->transaction(
            /**
             * Tělo operace běží v jedné transakci nad databází okurku.
             *
             * @return mixed                    Výsledek, který transakce vrátí.
             * @throws SryError                 Chyba domény; transakce se vrátí zpět.
             * @throws \PDOException           Chyba databáze; transakce se vrátí zpět.
             */
            function () use (
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
    /**
     * Změní denní cíl bodů a oprávnění k Wi-Fi a datům daného dítěte.
     *
     * @param  array<string, mixed> $a  Kontext aktéra; musí mít roli `admin`.
     * @param  int                   $id  ID dítěte.
     * @param  array<string, mixed> $b  Tělo požadavku: `daily_target`, `wifi_allowed`, `data_allowed` (všechna tři povinná).
     * @return array<string, mixed>      Aktualizovaný člen.
     * @throws SryError                  'forbidden' (403) nebo 'invalidInput' (422).
     */
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
        return $this->family->transaction(
            /**
             * Tělo operace běží v jedné transakci nad databází okurku.
             *
             * @return mixed                    Výsledek, který transakce vrátí.
             * @throws SryError                 Chyba domény; transakce se vrátí zpět.
             * @throws \PDOException           Chyba databáze; transakce se vrátí zpět.
             */
            function () use (
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
    /**
     * Vrátí publikované kategorie a číselníky pro výběr v úlohách.
     *
     * @return array<string, mixed> `{ categories: [...], enumerations: [...] }`.
     */
    public function catalog(): array
    {
        return [
            "categories" => $this->published($this->categories),
            "enumerations" => $this->published($this->enumerations),
        ];
    }
    /**
     * Načte všechny publikované záznamy z libovolného repozitáře stránkovaně.
     *
     * @param  object $repository Repozitář s metodou `findAll()` (kategorie nebo číselníky).
     * @return list<array<string, mixed>>  Záznamy s `published = 1`.
     */
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
    /**
     * Vrátí úkoly viditelné aktérovi (člen vidí jen vlastní zadání).
     *
     * @param  array<string, mixed> $a Kontext aktéra (`role`, `id`, `family_id`).
     * @return list<array<string, mixed>>  Zadání s body, termínem a stavem.
     */
    public function tasks(array $a): array
    {
        return $this->tasks->forFamily(
            $a["family_id"],
            $a["role"] === "user" ? $a["id"] : null,
        );
    }
    /**
     * Načte zadání úkolu a zkontroluje oprávnění aktéra.
     *
     * @param  array<string, mixed> $a    Kontext aktéra (`role`, `id`, `family_id`).
     * @param  int                   $id    ID zadání.
     * @param  bool                  $lock  true uzamkne řádek proti souběžné změně (SELECT ... FOR UPDATE).
     * @return array<string, mixed>       Řádek zadání.
     * @throws SryError                   'notFound' (404), pokud zadání neexistuje nebo patří jinému členu.
     */
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
    /**
     * Vrátí detail zadání včetně odevzdání a přiložených médií.
     *
     * @param  array<string, mixed> $a Kontext aktéra.
     * @param  int                   $id ID zadání.
     * @return array<string, mixed>     Řádek zadání doplněný o `submissions` a `media_ids`.
     * @throws SryError                 'notFound' (404), pokud zadání není viditelné.
     */
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
    /**
     * Vytvoří úkol a rovnou ho přiřadí dítěti s termínem a bodovým ohodnocením.
     *
     * @param  array<string, mixed> $a Kontext aktéra; musí mít roli `admin`.
     * @param  array<string, mixed> $b Tělo požadavku: `member_id`, `title`, `due_date`, `points` povinné;
     *                                   volitelné `description`, `category_id`, `enumeration_id`, `media_ids` (max 5).
     * @return array<string, mixed>     Detail nového zadání.
     * @throws SryError                 'forbidden' (403), 'invalidInput' (422) nebo 'notFound' (404).
     */
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
        return $this->family->transaction(
            /**
             * Tělo operace běží v jedné transakci nad databází okurku.
             *
             * @return mixed                    Výsledek, který transakce vrátí.
             * @throws SryError                 Chyba domény; transakce se vrátí zpět.
             * @throws \PDOException           Chyba databáze; transakce se vrátí zpět.
             */
            function () use (
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
    /**
     * Odevzdá obrázek nebo video k úkolu.
     *
     * Odevzdání je možné až po termínu a jen v očekávané revizi zadání; součástí
     * je optimistická kontrola `revision` a stavu proti souběžným změnám.
     *
     * @param  array<string, mixed> $a Kontext aktéra; musí mít roli `user`.
     * @param  int                   $id ID zadání.
     * @param  array<string, mixed> $b Tělo požadavku: `media_id`, `revision` povinné, `note` volitelné.
     * @return array<string, mixed>     Aktualizovaný detail zadání.
     * @throws SryError                 'forbidden' (403), 'imageRequired' (422), 'conflict' (409),
     *                                  'notDue' (409) nebo 'notFound' (404).
     */
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
        return $this->family->transaction(
            /**
             * Tělo operace běží v jedné transakci nad databází okurku.
             *
             * @return mixed                    Výsledek, který transakce vrátí.
             * @throws SryError                 Chyba domény; transakce se vrátí zpět.
             * @throws \PDOException           Chyba databáze; transakce se vrátí zpět.
             */
            function () use (
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
    /**
     * Rodič schválí nebo vrátí odevzdání; schválení uděluje body za daný den.
     *
     * @param  array<string, mixed> $a Kontext aktéra; musí mít roli `admin`.
     * @param  int                   $id ID zadání.
     * @param  array<string, mixed> $b Tělo požadavku: `revision`, `decision` ('approved'|'returned') povinné,
     *                                   `note` povinné pouze při vrácení.
     * @return array<string, mixed>     Aktualizovaný detail zadání.
     * @throws SryError                 'forbidden' (403), 'invalidInput' (422) nebo 'conflict' (409).
     */
    public function review(array $a, int $id, array $b): array
    {
        $this->admin($a);
        $revision = $this->integer($b, "revision");
        $decision = $b["decision"] ?? "";
        if (!in_array($decision, ["approved", "returned"], true)) {
            throw new SryError("invalidInput");
        }
        $note = SryInput::text($b, "note", 2000, $decision === "returned");
        return $this->family->transaction(
            /**
             * Tělo operace běží v jedné transakci nad databází okurku.
             *
             * @return mixed                    Výsledek, který transakce vrátí.
             * @throws SryError                 Chyba domény; transakce se vrátí zpět.
             * @throws \PDOException           Chyba databáze; transakce se vrátí zpět.
             */
            function () use (
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
    /**
     * Vrátí ID administrátorů (rodičů) v dané rodině.
     *
     * @param  array<string, mixed> $a Kontext aktéra s `family_id`.
     * @return list<int>                 ID členů s rolí `admin`.
     */
    private function parents(array $a): array
    {
        return array_map(
            "intval",
            array_column($this->family->parents($a["family_id"]), "id"),
        );
    }
    /**
     * Načte hotové medium vlastněné konkrétním členem rodiny.
     *
     * @param  array<string, mixed> $a  Kontext aktéra (`id`, `family_id`).
     * @param  int                   $id  ID media.
     * @return array<string, mixed>      Záznam media.
     * @throws SryError                  'notFound' (404), pokud medium nepatří členu nebo ještě není připravené.
     */
    private function ownedMedia(array $a, int $id): array
    {
        $m = $this->media->ownedReady($id, $a["family_id"], $a["id"]);
        if (!$m) {
            throw new SryError("notFound", 404);
        }
        return $m;
    }
    /**
     * Vytvoří záznam media a vrátí podepsanou URL pro přímý upload do cloudu.
     *
     * @param  array<string, mixed> $a Kontext aktéra (`id`, `family_id`).
     * @param  array<string, mixed> $b Tělo požadavku: `mime` a `size` (1 B – 25 MB);
     *                                  povolené jsou jen JPEG, PNG, WebP, MP4 a povolené audio formáty.
     * @return array{id: int, url: string}  ID media a URL pro upload.
     * @throws SryError                     'invalidInput' (422), pokud typ nebo velikost nevyhovují.
     */
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
    /**
     * Ověří v cloudu nahrání media (velikost a MIME) a označí ho jako připravené.
     *
     * @param  array<string, mixed> $a  Kontext aktéra (`id`, `family_id`).
     * @param  int                   $id  ID media.
     * @return array{id: int}             Potvrzení `{ id }`.
     * @throws SryError                  'notFound' (404) nebo 'uploadFailed' (409), pokud metadata nesouhlasí.
     */
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
    /**
     * Vrátí podepsanou URL ke stažení media.
     *
     * Člen vidí vlastní media a media přiložená k úkolům, které mu byly zadány.
     *
     * @param  array<string, mixed> $a  Kontext aktéra (`role`, `id`, `family_id`).
     * @param  int                   $id  ID media.
     * @return array{url: string}        Podepsaná URL pro čtení.
     * @throws SryError                  'notFound' (404), pokud medium není připravené ani nepřidělené.
     */
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
    /**
     * Zapíše notifikaci a realtime událost pro příjemce.
     *
     * @param  array<string, mixed> $a          Kontext aktéra.
     * @param  string               $topic     Téma události ('family', 'tasks', 'chat').
     * @param  int                  $id        ID entity, ke které se událost vztahuje.
     * @param  string               $event     Název události.
     * @param  list<int>            $recipients ID členů, kterých se událost týká.
     * @param  bool                 $notify    false vytvoří jen realtime záznam, bez trvalé notifikace.
     * @return void                              Vedlejší efekt: zápis do tabulek notifikací a událostí.
     */
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
    /**
     * Vrátí vlastní notifikace člena.
     *
     * @param  array<string, mixed> $a Kontext aktéra s `id`.
     * @return list<array<string, mixed>>  Notifikace člena.
     */
    public function notifications(array $a): array
    {
        return $this->notifications->forMember($a["id"]);
    }
    /**
     * Označí notifikaci člena jako přečtenou.
     *
     * @param  array<string, mixed> $a  Kontext aktéra s `id`.
     * @param  int                   $id  ID notifikace.
     * @return array{updated: true}      Potvrzení `{ updated: true }`.
     */
    public function markRead(array $a, int $id): array
    {
        $this->notifications->markRead(gmdate("Y-m-d H:i:s"), $id, $a["id"]);
        return ["updated" => true];
    }
    /**
     * Zaregistruje push token zařízení pro daného člena.
     *
     * @param  array<string, mixed> $a Kontext aktéra s `id`.
     * @param  array<string, mixed> $b Tělo požadavku: `token` ve tvaru Expo/Exponent,
     *                                  volitelně `language` ('cs' nebo 'en').
     * @return array{updated: true}     Potvrzení `{ updated: true }`.
     * @throws SryError                 'invalidInput' (422), pokud token neodpovídá očekávanému formátu.
     */
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
    /**
     * Odebere push token zařízení člena.
     *
     * @param  array<string, mixed> $a Kontext aktéra s `id`.
     * @param  array<string, mixed> $b Tělo požadavku: `token`.
     * @return array{updated: true}     Potvrzení `{ updated: true }`.
     * @throws SryError                 'invalidInput' (422), pokud chybí token.
     */
    public function removePush(array $a, array $b): array
    {
        $this->notifications->removeDevice(
            SryInput::text($b, "token", 255),
            $a["id"],
        );
        return ["updated" => true];
    }
    /**
     * Vrátí podepsanou WebSocket URL pro realtime kanál člena.
     *
     * @param  array<string, mixed> $a  Kontext aktéra (`id`, `family_id`).
     * @return array{url: string}        WebSocket URL (HTTPS se přepisuje na WSS).
     */
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
    /**
     * Vrátí konverzaci aktuálního člena s jeho rodiči.
     *
     * @param  array<string, mixed> $a Kontext aktéra (`id`, `family_id`).
     * @return list<array<string, mixed>>  Zprávy konverzace.
     */
    public function messages(array $a): array
    {
        return $this->chat->forMember($a["family_id"], $a["id"], $a["id"]);
    }
    /**
     * Odešle soukromou zprávu jinému členu rodiny.
     *
     * @param  array<string, mixed> $a Kontext aktéra (`id`, `family_id`).
     * @param  array<string, mixed> $b Tělo požadavku: `recipient_id` a `body` (max 2000 znaků) povinné.
     * @return array{id: int}           ID vytvořené zprávy.
     * @throws SryError                 'invalidInput' (422) při chybějícím příjemci či textu nebo při pokusu psát sobě,
     *                                  'notFound' (404) při neviditelném příjemci.
     */
    public function sendMessage(array $a, array $b): array
    {
        $to = $this->member($a, $this->integer($b, "recipient_id"));
        if ((int) $to["id"] === $a["id"]) {
            throw new SryError("invalidInput");
        }
        $text = SryInput::text($b, "body", 2000);
        return $this->family->transaction(
            /**
             * Tělo operace běží v jedné transakci nad databází okurku.
             *
             * @return mixed                    Výsledek, který transakce vrátí.
             * @throws SryError                 Chyba domény; transakce se vrátí zpět.
             * @throws \PDOException           Chyba databáze; transakce se vrátí zpět.
             */
            function () use ($a, $to, $text) {
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
