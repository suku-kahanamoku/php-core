<?php

declare(strict_types=1);

namespace App\Modules\Http\Contracts;

/**
 * Kontrakt odchozího odesílání e-mailů.
 *
 * Implementaci poskytuje `HttpModule::smtp()` (modul Http); doménové služby
 * dostávají pouze tuto abstrakci, aby neznaly PHPMailer ani SMTP konfiguraci.
 */
interface MailClient
{
    /**
     * Odešle jeden HTML e-mail.
     *
     * @param  string            $to           Primární příjemce.
     * @param  string            $subject      Předmět zprávy.
     * @param  string            $html         Tělo zprávy v HTML.
     * @param  list<string>      $attachments  Cesty k přílohám; neexistující soubory se přeskočí.
     * @param  string|array|null $bcc          Skrytí příjemci (jeden e-mail nebo seznam), nebo null.
     * @param  string|null       $fromEmail    E-mail pro odpověď; výchozí hodnota z konfigurace okruku.
     * @param  string|null       $fromName     Jméno pro odpověď; výchozí hodnota z konfigurace okruku.
     * @return bool              true, pokud byla zpráva předána odesílači.
     */
    public function send(
        string $to,
        string $subject,
        string $html,
        array $attachments,
        string|array|null $bcc,
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): bool;
}
