<?php
$en = ($language ?? "cs") === "en"; ?>
<!doctype html><html lang="<?= $en
    ? "en"
    : "cs" ?>"><meta charset="utf-8"><body style="font-family:sans-serif;color:#16233a;background:#fcf9f4;padding:32px">
<h1 style="color:#dc342c">sorry–jako.</h1>
<p><?= $en
    ? "Use this one-time link to reset your password. It expires in one hour."
    : "Tímto jednorázovým odkazem obnovíš heslo. Platí jednu hodinu." ?></p>
<p><a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, "UTF-8") ?>"><?= $en
    ? "Reset password"
    : "Obnovit heslo" ?></a></p>
<p><?= $en
    ? "If you did not request this change, you can ignore this email."
    : "Pokud jsi o změnu nežádal/a, tento e-mail můžeš ignorovat." ?></p>
</body></html>
