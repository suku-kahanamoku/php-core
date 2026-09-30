<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/**
 * Nahraná média (fotografie a video) členů rodiny.
 *
 * Dotazy vždy omezují `family_id` a vlastníka, takže člen nečte cizí nahrávky.
 * Stav `ready` znamená, že je soubor nahrán a čitelný; dokud hotový není,
 * vracejí se pouze vlastník a rodič.
 */
final class MediaRepository extends SryRepository
{
    /**
     * Načte hotové vlastní medium.
     *
     * @param  int $id       ID média.
     * @param  int $familyId ID rodiny.
     * @param  int $memberId ID vlastníka.
     * @return array<string, mixed>|null Řádek média ve stavu `ready`, nebo null.
     */
    public function ownedReady(int $id, int $familyId, int $memberId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND member_id=? AND state='ready'",
            [$id, $familyId, $memberId],
        ) ?:
            null;
    }
    /**
     * Vloží záznam o právě nahrávaném souboru.
     *
     * @param  array<string, mixed> $data Atributy včetně `family_id`, `member_id` a `state`.
     * @return int                       ID vloženého záznamu.
     */
    public function create(array $data): int
    {
        return $this->_db->insert("sry_media", $data);
    }
    /**
     * Načte vlastní medium v jakémkoli stavu.
     *
     * @param  int $id       ID média.
     * @param  int $familyId ID rodiny.
     * @param  int $memberId ID vlastníka.
     * @return array<string, mixed>|null Řádek média, nebo null.
     */
    public function owned(int $id, int $familyId, int $memberId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND member_id=?",
            [$id, $familyId, $memberId],
        ) ?:
            null;
    }
    /**
     * Označí nahrávání jako dokončené.
     *
     * @param  int $id ID záznamu média.
     * @return void    Vedlejší efekt: přechod do stavu `ready`.
     */
    public function markReady(int $id): void
    {
        $this->_db->query("UPDATE sry_media SET state='ready' WHERE id=?", [
            $id,
        ]);
    }
    /**
     * Načte hotové medium v rodině (po kontrole vlastnictví).
     *
     * @param  int $id       ID média.
     * @param  int $familyId ID rodiny.
     * @return array<string, mixed>|null Řádek média ve stavu `ready`, nebo null.
     */
    public function ready(int $id, int $familyId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT * FROM sry_media WHERE id=? AND family_id=? AND state='ready'",
            [$id, $familyId],
        ) ?:
            null;
    }
    /**
     * Vrátí úkol, ke kterému je medium přiřazené, aby se zabránilo dvojnásobnému
     * přiřazení stejného souboru.
     *
     * @param  int $id       ID média.
     * @param  int $memberId ID člena, kterému je úkol přiřazen.
     * @param  int $familyId ID rodiny.
     * @return array<string, mixed>|null `{ task_id }`, nebo null.
     */
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
