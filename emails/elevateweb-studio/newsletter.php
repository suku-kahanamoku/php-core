<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $email */
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter subscription confirmation</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'Newsletter subscription confirmation', 'logoPath' => 'https://elevateweb-studio.com/assets/img/favicon.svg']) ?>

        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Hello,</p>

            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                Thank you for subscribing to our newsletter.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 12px;">
                We will send updates, insights, and relevant news to <strong><?= htmlspecialchars((string) $email) ?></strong>.
            </p>
            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                If you did not sign up, you can safely ignore this email.
            </p>

            <p style="font-size:14px;color:#333333;margin:0;">Best regards,</p>
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