<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $name */
/** @var string $email */
/** @var string $phone */
/** @var string $interest */
/** @var string $message */
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nová žádost o konzultaci</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f2ed;font-family:Arial,sans-serif;color:#1f2925;">
    <div style="max-width:768px;margin:20px auto;background:#ffffff;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <?= $tpl->render('header', ['headerTitle' => 'Nová žádost o konzultaci', 'logoPath' => $logoPath ?? '']) ?>
        <div style="padding:32px;">
            <table style="width:100%;font-size:14px;color:#555f5a;margin:0 0 24px;border-collapse:collapse;">
                <tr><td style="padding:8px 0;font-weight:bold;width:140px;">Jméno:</td><td style="padding:8px 0;"><?= htmlspecialchars((string) ($name ?? '')) ?></td></tr>
                <tr><td style="padding:8px 0;font-weight:bold;">E-mail:</td><td style="padding:8px 0;"><a href="mailto:<?= htmlspecialchars((string) ($email ?? '')) ?>" style="color:#745b26;text-decoration:none;"><?= htmlspecialchars((string) ($email ?? '')) ?></a></td></tr>
                <tr><td style="padding:8px 0;font-weight:bold;">Telefon:</td><td style="padding:8px 0;"><?= htmlspecialchars((string) ($phone ?? '')) ?></td></tr>
                <tr><td style="padding:8px 0;font-weight:bold;">Oblast zájmu:</td><td style="padding:8px 0;"><?= htmlspecialchars((string) ($interest ?? '')) ?></td></tr>
            </table>
            <?php if (!empty($message)): ?>
                <div style="background:#f4f2ed;border-left:4px solid #b38b45;padding:16px;margin:0 0 24px;font-size:14px;line-height:1.6;color:#555f5a;"><?= nl2br(htmlspecialchars((string) $message)) ?></div>
            <?php endif; ?>
            <p style="font-size:14px;color:#1f2925;margin:0;">Zpráva byla vytvořena formulářem na webu COLLEGAS.</p>
        </div>
        <?= $tpl->render('footer', [
            'footerName' => $fromName ?? '',
            'footerEmail' => $fromEmail ?? '',
            'footerPhone' => $fromPhone ?? '',
            'logoPath' => $logoPath ?? '',
        ]) ?>
    </div>
</body>
</html>
