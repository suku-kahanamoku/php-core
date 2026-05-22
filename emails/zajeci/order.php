<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $email */
/** @var string $orderId */
?>

<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení objednávky</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Potvrzení objednávky', 'logoPath' => $logoPath ?? '']) ?>

        <!-- CONTENT -->
        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Vážený zákazníku,</p>

            <?php if (!empty($orderId)): ?>
                <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                    Objednávka <strong><?= htmlspecialchars((string) $orderId) ?></strong> byla přijata.
                </p>
            <?php endif; ?>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Děkujeme Vám za projevený zájem. Vaše objednávka byla přijata.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Posíláme Vám fakturu v příloze. Věnujte prosím chvilku překontrolování všech údajů. Pokud by cokoliv nesouhlasilo, stačí nás kontaktovat na emailové adrese
                <?php if (!empty($fromEmail)): ?>
                    <a href="mailto:<?= htmlspecialchars((string) $fromEmail) ?>" style="color:#5b8dd9;text-decoration:none;"><?= htmlspecialchars((string) $fromEmail) ?></a>.
                <?php endif; ?>
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                Věříme, že Vám naše víno bude chutnat a že si u nás příště zase vyberete.
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