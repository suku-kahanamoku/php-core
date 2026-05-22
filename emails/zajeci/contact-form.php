<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $msg */
?>

<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení přijetí vašeho e-mailu</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Potvrzení přijetí vašeho e-mailu', 'logoPath' => $logoPath ?? '']) ?>

        <!-- CONTENT -->
        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Vážený zákazníku,</p>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Děkujeme Vám za projevený zájem a odeslání zprávy.
            </p>

            <?php if (!empty($msg)): ?>
                <div style="background:#f8f9fa;border-left:4px solid #5b8dd9;padding:12px 16px;margin:0 0 16px;font-size:14px;color:#555555;">
                    <?= htmlspecialchars((string) $msg) ?>
                </div>
            <?php endif; ?>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Vaše zpráva byla úspěšně doručena našemu týmu. Naše oddělení zákaznické podpory se na ni podívá co nejdříve a pokusíme se odpovědět do 24 hodin (během pracovních dnů).
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                Pokud máte nějaké další dotazy, neváhejte nás kontaktovat.
            </p>

            <p style="font-size:14px;color:#333333;margin:0;">S pozdravem</p>
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