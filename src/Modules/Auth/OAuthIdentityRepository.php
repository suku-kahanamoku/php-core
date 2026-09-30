<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Database\Database;

/**
 * Repozitář pro vazbu externího OAuth identifikátoru na účty v rámci okurku.
 *
 * Vazba je vždy omezena na `franchiseCode` z konstruktoru, takže identifikátor
 * z jiného okurku nelze použít k přihlášení.
 */
final class OAuthIdentityRepository
{
    /**
     * @param  Database $db            Databazove pripojeni.
     * @param  string   $franchiseCode Kod okurku, kterymu vazby patri.
     * @return void
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $franchiseCode,
    ) {}

    /**
     * Nalezne ID uživatele podle provideru a subjectu z tokenu.
     *
     * @param  string $provider Název poskytovatele (např. 'google').
     * @param  string $subject  Stabilní identifikátor uživatele u poskytovatele.
     * @return int|null         ID uživatele, nebo null pokud vazba neexistuje.
     */
    public function findUserId(string $provider, string $subject): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT user_id FROM oauth_identity WHERE franchise_code = ? AND provider = ? AND provider_subject = ?',
            [$this->franchiseCode, $provider, $subject],
        );
        return $row ? (int) $row['user_id'] : null;
    }

    /**
     * Uloží novou vazbu OAuth na uživatele.
     *
     * @param  int    $userId   ID uživatele.
     * @param  string $provider Název poskytovatele.
     * @param  string $subject  Stabilní identifikátor u poskytovatele.
     * @param  string $email    E-mail z profilu poskytovatele (informativni).
     * @return void             Vedlejší efekt: vložení řádku do `oauth_identity`.
     */
    public function create(int $userId, string $provider, string $subject, string $email): void
    {
        $this->db->insert('oauth_identity', [
            'franchise_code' => $this->franchiseCode, 'user_id' => $userId,
            'provider' => $provider, 'provider_subject' => $subject, 'email' => $email,
        ]);
    }
}
