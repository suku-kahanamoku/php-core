<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/**
 * Rodina, členové a jejich denní limity.
 *
 * Každý dotaz je omezen rodinou, takže člen jedné rodiny nelze načíst přes jinou.
 * Role `admin` jsou rodiče.
 */
final class FamilyRepository extends SryRepository
{
    /**
     * Časové pásmo rodiny pro výpočet dne.
     *
     * @param  int $familyId ID rodiny.
     * @return array<string, mixed>|null `{ timezone }`, nebo null pokud rodina neexistuje.
     */
    public function timezone(int $familyId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT timezone FROM sry_family WHERE id=?",
            [$familyId],
        ) ?:
            null;
    }
    /**
     * Načte aktivního člena rodiny.
     *
     * @param  int $id       ID člena.
     * @param  int $familyId ID rodiny (musí souhlasit).
     * @return array<string, mixed>|null Relace člena, nebo null.
     */
    public function findMember(int $id, int $familyId): ?array
    {
        return $this->_db->fetchOne(
            "SELECT id,family_id,name,role,daily_target,wifi_allowed,data_allowed FROM sry_member WHERE id=? AND family_id=? AND active=1",
            [$id, $familyId],
        ) ?:
            null;
    }
    /**
     * Vypíše aktivní členy rodiny seřazené podle role a ID.
     *
     * @param  int $familyId ID rodiny.
     * @return list<array<string, mixed>> Relace členů.
     */
    public function members(int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT id,family_id,name,role,daily_target,wifi_allowed,data_allowed FROM sry_member WHERE family_id=? AND active=1 ORDER BY role,id",
            [$familyId],
        );
    }
    /**
     * Vloží nového člena rodiny.
     *
     * @param  array<string, mixed> $data Atributy člena včetně `family_id` a `role`.
     * @return int                       ID vloženého člena.
     */
    public function createMember(array $data): int
    {
        return $this->_db->insert("sry_member", $data);
    }
    /**
     * Uloží denní limity a oprávnění člena.
     *
     * @param  int $target   Denní limit v bodů.
     * @param  int $wifi     1, pokud je povoleno Wi-Fi.
     * @param  int $data     1, pokud je povoleno přenosování dat.
     * @param  int $id       ID člena.
     * @param  int $familyId ID rodiny (musí souhlasit).
     * @return void            Vedlejší efekt: `UPDATE sry_member`.
     */
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
    /**
     * Vypíše rodiče (členy s rolí `admin`) rodiny.
     *
     * @param  int $familyId ID rodiny.
     * @return list<array<string, mixed>> Seznam `{ id }` aktivních rodičů.
     */
    public function parents(int $familyId): array
    {
        return $this->_db->fetchAll(
            "SELECT id FROM sry_member WHERE family_id=? AND role='admin' AND active=1",
            [$familyId],
        );
    }
}
