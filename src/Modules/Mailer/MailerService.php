<?php

declare(strict_types=1);

namespace App\Modules\Mailer;

use App\Modules\Templater\TemplaterService;

class MailerService
{
    private TemplaterService $_tpl;
    private \App\Modules\Http\Contracts\MailClient $smtp;

    public function __construct(string $franchiseCode = '', ?\App\Modules\Http\Contracts\MailClient $smtp = null)
    {
        $this->_tpl = new TemplaterService($franchiseCode);
        $this->smtp = $smtp ?? \App\Modules\Http\HttpModule::smtp($franchiseCode);
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
            $sent = $this->smtp->send(
                $recipient,
                $subject,
                $html,
                $attachments,
                $bcc,
                $templateData['fromEmail'] ?? null,
                $templateData['fromName']  ?? null,
            );
            $allSent = $allSent && $sent;
        }

        return $allSent;
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
