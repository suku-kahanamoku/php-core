<?php

declare(strict_types=1);

namespace App\Modules\Http;

use PHPMailer\PHPMailer\PHPMailer;

/** SMTP/native-mail transport, scoped to one tenant. */
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

    private function resolveMailerEnvPrefix(string $franchiseCode): string
    {
        if ($franchiseCode === '') {
            return '';
        }

        return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($franchiseCode)), '_') . '_';
    }

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
