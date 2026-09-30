<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/**
 * Uložený chat mezi členem a rodičem v rámci jedné rodiny.
 *
 * Každý dotaz omezuje `family_id`, takže zpráva jedné rodiny není čitelná
 * z jiné. Výpis vrací posledních 100 zpráv oběma směrů.
 */
final class ChatRepository extends SryRepository
{
    /**
     * Poslední zprávy mezi dvěma členy rodiny, od nejnovější.
     *
     * @param  int $familyId    ID rodiny (hranice okurku).
     * @param  int $senderId    ID člena, jehož zprávy se hledají.
     * @param  int $recipientId ID druhého účastníka.
     * @return list<array<string, mixed>> Nejnovější zprávy, max. 100.
     */
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
    /**
     * Vloží novou zprávu.
     *
     * @param  array<string, mixed> $data Zpráva včetně `family_id`, `sender_id`, `recipient_id` a `body`.
     * @return int                       ID vložené zprávy.
     */
    public function create(array $data): int
    {
        return $this->_db->insert("sry_chat", $data);
    }
}
