<?php
declare(strict_types=1);
namespace App\Modules\Sry;
/**
 * Notifikace členů a outbox pro push.
 *
 * Dotazy jsou omezené na `member_id`, takže si člen nezobrazí cizí notifikace.
 * Push se odesílá až po uložení notifikace (outbox), aby se zpráva neztratila při
 * výpadku doručovací služby.
 */
final class NotificationRepository extends SryRepository
{
    /**
     * Vloží notifikaci pro člena.
     *
     * @param  array<string, mixed> $data Atributy včetně `member_id`, `event` a `entity_id`.
     * @return int                       ID vložené notifikace.
     */
    public function create(array $data): int
    {
        return $this->_db->insert("sry_notification", $data);
    }
    /**
     * Zařadí push zprávu do outboxu k pozdějšímu odeslání.
     *
     * @param  array<string, mixed> $data Atributy zprávy včetně `member_id`.
     * @return int                       ID záznamu v outboxu.
     */
    public function enqueue(array $data): int
    {
        return $this->_db->insert("sry_outbox", $data);
    }
    /**
     * Poslední notifikace člena, od nejnovější.
     *
     * @param  int $memberId ID člena.
     * @return list<array<string, mixed>> Nejnovější notifikace, max. 100.
     */
    public function forMember(int $memberId): array
    {
        return $this->_db->fetchAll(
            "SELECT id,event,entity_id,read_at,created_at FROM sry_notification WHERE member_id=? ORDER BY id DESC LIMIT 100",
            [$memberId],
        );
    }
    /**
     * Označí notifikaci člena jako přečtenou.
     *
     * @param  string $date    Čas přečtení ve formátu `Y-m-d H:i:s`.
     * @param  int    $id      ID notifikace.
     * @param  int    $memberId ID člena (musí souhlasit).
     * @return void             Vedlejší efekt: zápis `read_at`.
     */
    public function markRead(string $date, int $id, int $memberId): void
    {
        $this->_db->query(
            "UPDATE sry_notification SET read_at=? WHERE id=? AND member_id=?",
            [$date, $id, $memberId],
        );
    }
    /**
     * Odebere konkrétní push zařízení člena.
     *
     * @param  string $token   Token zařízení.
     * @param  int    $memberId ID člena, kterému zařízení patří.
     * @return void             Vedlejší efekt: `DELETE sry_push_device`.
     */
    public function removeDevice(string $token, int $memberId): void
    {
        $this->_db->query(
            "DELETE FROM sry_push_device WHERE token=? AND member_id=?",
            [$token, $memberId],
        );
    }
    /**
     * Zaregistruje nebo aktualizuje push zařízení člena včetně jazyka notifikací.
     *
     * @param  string $token    Token zařízení.
     * @param  int    $memberId ID člena.
     * @param  string $language Jazyk notifikací.
     * @return void             Vedlejší efekt: vložení nebo aktualizace `sry_push_device`.
     */
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
    /**
     * Odebere všechna push zařízení člena (například při odhlášení).
     *
     * @param  int $memberId ID člena.
     * @return void           Vedlejší efekt: `DELETE sry_push_device`.
     */
    public function removeMemberDevices(int $memberId): void
    {
        $this->_db->delete("sry_push_device", "member_id=?", [$memberId]);
    }
}
