<?php

declare(strict_types=1);

namespace App\Modules\Mailer;

use App\Modules\Templater\TemplaterService;
use PHPMailer\PHPMailer\PHPMailer;

class MailerService
{
    private string $_from;
    private string $_fromName;
    private string $_smtpHost;
    private string $_smtpUser;
    private string $_smtpPass;
    private int    $_smtpPort;
    private TemplaterService $_tpl;

    public function __construct(string $franchiseCode = '')
    {
        $prefix = $franchiseCode !== '' ? strtoupper($franchiseCode) . '_' : '';

        $this->_from     = $_ENV["{$prefix}MAILER_FROM"]
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
        $this->_tpl      = new TemplaterService();
    }

    /**
     * Odesle HTML email jednomu nebo vice prijemcum.
     *
     * Pokud je $to pole, odesle kazde adrese samostatny email (prijemci
     * se navzajem nevidi). Pokud chcete skupinovy email (vsichni vidi
     * na ostatni), predejte adresy jako jeden retezec oddeleny carkou.
     *
     * @param  string|string[]      $to           Prijemce nebo seznam prijemcu
     * @param  string               $subject      Predmet emailu
     * @param  string               $template     Nazev sablony (napr. 'test')
     * @param  array<string, mixed> $templateData Promenne pro sablonu
     * @param  string[]             $attachments  Absolutni cesty k priloham
     * @param  string|string[]|null $bcc          Skryta kopie (nikdo ji nevidi)
     * @return bool                               True pokud vsechny emaily byly odeslany
     */
    public function sendMail(
        string|array $to,
        string $subject,
        string $template,
        array $templateData = [],
        array $attachments = [],
        string|array|null $bcc = null,
    ): bool {
        $html       = $this->_tpl->render($template, $templateData);
        $recipients = is_array($to) ? $to : [$to];
        $allSent    = true;

        foreach ($recipients as $recipient) {
            $sent    = $this->send(
                $recipient,
                $subject,
                $html,
                $attachments,
                $bcc,
                $templateData['fromEmail'] ?? null,
                $templateData['fromName'] ?? null
            );
            $allSent = $allSent && $sent;
        }

        return $allSent;
    }

    /**
     * Odesle jeden email na jednu adresu.
     *
     * @param  string               $to
     * @param  string               $subject
     * @param  string               $html
     * @param  string[]             $attachments
     * @param  string|string[]|null $bcc
     * @return bool
     */
    private function send(
        string $to,
        string $subject,
        string $html,
        array $attachments,
        string|array|null $bcc,
        string|null $fromEmail = null,
        string|null $fromName = null,
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $smtpHost = $this->_smtpHost;
            if ($smtpHost !== '') {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $this->_smtpUser;
                $mail->Password   = $this->_smtpPass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
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

    // ── Zkratka pro testovaci email ───────────────────────────────────────────

    public function sendTestMail(string $to): bool
    {
        return $this->sendMail(
            to: $to,
            subject: 'Test email',
            template: 'test',
            templateData: [
                'email'    => $to,
                'logoPath' => 'logo',
            ],
        );
    }
}
