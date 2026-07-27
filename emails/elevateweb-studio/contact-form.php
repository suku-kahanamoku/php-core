<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $name */
/** @var string $project */
/** @var string $msg */
?>

<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení přijetí vaší zprávy</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Potvrzení přijetí vaší zprávy', 'logoPath' => 'https://elevateweb-studio.com/assets/img/favicon.svg']) ?>

        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Dobrý den <?= htmlspecialchars((string) $name) ?>,</p>
            <p style="font-size:14px;color:#555555;margin:0 0 16px;">děkujeme, že jste nám napsal/a ohledně projektu <strong><?= htmlspecialchars((string) $project) ?></strong>.</p>

            <?php if (!empty($msg)): ?>
                <div style="background:#f8f9fa;border-left:4px solid #5b8dd9;padding:16px;margin:0 0 24px;font-size:14px;color:#555555;">
                    <?= nl2br(htmlspecialchars((string) $msg)) ?>
                </div>
            <?php endif; ?>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">Vaše zpráva byla úspěšně doručena našemu týmu. Odpovíme co nejdříve.</p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">Pokud budete potřebovat cokoliv dalšího, odpovězte prosím na tento e-mail.</p>
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