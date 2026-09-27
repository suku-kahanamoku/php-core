<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class MediaRepository extends SryRepository
{
    public function ownedReady(int $id, int $familyId, int $memberId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND member_id=? AND state='ready'",
            [$id, $familyId, $memberId],
        ) ?:
            null;
    }
    public function create(array $data): int
    {
        return $this->_db->insert("sry_media", $data);
    }
    public function owned(int $id, int $familyId, int $memberId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND member_id=?",
            [$id, $familyId, $memberId],
        ) ?:
            null;
    }
    public function markReady(int $id): void
    {
        $this->_db->query("UPDATE sry_media SET state='ready' WHERE id=?", [
            $id,
        ]);
    }
    public function ready(int $id, int $familyId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND state='ready'",
            [$id, $familyId],
        ) ?:
            null;
    }
    public function assignedReference(
        int $id,
        int $memberId,
        int $familyId,
    ): ?array {
        return $this->_db->fetchOne(
            "SELECT tm.task_id FROM task_media tm JOIN task_assignment a ON a.task_id=tm.task_id AND a.family_id=tm.family_id WHERE tm.media_id=? AND a.member_id=? AND a.family_id=?",
            [$id, $memberId, $familyId],
        ) ?:
            null;
    }
}
