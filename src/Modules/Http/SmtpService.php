<?php

declare(strict_types=1);

namespace App\Modules\Http;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP/native-mail transport omezený na jednoho tenanta (okruh).
 *
 * Konfigurace se načítá z prostředí s prefixem daného franchise kódu a s fallbackem
 * na globální `MAILER_*` proměnné. Instance se vytváří výhradně přes
 * `HttpModule::smtp($franchiseCode)`, aby credentials jednoho okruku nepronikly do
 * jiného. Implementuje `MailClient`, takže doménové služby neznají PHPMailer.
 */
final class SmtpService implements \App\Modules\Http\Contracts\MailClient
{
    private string $_from;
    private string $_fromName;
    private string $_smtpHost;
    private string $_smtpUser;
    private string $_smtpPass;
    private int    $_smtpPort;
    private bool   $_smtpAuth;
    private string $_smtpSecure;

    /**
     * Načte SMTP konfiguraci okurku z prostředí.
     *
     * @param  string          $franchiseCode Kód okurku; prázdný řetězec znamená pouze globální `MAILER_*` proměnné.
     * @param  \Closure|null   $messageFactory Testovací factory nahrazující PHPMailer.
     * @return void
     */
    public function __construct(string $franchiseCode = '', private readonly ?\Closure $messageFactory = null)
    {
        $prefix = $this->resolveMailerEnvPrefix($franchiseCode);

        $this->_from = $_ENV["{$prefix}MAILER_FROM"]
            ?? $_ENV['MAILER_FROM']
            ?? '';
        $this->_fromName = $_ENV["{$prefix}MAILER_FROM_NAME"]
            ?? $_ENV['MAILER_FROM_NAME']
            ?? '';
        $this->_smtpHost = $_ENV["{$prefix}MAILER_SMTP_HOST"]
            ?? $_ENV['MAILER_SMTP_HOST']
            ?? '';
        $this->_smtpUser = $_ENV["{$prefix}MAILER_SMTP_USER"]
            ?? $_ENV['MAILER_SMTP_USER']
            ?? $this->_from;
        $this->_smtpPass = $_ENV["{$prefix}MAILER_SMTP_PASS"]
            ?? $_ENV['MAILER_SMTP_PASS']
            ?? '';
        $this->_smtpPort = (int) ($_ENV["{$prefix}MAILER_SMTP_PORT"]
            ?? $_ENV['MAILER_SMTP_PORT']
            ?? 587);
        $smtpAuth = $_ENV["{$prefix}MAILER_SMTP_AUTH"]
            ?? $_ENV['MAILER_SMTP_AUTH']
            ?? null;
        $this->_smtpAuth = $smtpAuth === null
            ? $this->_smtpUser !== '' || $this->_smtpPass !== ''
            : filter_var($smtpAuth, FILTER_VALIDATE_BOOL);
        $this->_smtpSecure = strtolower(trim((string) (
            $_ENV["{$prefix}MAILER_SMTP_SECURE"]
            ?? $_ENV['MAILER_SMTP_SECURE']
            ?? 'tls'
        )));
    }

    /**
     * Sestaví prefix prostředí pro daný kód okurku.
     *
     * @param  string $franchiseCode Kód okurku, např. 'cz-shop' → 'CZ_SHOP_'.
     * @return string                Prefix bez podtržítka na konci, nebo prázdný řetězec pro globální konfiguraci.
     */
    private function resolveMailerEnvPrefix(string $franchiseCode): string
    {
        if ($franchiseCode === '') {
            return '';
        }

        return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($franchiseCode)), '_') . '_';
    }

    /**
     * Sestaví a odešle jeden HTML e-mail přes SMTP (nebo native mail, pokud host není nastaven).
     *
     * Chyby se nelouhou — zapisují se do logu a vrací se false, aby volající mohl
     * rozhodnout o retry nebo o fallbacku.
     *
     * @param  string            $to         Primární příjemce.
     * @param  string            $subject    Předmět zprávy.
     * @param  string            $html       Tělo zprávy v HTML.
     * @param  list<string>      $attachments Cesty k přílohám; neexistující soubory se přeskočí.
     * @param  string|array|null $bcc        Skrytí příjemci, nebo null.
     * @param  string|null       $fromEmail  E-mail pro odpověď; výchozí z konfigurace okurku.
     * @param  string|null       $fromName   Jméno pro odpověď; výchozí z konfigurace okurku.
     * @return bool                       true, pokud byla zpráva odeslána.
     */
    public function send(
        string $to,
        string $subject,
        string $html,
        array $attachments,
        string|array|null $bcc,
        string|null $fromEmail = null,
        string|null $fromName = null,
    ): bool {
        $mail = $this->messageFactory !== null ? ($this->messageFactory)() : new PHPMailer(true);

        try {
            $smtpHost = $this->_smtpHost;
            if ($smtpHost !== '') {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = $this->_smtpAuth;
                if ($this->_smtpAuth) {
                    $mail->Username = $this->_smtpUser;
                    $mail->Password = $this->_smtpPass;
                }
                if (in_array($this->_smtpSecure, ['ssl', 'smtps'], true)) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif (in_array($this->_smtpSecure, ['tls', 'starttls'], true)) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                $mail->Port       = $this->_smtpPort;
            } else {
                $mail->isMail();
            }

            $mail->setFrom($this->_from, $fromName ?? $this->_fromName);
            $mail->addReplyTo($fromEmail ?? $this->_from, $fromName ?? $this->_fromName);
            $mail->addAddress($to);

            if ($bcc !== null) {
                foreach ((array) $bcc as $bccAddr) {
                    $mail->addBCC($bccAddr);
                }
            }

            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                }
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $html;

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('MailerService: ' . $e->getMessage());
            return false;
        }
    }

}
