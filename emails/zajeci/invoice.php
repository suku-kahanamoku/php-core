<?php

/**
 * Sablona faktury pro Dompdf — emails/zajeci/invoice.php
 *
 * Dostupne promenne (injektovane z InvoicePdfService::_renderTemplate()):
 *   @var array<string, mixed>      $data         Kompletni faktura z findById()
 *   @var array<string, mixed>|null $bankDetails  Bankovni data z enum (iban, swift, account)
 *   @var string|null               $qrCode       Base64 PNG QR kodu (SPAYD)
 */

$user            = is_array($data['user'] ?? null) ? $data['user'] : [];
$billing         = is_array($data['billing_address'] ?? null) ? $data['billing_address'] : [];
$shipping        = is_array($data['shipping_address'] ?? null) ? $data['shipping_address'] : [];
$items           = $data['items'] ?? [];
$invoiceNumber   = str_replace('-', '', $data['invoice_number'] ?? '');
$issuedAt        = isset($data['issued_at']) ? date('d.m.Y', strtotime($data['issued_at'])) : '';
$dueAt           = isset($data['due_at']) ? date('d.m.Y', strtotime($data['due_at'])) : '';
$paidAt          = isset($data['paid_at']) && $data['paid_at'] ? date('d.m.Y', strtotime($data['paid_at'])) : null;
$currency        = $data['currency'] ?? 'CZK';
$paymentType     = $data['payment_type'] ?? '';
$isBankTransfer  = $paymentType === 'bank';

// Ceny
$totalPrice           = number_format((float) ($data['total_price'] ?? 0), 2, ',', ' ');
$totalPriceWithVat    = number_format((float) ($data['total_price_with_vat'] ?? 0), 2, ',', ' ');
$totalPriceAll        = number_format((float) ($data['total_price_all'] ?? 0), 2, ',', ' ');
$totalPriceAllWithVat = number_format((float) ($data['total_price_all_with_vat'] ?? 0), 2, ',', ' ');
$shippingPrice        = (float) ($data['shipping_price'] ?? 0);

// Dodavatel z contact enumu
$supplierName    = $supplier['name']   ?? 'Zajeci.cz';
$supplierStreet  = $supplier['street'] ?? '';
$supplierCity    = $supplier['city']   ?? '';
$supplierZip     = $supplier['zip']    ?? '';
$supplierIco     = $supplier['ic']     ?? '';
$supplierDic     = $supplier['dic']    ?? '';
$supplierEmail   = $supplier['email']  ?? '';
$supplierPhone   = $supplier['phone1'] ?? '';

// VAT sadby - agregace polozek
$vatGroups = [];
foreach ($items as $item) {
    $rate = (float) ($item['vat_rate'] ?? 0);
    $key  = number_format($rate, 0);
    if (!isset($vatGroups[$key])) {
        $vatGroups[$key] = ['rate' => $rate, 'base' => 0.0, 'vat' => 0.0];
    }
    $base = (float) ($item['total_price'] ?? 0);
    $vat  = (float) ($item['total_price_with_vat'] ?? 0) - $base;
    $vatGroups[$key]['base'] += $base;
    $vatGroups[$key]['vat']  += $vat;
}
if ($shippingPrice > 0) {
    // Doprava obvykle bez DPH — prida se do skupiny 0 %
    $vatGroups['0']['base'] = ($vatGroups['0']['base'] ?? 0) + $shippingPrice;
}
?>
<!DOCTYPE html>
<html lang="cs">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222222;
            background: #ffffff;
            padding: 20px 24px;
        }

        h1,
        h2,
        h3 {
            font-weight: bold;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        /* ── Header ────────────────────────────────── */
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #1a2b3c;
            padding-bottom: 10px;
        }

        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a2b3c;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .header-number {
            font-size: 13px;
            color: #555;
            margin-top: 4px;
        }

        /* ── Parties ───────────────────────────────── */
        .parties {
            margin-bottom: 14px;
        }

        .party-box {
            vertical-align: top;
            width: 49%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            background: #f9f9f9;
        }

        .party-spacer {
            width: 2%;
        }

        .party-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 6px;
        }

        .party-name {
            font-size: 12px;
            font-weight: bold;
            color: #1a2b3c;
            margin-bottom: 4px;
        }

        .party-row {
            margin-bottom: 2px;
            color: #444;
        }

        /* ── Meta row ──────────────────────────────── */
        .meta-row {
            margin-bottom: 14px;
        }

        .meta-box {
            vertical-align: top;
            width: 49%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            background: #f9f9f9;
        }

        .meta-label {
            font-size: 9px;
            color: #888;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .meta-kv {
            margin-bottom: 3px;
        }

        .meta-k {
            color: #888;
            width: 110px;
            vertical-align: top;
            padding-right: 6px;
            white-space: nowrap;
        }

        .meta-v {
            color: #222;
            font-weight: bold;
        }

        /* ── Items table ───────────────────────────── */
        .items-table {
            margin-bottom: 16px;
        }

        .items-table th {
            background: #1a2b3c;
            color: #ffffff;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .items-table th.right,
        .items-table td.right {
            text-align: right;
        }

        .items-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .items-table tr:nth-child(even) td {
            background: #f7f8fa;
        }

        .items-sku {
            font-size: 9px;
            color: #888;
            margin-top: 2px;
        }

        /* ── Totals ────────────────────────────────── */
        .totals {
            margin-bottom: 16px;
        }

        .totals td {
            padding: 4px 8px;
        }

        .totals .totals-label {
            text-align: right;
            color: #555;
            width: 70%;
        }

        .totals .totals-value {
            text-align: right;
            font-weight: bold;
            color: #222;
            white-space: nowrap;
            width: 30%;
        }

        .totals .grand-total td {
            border-top: 2px solid #1a2b3c;
            font-size: 13px;
            color: #1a2b3c;
            padding-top: 6px;
        }

        /* ── VAT summary ───────────────────────────── */
        .vat-table {
            margin-bottom: 16px;
            font-size: 10px;
        }

        .vat-table th {
            background: #eeeeee;
            color: #555;
            padding: 4px 8px;
            text-align: right;
            font-weight: bold;
        }

        .vat-table th:first-child {
            text-align: left;
        }

        .vat-table td {
            padding: 4px 8px;
            text-align: right;
            border-bottom: 1px solid #eeeeee;
        }

        .vat-table td:first-child {
            text-align: left;
        }

        /* ── Bank / QR ─────────────────────────────── */
        .payment-section {
            margin-bottom: 16px;
        }

        .qr-img {
            width: 100px;
            height: 100px;
        }

        .bank-td {
            vertical-align: top;
            padding-left: 14px;
        }

        .note {
            font-size: 10px;
            color: #666;
            margin-bottom: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-issued {
            background: #dce7fb;
            color: #1a4fa0;
        }

        .status-paid {
            background: #d4f5e2;
            color: #1a7a45;
        }

        .status-overdue {
            background: #fde8e8;
            color: #a01a1a;
        }

        .status-draft {
            background: #f0f0f0;
            color: #555555;
        }

        .status-cancelled {
            background: #f0f0f0;
            color: #555555;
        }

        /* ── Footer ────────────────────────────────── */
        .footer {
            border-top: 2px solid #1a2b3c;
            margin-top: 20px;
            padding-top: 10px;
            font-size: 10px;
            color: #888;
            text-align: center;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <table class="header">
        <tr>
            <td>
                <div class="header-title">Faktura</div>
            </td>
            <td style="text-align:right; vertical-align:bottom;">
                <div class="header-number"><?= htmlspecialchars($invoiceNumber) ?></div>
            </td>
        </tr>
    </table>

    <!-- PARTIES: DODAVATEL + ODBERATEL -->
    <table class="parties">
        <tr>
            <!-- Dodavatel -->
            <td class="party-box">
                <div class="party-label">Dodavatel</div>
                <div class="party-name"><?= htmlspecialchars($supplierName) ?></div>
                <?php if ($supplierStreet): ?><div class="party-row"><?= htmlspecialchars($supplierStreet) ?></div><?php endif; ?>
                <?php if ($supplierZip || $supplierCity): ?>
                    <div class="party-row"><?= htmlspecialchars(trim($supplierZip . ' ' . $supplierCity)) ?></div>
                <?php endif; ?>
                <?php if ($supplierIco): ?><div class="party-row" style="margin-top:4px;">IČ: <?= htmlspecialchars($supplierIco) ?></div><?php endif; ?>
                <?php if ($supplierDic): ?><div class="party-row">DIČ: <?= htmlspecialchars($supplierDic) ?></div><?php endif; ?>
                <?php if ($supplierEmail): ?><div class="party-row" style="margin-top:4px;"><?= htmlspecialchars($supplierEmail) ?></div><?php endif; ?>
                <?php if ($supplierPhone): ?><div class="party-row"><?= htmlspecialchars($supplierPhone) ?></div><?php endif; ?>
            </td>
            <td class="party-spacer"></td>
            <!-- Odberatel -->
            <td class="party-box">
                <div class="party-label">Odběratel</div>
                <?php if (!empty($billing['company'])): ?>
                    <div class="party-name"><?= htmlspecialchars($billing['company']) ?></div>
                <?php elseif (!empty($billing['name'])): ?>
                    <div class="party-name"><?= htmlspecialchars($billing['name']) ?></div>
                <?php elseif (!empty($user)): ?>
                    <div class="party-name"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></div>
                <?php endif; ?>

                <?php if (!empty($billing['street'])): ?><div class="party-row"><?= htmlspecialchars($billing['street']) ?></div><?php endif; ?>
                <?php if (!empty($billing['zip']) || !empty($billing['city'])): ?>
                    <div class="party-row"><?= htmlspecialchars(trim(($billing['zip'] ?? '') . ' ' . ($billing['city'] ?? ''))) ?></div>
                <?php endif; ?>
                <?php if (!empty($billing['country'])): ?><div class="party-row"><?= htmlspecialchars($billing['country']) ?></div><?php endif; ?>
                <?php if (!empty($billing['vat_number'])): ?><div class="party-row" style="margin-top:4px;">DIČ: <?= htmlspecialchars($billing['vat_number']) ?></div><?php endif; ?>
                <?php if (!empty($user['email'])): ?><div class="party-row" style="margin-top:4px;"><?= htmlspecialchars($user['email']) ?></div><?php endif; ?>
                <?php if (!empty($user['phone'])): ?><div class="party-row"><?= htmlspecialchars($user['phone']) ?></div><?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- META: PLATEBNI UDAJE + DATUMY -->
    <table class="meta-row">
        <tr>
            <td class="meta-box" style="width:60%;">
                <table style="width:100%;">
                    <tr>
                        <td style="vertical-align:top;">
                            <div class="meta-label">Platební údaje</div>
                            <table>
                                <?php if (!empty($bankDetails['account'])): ?>
                                    <tr class="meta-kv">
                                        <td class="meta-k">Číslo účtu</td>
                                        <td class="meta-v"><?= htmlspecialchars($bankDetails['account']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($bankDetails['iban'])): ?>
                                    <tr class="meta-kv">
                                        <td class="meta-k">IBAN</td>
                                        <td class="meta-v"><?= htmlspecialchars($bankDetails['iban']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($bankDetails['swift'])): ?>
                                    <tr class="meta-kv">
                                        <td class="meta-k">BIC/SWIFT</td>
                                        <td class="meta-v"><?= htmlspecialchars($bankDetails['swift']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php
                                $vs = preg_replace('/\D/', '', $invoiceNumber);
                                $vs = substr($vs, -10);
                                ?>
                                <?php if ($vs !== ''): ?>
                                    <tr class="meta-kv">
                                        <td class="meta-k">Variabilní symbol</td>
                                        <td class="meta-v"><?= htmlspecialchars($vs) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($data['order_number'])): ?>
                                    <tr class="meta-kv">
                                        <td class="meta-k">Č. objednávky</td>
                                        <td class="meta-v"><?= htmlspecialchars($data['order_number']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                        <?php if ($isBankTransfer && $qrCode): ?>
                            <td style="vertical-align:middle;text-align:center;width:120px;padding-left:12px;">
                                <div style="font-size:9px;color:#888;margin-bottom:4px;">QR platba</div>
                                <img src="<?= htmlspecialchars($qrCode) ?>" class="qr-img" alt="QR platba">
                            </td>
                        <?php endif; ?>
                    </tr>
                </table>
            </td>
            <td class="party-spacer"></td>
            <td class="meta-box" style="width:38%;">
                <div class="meta-label">Datumy</div>
                <table>
                    <tr class="meta-kv">
                        <td class="meta-k">Datum vystavení</td>
                        <td class="meta-v"><?= htmlspecialchars($issuedAt) ?></td>
                    </tr>
                    <tr class="meta-kv">
                        <td class="meta-k">Datum daň. povinnosti</td>
                        <td class="meta-v"><?= htmlspecialchars($issuedAt) ?></td>
                    </tr>
                    <tr class="meta-kv">
                        <td class="meta-k">Datum splatnosti</td>
                        <td class="meta-v"><?= htmlspecialchars($dueAt) ?></td>
                    </tr>
                    <?php if ($paidAt): ?>
                        <tr class="meta-kv">
                            <td class="meta-k">Datum úhrady</td>
                            <td class="meta-v"><?= htmlspecialchars($paidAt) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>

    <!-- POLOZKY FAKTURY -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:40%;">Popis</th>
                <th class="right" style="width:8%;">Množ.</th>
                <th class="right" style="width:14%;">Cena bez DPH</th>
                <th class="right" style="width:9%;">DPH %</th>
                <th class="right" style="width:14%;">Cena s DPH</th>
                <th class="right" style="width:15%;">Celkem s DPH</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['product_name'] ?? '') ?>
                        <?php if (!empty($item['sku'])): ?>
                            <div class="items-sku"><?= htmlspecialchars($item['sku']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="right"><?= (int) ($item['quantity'] ?? 1) ?></td>
                    <td class="right"><?= number_format((float) ($item['price'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td class="right"><?= number_format((float) ($item['vat_rate'] ?? 0), 0) ?> %</td>
                    <td class="right"><?= number_format((float) ($item['price_with_vat'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td class="right"><?= number_format((float) ($item['total_price_with_vat'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($shippingPrice > 0): ?>
                <tr>
                    <td>Doprava (<?= htmlspecialchars($data['shipping_type'] ?? '') ?>)</td>
                    <td class="right">1</td>
                    <td class="right"><?= number_format($shippingPrice, 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td class="right">0 %</td>
                    <td class="right"><?= number_format($shippingPrice, 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    <td class="right"><?= number_format($shippingPrice, 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- REKAPITULACE DPH + CELKEM -->
    <table style="width:50%;margin-left:50%;margin-bottom:16px;">
        <tr>
            <td style="vertical-align:top;">
                <?php if (count($vatGroups) > 0): ?>
                    <table class="vat-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>DPH</th>
                                <th>Základ</th>
                                <th>DPH</th>
                                <th>Celkem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vatGroups as $vg): ?>
                                <tr>
                                    <td><?= number_format($vg['rate'], 0) ?> %</td>
                                    <td><?= number_format($vg['base'], 2, ',', ' ') ?></td>
                                    <td><?= number_format($vg['vat'], 2, ',', ' ') ?></td>
                                    <td><?= number_format($vg['base'] + $vg['vat'], 2, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- CELKOVE CENY -->
                <table class="totals" style="width:100%;margin-top:8px;">
                    <tr>
                        <td class="totals-label">Celkem bez DPH</td>
                        <td class="totals-value"><?= $totalPrice ?> <?= htmlspecialchars($currency) ?></td>
                    </tr>
                    <tr>
                        <td class="totals-label">DPH celkem</td>
                        <td class="totals-value"><?= number_format((float)($data['total_price_with_vat'] ?? 0) - (float)($data['total_price'] ?? 0), 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                    </tr>
                    <?php if ($shippingPrice > 0): ?>
                        <tr>
                            <td class="totals-label">Doprava</td>
                            <td class="totals-value"><?= number_format($shippingPrice, 2, ',', ' ') ?> <?= htmlspecialchars($currency) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="grand-total">
                        <td class="totals-label"><strong>K ÚHRADĚ</strong></td>
                        <td class="totals-value"><strong><?= $totalPriceAllWithVat ?> <?= htmlspecialchars($currency) ?></strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- BANKOVNI PLATBA -->
    <?php if ($isBankTransfer): ?>
        <p style="margin-top:8px;font-size:9px;color:#888;text-align:center;">
            Prosíme o zaplacení do data splatnosti. Při prodlení si vyhrazujeme právo účtovat zákonný úrok z prodlení.
        </p>
    <?php endif; ?>

    <!-- POZNAMKA -->
    <?php if (!empty($data['note'])): ?>
        <div class="note" style="margin-top:14px;">
            <strong>Poznámka:</strong> <?= htmlspecialchars((string) $data['note']) ?>
        </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="footer">
        <?= htmlspecialchars($supplierName) ?>
        <?php if ($supplierEmail): ?> &bull; <?= htmlspecialchars($supplierEmail) ?><?php endif; ?>
            <?php if ($supplierPhone): ?> &bull; <?= htmlspecialchars($supplierPhone) ?><?php endif; ?>
                <br>Dokument byl vygenerován automaticky.
    </div>

</body>

</html>