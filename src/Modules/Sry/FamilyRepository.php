<?php
declare(strict_types=1);
namespace App\Modules\Sry;
final class FamilyRepository extends SryRepository
{
    public function timezone(int $familyId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT timezone FROM sry_family WHERE id=?",
            [$familyId],
        ) ?:
            null;
    }
    public function findMember(int $id, int $familyId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT id,family_id,name,role,daily_target,wifi_allowed,data_allowed FROM sry_member WHERE id=? AND family_id=? AND active=1",
            [$id, $familyId],
        ) ?:
            null;
    }
    public function members(int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT id,family_id,name,role,daily_target,wifi_allowed,data_allowed FROM sry_member WHERE family_id=? AND active=1 ORDER BY role,id",
            [$familyId],
        );
    }
    public function createMember(array $data): int
    {
        return $this->_db->insert("sry_member", $data);
    }
    public function updatePolicy(
        int $target,
        int $wifi,
        int $data,
        int $id,
        int $familyId,
    ): void {
        $this->_db->query(
            "UPDATE sry_member SET daily_target=?,wifi_allowed=?,data_allowed=? WHERE id=? AND family_id=?",
            [$target, $wifi, $data, $id, $familyId],
        );
    }
    public function parents(int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT id FROM sry_member WHERE family_id=? AND role='admin' AND active=1",
            [$familyId],
        );
    }
}
