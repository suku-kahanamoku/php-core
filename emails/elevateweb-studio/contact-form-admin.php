<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
/** @var string $logoPath */
/** @var string $fromEmail */
/** @var string $fromName */
/** @var string $fromPhone */
/** @var string $name */
/** @var string $email */
/** @var string $project */
/** @var string $msg */
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New message from contact form</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

    <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <?= $tpl->render('header', ['headerTitle' => 'New message from contact form', 'logoPath' => 'https://elevateweb-studio.com/assets/img/favicon.svg']) ?>

        <div style="padding:32px;">
            <p style="font-size:16px;color:#333333;margin:0 0 16px;">Hello,</p>
            <p style="font-size:14px;color:#555555;margin:0 0 16px;">A new message has been received from the contact form.</p>

            <table style="width:100%;font-size:14px;color:#555555;margin:0 0 16px;border-collapse:collapse;">
                <?php if (!empty($name)): ?>
                    <tr>
                        <td style="padding:6px 0;font-weight:bold;width:120px;">Name:</td>
                        <td style="padding:6px 0;"><?= htmlspecialchars((string) $name) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($email)): ?>
                    <tr>
                        <td style="padding:6px 0;font-weight:bold;">Email:</td>
                        <td style="padding:6px 0;"><a href="mailto:<?= htmlspecialchars((string) $email) ?>" style="color:#5b8dd9;text-decoration:none;"><?= htmlspecialchars((string) $email) ?></a></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($project)): ?>
                    <tr>
                        <td style="padding:6px 0;font-weight:bold;">Project:</td>
                        <td style="padding:6px 0;"><?= htmlspecialchars((string) $project) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($phone)): ?>
                    <tr>
                        <td style="padding:6px 0;font-weight:bold;">Phone:</td>
                        <td style="padding:6px 0;"><?= htmlspecialchars((string) $phone) ?></td>
                    </tr>
                <?php endif; ?>
            </table>

            <?php if (!empty($msg)): ?>
                <div style="background:#f8f9fa;border-left:4px solid #5b8dd9;padding:16px;margin:0 0 24px;font-size:14px;color:#555555;">
                    <?= nl2br(htmlspecialchars((string) $msg)) ?>
                </div>
            <?php endif; ?>

            <p style="font-size:14px;color:#555555;margin:0 0 24px;">The sender's contact details are listed above. Please reply as soon as possible.</p>
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