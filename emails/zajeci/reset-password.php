<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $email */
/** @var string $password */
?>

<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset hesla</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Reset hesla', 'logoPath' => $logoPath ?? '']) ?>

        <!-- CONTENT -->
        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Vážený zákazníku,</p>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Obdrželi jsme žádost o resetování hesla k vašemu účtu.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Bylo vygenerováno nové heslo pro váš účet.
            </p>

            <table style="width:100%;font-size:14px;color:#555555;margin:0 0 16px;border-collapse:collapse;">
                <?php if (!empty($email)): ?>
                    <tr>
                        <td style="padding:6px 0;font-weight:bold;width:120px;">E-mail:</td>
                        <td style="padding:6px 0;"><?= htmlspecialchars((string) $email) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($password)): ?>
                    <tr>
                        <td style="padding:6px 0;font-weight:bold;">Heslo:</td>
                        <td style="padding:6px 0;font-family:monospace;font-size:15px;letter-spacing:1px;"><?= htmlspecialchars((string) $password) ?></td>
                    </tr>
                <?php endif; ?>
            </table>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Pokud jste o nové heslo nežádal/a, kontaktujte nás okamžitě.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                Pokud máte nějaké další otázky nebo potřebujete pomoc, neváhejte nás kontaktovat.
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