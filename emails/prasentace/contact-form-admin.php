<?php

/** Prasentace enquiry notification. All visitor and configuration values are escaped. */
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="utf-8">
    <title>Nová poptávka</title>
</head>

<body style="margin:0;background:#f8faff;font-family:Arial,sans-serif;color:#182239;">
    <div style="max-width:680px;margin:24px auto;padding:32px;background:#fff;border-radius:16px;">
        <p style="font-size:26px;font-weight:bold;margin:0 0 28px;"><?= $escape($fromName ?? 'Prasentace') ?></p>
        <h1 style="font-size:22px;">Nová poptávka z webu</h1>
        <table style="font-size:16px;line-height:1.8;">
            <tr>
                <th style="text-align:left;padding-right:20px;">Jméno</th>
                <td><?= $escape($name ?? '') ?></td>
            </tr>
            <tr>
                <th style="text-align:left;padding-right:20px;">E-mail</th>
                <td><?= $escape($email ?? '') ?></td>
            </tr>
            <tr>
                <th style="text-align:left;padding-right:20px;">Oblast zájmu</th>
                <td><?= $escape($interest ?? '') ?></td>
            </tr>
        </table>
        <div style="margin:24px 0;padding:20px;background:#f8faff;font-size:16px;line-height:1.7;"><?= nl2br($escape($message ?? '')) ?></div>
        <p style="font-size:14px;color:#48566d;"><?= $escape($fromName ?? 'Prasentace') ?> · <?= $escape($fromEmail ?? '') ?></p>
    </div>
</body>

</html>