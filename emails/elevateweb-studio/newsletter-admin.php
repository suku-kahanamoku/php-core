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
    <title>New newsletter subscription</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'New newsletter subscription', 'logoPath' => 'https://elevateweb-studio.com/assets/img/favicon.svg']) ?>

        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Hello,</p>

            <p style="font-size:14px;color:#555555;margin:0 0 16px;">
                A new subscriber has joined the newsletter.
            </p>

            <table style="width:100%;font-size:14px;color:#555555;margin:0 0 24px;border-collapse:collapse;">
                <tr>
                    <td style="padding:6px 0;font-weight:bold;width:120px;">Email:</td>
                    <td style="padding:6px 0;">
                        <a href="mailto:<?= htmlspecialchars((string) $email) ?>" style="color:#5b8dd9;text-decoration:none;">
                            <?= htmlspecialchars((string) $email) ?>
                        </a>
                    </td>
                </tr>
            </table>

            <p style="font-size:14px;color:#555555;margin:0 0 24px;">
                Please add this contact to the appropriate mailing list or CRM workflow.
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