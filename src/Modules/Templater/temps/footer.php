<div style="background-color:#1a2b3c;padding:24px 32px;text-align:center;">

    <?php if (empty($footerName)): ?>
        <img src="" alt="Logo"
            onerror="this.style.display='none'" style="height:36px;margin-bottom:8px;">
    <?php else: ?>
        <p style="margin:0 0 8px;font-size:16px;font-weight:bold;color:#ffffff;">
            <?= htmlspecialchars((string) $footerName) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($footerEmail)): ?>
        <p style="margin:4px 0;font-size:12px;color:#cfd7df;">
            <a href="mailto:<?= htmlspecialchars($footerEmail) ?>"
                style="color:#cfd7df;text-decoration:none;">
                <?= htmlspecialchars($footerEmail) ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if (!empty($footerPhone)): ?>
        <p style="margin:4px 0;font-size:12px;color:#cfd7df;">
            <a href="tel:<?= htmlspecialchars((string) preg_replace('/\s+/', '', $footerPhone)) ?>"
                style="color:#cfd7df;text-decoration:none;">
                <?= htmlspecialchars($footerPhone) ?>
            </a>
        </p>
    <?php endif; ?>
</div>
