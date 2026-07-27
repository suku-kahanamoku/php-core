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
    <title>Potvrzení odběru newsletteru</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Potvrzení odběru newsletteru', 'logoPath' => $logoPath ?? '']) ?>

        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Vážený odběrateli,</p>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Děkujeme za přihlášení k našemu newsletteru.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Na adresu <strong><?= htmlspecialchars((string) $email) ?></strong> Vám budeme zasílat novinky, inspiraci a důležité informace.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                Pokud jste se nepřihlásili Vy, můžete tento e-mail bez obav ignorovat.
            </p>

            <p style="font-size:14px;color:#333333;margin:0;">S pozdravem</p>
        </div>

        <?= $tpl->render('footer', [
            'footerName'  => $fromName  ?? '',
            'footerEmail' => $fromEmail ?? '',
            'footerPhone' => $fromPhone ?? '',
            'logoPath'    => $logoPath  ?? '',
        ]) ?>

    </div>

</body>

</html>