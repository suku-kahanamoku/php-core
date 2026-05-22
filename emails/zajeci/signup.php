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
    <title>Potvrzení registrace</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Potvrzení registrace', 'logoPath' => $logoPath ?? '']) ?>

        <!-- CONTENT -->
        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Vážený zákazníku,</p>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Děkujeme Vám za registraci na naší platformě.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Vaše registrace byla úspěšně dokončena a Váš účet byl aktivován.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                Pokud máte jakékoli dotazy ohledně používání našich služeb nebo potřebujete pomoc, neváhejte nás kontaktovat. Jsme tu pro Vás.
            </p>
        </div>

        <?= $tpl->render('footer', [
            'footerName'  => $fromName ?? '',
            'footerEmail' => $fromEmail ?? '',
            'footerPhone' => $fromPhone ?? '',
            'logoPath'    => $logoPath ?? '',
        ]) ?>

    </div>

</body>

</html>