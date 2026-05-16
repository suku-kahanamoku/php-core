<?php

/** @var \App\Modules\Templater\TemplaterService $tpl */
?>

<!DOCTYPE html>
<html lang="cs">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test email</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;color:#333333;">

  <div style="max-width:768px;margin:20px auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

    <?= $tpl->render('header', ['headerTitle' => 'Test email']) ?>

    <!-- CONTENT -->
    <div style="padding:32px;">
      <div style="font-size:16px;font-weight:bold;color:#1a2b3c;padding-bottom:8px;">Ahoj,</div>
      <div style="font-size:14px;color:#555555;">
        <p>Toto je testovaci email odeslany na adresu
          <a href="mailto:<?= htmlspecialchars($email ?? '') ?>" style="color:#5b8dd9;text-decoration:none;"><?= htmlspecialchars($email ?? '') ?></a>.
        </p>
        <p>Pokud jste tento email neocekavali, muzete jej ignorovat.</p>
      </div>
    </div>

    <?= $tpl->render('footer', [
      'footerEmail' => $_ENV['MAILER_FROM'] ?? '',
      'footerPhone' => '',
    ]) ?>

  </div>

</body>

</html>
