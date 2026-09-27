<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class ChatRepository extends SryRepository
{
    public function forMember(
        int $familyId,
        int $senderId,
        int $recipientId,
    ): array {
        return $this->_db->fetchAll(
            "SELECT id,sender_id,recipient_id,body,created_at FROM sry_chat WHERE family_id=? AND (sender_id=? OR recipient_id=?) ORDER BY id DESC LIMIT 100",
            [$familyId, $senderId, $recipientId],
        );
    }
    public function create(array $data): int
    {
        return $this->_db->insert("sry_chat", $data);
    }
}
