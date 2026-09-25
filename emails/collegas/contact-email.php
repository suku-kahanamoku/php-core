<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení přijetí kontaktu</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f2ed;font-family:Arial,sans-serif;color:#1f2925;">
    <div style="max-width:768px;margin:20px auto;background:#ffffff;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <?= $tpl->render('header', ['headerTitle' => 'Děkujeme za váš zájem', 'logoPath' => $logoPath ?? '']) ?>
        <div style="padding:32px;">
            <p style="font-size:16px;line-height:1.6;color:#1f2925;margin:0 0 16px;">Dobrý den,</p>
            <p style="font-size:14px;line-height:1.6;color:#555f5a;margin:0 0 16px;">děkujeme, že jste nám zanechali svůj e-mail. Váš kontakt jsme v pořádku přijali a brzy se vám ozveme.</p>
            <p style="font-size:14px;line-height:1.6;color:#555f5a;margin:0 0 24px;">Pokud chcete doplnit další informace, odpovězte přímo na tento e-mail.</p>
            <p style="font-size:14px;color:#1f2925;margin:0;">S pozdravem<br><strong>tým COLLEGAS</strong></p>
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
