<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $email */
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nový kontakt z webu COLLEGAS</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f2ed;font-family:Arial,sans-serif;color:#1f2925;">
    <div style="max-width:768px;margin:20px auto;background:#ffffff;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <?= $tpl->render('header', ['headerTitle' => 'Nový kontakt z webu COLLEGAS', 'logoPath' => $logoPath ?? '']) ?>
        <div style="padding:32px;">
            <p style="font-size:14px;line-height:1.6;color:#555f5a;margin:0 0 16px;">Návštěvník webu požádal o kontaktování.</p>
            <table style="width:100%;font-size:14px;color:#555f5a;margin:0 0 24px;border-collapse:collapse;">
                <tr>
                    <td style="padding:8px 0;font-weight:bold;width:120px;">E-mail:</td>
                    <td style="padding:8px 0;"><a href="mailto:<?= htmlspecialchars((string) ($email ?? '')) ?>" style="color:#745b26;text-decoration:none;"><?= htmlspecialchars((string) ($email ?? '')) ?></a></td>
                </tr>
            </table>
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
