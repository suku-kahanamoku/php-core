<?php

declare(strict_types=1);

namespace App\Modules\Mailer;

use App\Modules\Templater\TemplaterService;

class MailerService
{
    private string $from;
    private string $fromName;
    private TemplaterService $tpl;

    public function __construct()
    {
        $this->from     = $_ENV['MAILER_FROM'] ?? 'noreply@example.com';
        $this->fromName = $_ENV['MAILER_FROM_NAME'] ?? 'App';
        $this->tpl      = new TemplaterService();
    }

    /**
     * Odesle HTML email jednomu nebo vice prijemcum.
     *
     * Pokud je $to pole, odesle kazde adrese samostatny email (prijemci
     * se navzajem nevidí). Pokud chcete skupinovy email (vsichni vidi
     * na ostatni), predejte adresy jako jeden retezec oddeleny carkou.
     *
     * @param  string|string[]      $to           Prijemce nebo seznam prijemcu
     * @param  string               $subject      Predmet emailu
     * @param  string               $template     Nazev sablony (napr. 'mail/test')
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
        $html       = $this->tpl->render($template, $templateData);
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
        $boundary = '----=_Part_' . md5(uniqid('', true));

        $headers = $this->buildHeaders($boundary, $bcc, $fromEmail, $fromName);

        $body = $this->buildBody($html, $attachments, $boundary);

        return mail($to, $subject, $body, $headers);
    }

    private function buildHeaders(
        string $boundary,
        string|array|null $bcc,
        string|null $fromEmail = null,
        string|null $fromName = null,
    ): string {
        $resolvedFrom     = $fromEmail ?? $this->from;
        $resolvedFromName = $fromName ?? $this->fromName;

        $lines = [
            'From: ' . $resolvedFromName . ' <' . $this->from . '>',
            'Reply-To: ' . $resolvedFrom,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'X-Mailer: php-core',
        ];

        if ($bcc !== null) {
            $bccList = is_array($bcc) ? implode(', ', $bcc) : $bcc;
            $lines[] = 'Bcc: ' . $bccList;
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param  string[] $attachments
     */
    private function buildBody(
        string $html,
        array $attachments,
        string $boundary
    ): string {
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";

        foreach ($attachments as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }
            $fileName  = basename($filePath);
            $fileData  = chunk_split(
                base64_encode((string) file_get_contents($filePath))
            );
            $mimeType  = mime_content_type($filePath) ?: 'application/octet-stream';

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$mimeType}; name=\"{$fileName}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $fileData . "\r\n";
        }

        $body .= "--{$boundary}--";

        return $body;
    }

    // ── Zkratka pro testovaci email ───────────────────────────────────────────

    public function sendTestMail(string $to): bool
    {
        return $this->sendMail(
            to: $to,
            subject: 'Test email',
            template: 'mail/test',
            templateData: ['email' => $to],
        );
    }
}
