<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class SryAuth
{
    public function __construct(private SryStore $db) {}
    public static function text(
        array $body,
        string $key,
        int $max = 100,
        bool $required = true,
    ): string {
        $value = $body[$key] ?? "";
        if (!is_string($value)) {
            throw new SryError("invalidInput");
        }
        $value = trim($value);
        if (($required && $value === "") || mb_strlen($value) > $max) {
            throw new SryError("invalidInput");
        }
        return $value;
    }
    public static function password(array $body): string
    {
        $p = $body["password"] ?? null;
        if (!is_string($p) || strlen($p) < 10 || strlen($p) > 72) {
            throw new SryError("passwordInvalid");
        }
        return $p;
    }
    public static function email(array $body): string
    {
        $e = strtolower(self::text($body, "email", 254));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            throw new SryError("invalidInput");
        }
        return $e;
    }
    public function session(int $member): array
    {
        $token = bin2hex(random_bytes(32));
        $expires = gmdate("Y-m-d H:i:s", time() + 30 * 86400);
        $this->db->insert("sry_session", [
            "token_hash" => hash("sha256", $token),
            "member_id" => $member,
            "expires_at" => $expires,
        ]);
        return [
            "token" => $token,
            "expires_at" => str_replace(" ", "T", $expires) . "Z",
            "member" => $this->identity($token),
        ];
    }
    public function identity(string $token): array
    {
        $row = $this->db->one(
            "SELECT m.id,m.family_id,m.name,m.role FROM sry_session s JOIN sry_member m ON m.id=s.member_id JOIN sry_family f ON f.id=m.family_id JOIN user owner ON owner.id=f.owner_user_id LEFT JOIN user u ON u.id=m.user_id WHERE s.token_hash=? AND s.expires_at>? AND m.active=1 AND f.franchise_code='sry' AND owner.franchise_code='sry' AND owner.deleted=0 AND owner.status='active' AND (m.user_id IS NULL OR (u.franchise_code='sry' AND u.deleted=0 AND u.status='active'))",
            [hash("sha256", $token), gmdate("Y-m-d H:i:s")],
        );
        if (!$row) {
            throw new SryError("unauthorized", 401);
        }
        $row["id"] = (int) $row["id"];
        $row["family_id"] = (int) $row["family_id"];
        return $row;
    }
    public function login(array $body): array
    {
        $email = self::email($body);
        $password = $body["password"] ?? "";
        $user = $this->db->one(
            "SELECT u.password,m.id FROM user u JOIN sry_member m ON m.user_id=u.id WHERE u.franchise_code='sry' AND u.email=? AND u.deleted=0 AND u.status='active' AND m.active=1",
            [$email],
        );
        // Fixed-cost verification for unknown accounts too.
        $hash =
            $user["password"] ??
            '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (
            !is_string($password) ||
            !password_verify($password, $hash) ||
            !$user
        ) {
            throw new SryError("credentials", 401);
        }
        return $this->session((int) $user["id"]);
    }
    public function signup(array $body): array
    {
        $name = self::text($body, "name");
        $email = self::email($body);
        $password = self::password($body);
        return $this->db->transaction(function () use (
            $name,
            $email,
            $password,
        ) {
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
            $user = $this->db->insert("user", [
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
            $family = $this->db->insert("sry_family", [
                "owner_user_id" => $user,
            ]);
            $member = $this->db->insert("sry_member", [
                "family_id" => $family,
                "user_id" => $user,
                "name" => $name,
                "role" => "admin",
            ]);
            return $this->session($member);
        });
    }
    public function logout(string $token): void
    {
        $this->db->execute("DELETE FROM sry_session WHERE token_hash=?", [
            hash("sha256", $token),
        ]);
    }
    public function invitation(array $actor, array $body): array
    {
        if ($actor["role"] !== "admin") {
            throw new SryError("forbidden", 403);
        }
        $child = $body["child_id"] ?? null;
        if (
            $child !== null &&
            !$this->db->one(
                "SELECT id FROM sry_member WHERE id=? AND family_id=? AND role='user' AND active=1",
                [$child, $actor["family_id"]],
            )
        ) {
            throw new SryError("notFound", 404);
        }
        $token = bin2hex(random_bytes(32));
        $expires = gmdate("Y-m-d H:i:s", time() + 600);
        $this->db->insert("sry_invitation", [
            "token_hash" => hash("sha256", $token),
            "family_id" => $actor["family_id"],
            "child_id" => $child,
            "created_by" => $actor["id"],
            "expires_at" => $expires,
        ]);
        return [
            "token" => $token,
            "expires_at" => str_replace(" ", "T", $expires) . "Z",
        ];
    }
    public function join(array $body): array
    {
        $token = self::text($body, "token", 64);
        $name = self::text($body, "name");
        return $this->db->transaction(function () use ($token, $name) {
            $invite = $this->db->one(
                "SELECT i.* FROM sry_invitation i JOIN sry_family f ON f.id=i.family_id JOIN user u ON u.id=f.owner_user_id WHERE i.token_hash=? AND i.consumed_at IS NULL AND i.expires_at>? AND f.franchise_code='sry' AND u.deleted=0 AND u.status='active'" .
                    $this->db->lock(),
                [hash("sha256", $token), gmdate("Y-m-d H:i:s")],
            );
            if (!$invite) {
                throw new SryError("invitationInvalid", 410);
            }
            $child = $invite["child_id"];
            if (
                $child &&
                !$this->db->one(
                    "SELECT id FROM sry_member WHERE id=? AND family_id=? AND role='user' AND active=1",
                    [$child, $invite["family_id"]],
                )
            ) {
                throw new SryError("invitationInvalid", 410);
            }
            if (!$child) {
                $child = $this->db->insert("sry_member", [
                    "family_id" => $invite["family_id"],
                    "name" => $name,
                    "role" => "user",
                ]);
            }
            $this->db->execute(
                "UPDATE sry_invitation SET consumed_at=? WHERE token_hash=?",
                [gmdate("Y-m-d H:i:s"), hash("sha256", $token)],
            );
            $this->db->insert("sry_notification", [
                "member_id" => $invite["created_by"],
                "event" => "child_joined",
                "entity_id" => $child,
            ]);
            $this->db->insert("sry_outbox", [
                "member_id" => $invite["created_by"],
                "topic" => "family",
                "entity_id" => $child,
                "event" => "child_joined",
            ]);
            return $this->session((int) $child);
        });
    }
    public function reset(array $body, string $language, callable $send): array
    {
        $email = self::email($body);
        $user = $this->db->one(
            "SELECT id FROM user WHERE franchise_code='sry' AND email=? AND deleted=0 AND status='active'",
            [$email],
        );
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $this->db->insert("sry_password_reset", [
                "token_hash" => hash("sha256", $token),
                "user_id" => $user["id"],
                "expires_at" => gmdate("Y-m-d H:i:s", time() + 3600),
            ]);
            $send($email, $token, $language);
        }
        return ["requested" => true];
    }
    public function completeReset(array $body): array
    {
        $token = self::text($body, "token", 64);
        $password = self::password($body);
        return $this->db->transaction(function () use ($token, $password) {
            $r = $this->db->one(
                "SELECT r.* FROM sry_password_reset r JOIN user u ON u.id=r.user_id WHERE r.token_hash=? AND r.expires_at>? AND u.franchise_code='sry' AND u.deleted=0 AND u.status='active'" .
                    $this->db->lock(),
                [hash("sha256", $token), gmdate("Y-m-d H:i:s")],
            );
            if (!$r) {
                throw new SryError("invitationInvalid", 410);
            }
            $this->db->execute("UPDATE user SET password=? WHERE id=?", [
                password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]),
                $r["user_id"],
            ]);
            $this->db->execute(
                "DELETE FROM sry_password_reset WHERE user_id=?",
                [$r["user_id"]],
            );
            $this->db->execute(
                "DELETE FROM sry_session WHERE member_id IN (SELECT id FROM sry_member WHERE user_id=?)",
                [$r["user_id"]],
            );
            return ["updated" => true];
        });
    }
}
