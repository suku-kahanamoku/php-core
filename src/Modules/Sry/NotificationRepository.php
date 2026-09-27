<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class NotificationRepository extends SryRepository
{
    public function create(array $data): int
    {
        return $this->_db->insert("sry_notification", $data);
    }
    public function enqueue(array $data): int
    {
        return $this->_db->insert("sry_outbox", $data);
    }
    public function forMember(int $memberId): array
    {
        return $this->_db->fetchAll(
            "SELECT id,event,entity_id,read_at,created_at FROM sry_notification WHERE member_id=? ORDER BY id DESC LIMIT 100",
            [$memberId],
        );
    }
    public function markRead(string $date, int $id, int $memberId): void
    {
        $this->_db->query(
            "UPDATE sry_notification SET read_at=? WHERE id=? AND member_id=?",
            [$date, $id, $memberId],
        );
    }
    public function removeDevice(string $token, int $memberId): void
    {
        $this->_db->query(
            "DELETE FROM sry_push_device WHERE token=? AND member_id=?",
            [$token, $memberId],
        );
    }
    public function registerDevice(
        string $token,
        int $memberId,
        string $language,
    ): void {
        $this->_db->query(
            "INSERT INTO sry_push_device(token,member_id,language) VALUES(?,?,?) ON DUPLICATE KEY UPDATE member_id=VALUES(member_id),language=VALUES(language)",
            [$token, $memberId, $language],
        );
    }
    public function removeMemberDevices(int $memberId): void
    {
        $this->_db->delete("sry_push_device", "member_id=?", [$memberId]);
    }
}
