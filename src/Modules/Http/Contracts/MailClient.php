<?php

declare(strict_types=1);

namespace App\Modules\Http\Contracts;

interface MailClient
{
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
