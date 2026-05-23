<?php

declare(strict_types=1);

namespace App\Modules\Invoice;

use App\Modules\Database\Database;
use App\Modules\File\FileRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * InvoicePdfService — generuje PDF faktury z sablony a uklada ji do souboru.
 *
 * Flow:
 *   1. render() vyrenderuje PHP sablonu emails/{franchise}/invoice.php do HTML
 *   2. Dompdf preklopi HTML na PDF
 *   3. PDF se ulozi na disk do files/{franchise}/invoice/{invoiceId}/{invoice_number}.pdf
 *   4. Zaznam souboru se vlozi do tabulky file a propoji s fakturou pres invoice_file
 */
class InvoicePdfService
{
    private FileRepository        $_file;
    private InvoiceRepository     $_invoice;
    private string                $_code;
    private \PDO                  $_pdo;

    public function __construct(Database $db, string $franchiseCode)
    {
        $this->_code    = $franchiseCode;
        $this->_pdo     = $db->getPdo();
        $this->_file    = new FileRepository($db, $franchiseCode);
        $this->_invoice = new InvoiceRepository($db, $franchiseCode);
    }

    /**
     * Vygeneruje PDF faktury, ulozi ho na disk, zaregistruje v DB a linkne ke fakture.
     * Vraci ID vytvoreneho file zaznamu.
     *
     * @param  array<string, mixed> $invoice  Kompletni faktura z findById()
     * @return int  file.id
     */
    public function generate(array $invoice): int
    {
        $invoiceId     = (int) $invoice['id'];
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? 'invoice');

        // Bankovni udaje z enum type=payment syscode=bank
        $bankEnum = $this->_db_fetchPaymentBank();

        // QR kod pro platbu (SPAYD format)
        $qrBase64 = $this->_generateQrBase64($invoice, $bankEnum);

        // Render HTML sablony
        $html = $this->_renderTemplate($invoice, $bankEnum, $qrBase64);

        // HTML → PDF
        $pdfContent = $this->_htmlToPdf($html, $invoiceNumber);

        // Uloz na disk
        $relPath = $this->_saveToDisk($invoiceId, $invoiceNumber, $pdfContent);

        // Registruj v DB
        $fileId = $this->_file->insert([
            'type'        => 'pdf',
            'mime_type'   => 'application/pdf',
            'path'        => $relPath,
            'name'        => $invoiceNumber . '.pdf',
            'size'        => strlen($pdfContent),
            'visibility'  => 'private',
            'entity_type' => 'invoice',
            'entity_id'   => $invoiceId,
        ]);

        // Propoj s fakturou pres junction tabulku
        $this->_invoice->syncFiles($invoiceId, array_merge(
            $this->_currentFileIds($invoiceId),
            [$fileId],
        ));

        return $fileId;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Nacte bankovni enum (type=payment, syscode=bank).
     *
     * @return array<string, mixed>|null
     */
    private function _db_fetchPaymentBank(): ?array
    {
        $stmt = $this->_pdo->prepare(
            'SELECT data FROM enumeration
             WHERE franchise_code = ? AND type = ? AND syscode = ? AND deleted = 0
             LIMIT 1',
        );
        $stmt->execute([$this->_code, 'payment', 'bank']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $data = json_decode((string) $row['data'], true);
        return is_array($data) ? $data : null;
    }

    /**
     * Vytvori base64 PNG QR kodu pro platbu ve formatu SPAYD.
     *
     * @param  array<string, mixed> $invoice
     * @param  array<string, mixed>|null $bank
     * @return string|null base64 PNG nebo null
     */
    private function _generateQrBase64(array $invoice, ?array $bank): ?string
    {
        if ($bank === null || empty($bank['iban'])) {
            return null;
        }

        $amount  = number_format((float) ($invoice['total_price_all_with_vat'] ?? 0), 2, '.', '');
        $currency = strtoupper((string) ($invoice['currency'] ?? 'CZK'));
        $msg     = 'Faktura ' . ($invoice['invoice_number'] ?? '');

        // SPAYD (Short Payment Descriptor) — cesky standard pro platebni QR
        $spayd = implode('*', [
            'SPD',
            '1.0',
            'ACC:' . $bank['iban'],
            'AM:'  . $amount,
            'CC:'  . $currency,
            'MSG:' . substr($msg, 0, 60),
        ]);

        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($spayd)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Low)
                ->size(200)
                ->margin(4)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->build();

            return 'data:image/png;base64,' . base64_encode($result->getString());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Vyrenderuje PHP sablonu emails/{franchise}/invoice.php do HTML stringu.
     *
     * @param  array<string, mixed> $invoice
     * @param  array<string, mixed>|null $bank
     * @param  string|null $qrBase64
     * @return string
     */
    private function _renderTemplate(array $invoice, ?array $bank, ?string $qrBase64): string
    {
        $templatePath = $this->_templatePath();

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Invoice template not found: {$templatePath}");
        }

        ob_start();
        // Proměnné dostupné v sablone
        $data         = $invoice;
        $bankDetails  = $bank;
        $qrCode       = $qrBase64;
        include $templatePath;
        return (string) ob_get_clean();
    }

    /**
     * Prevede HTML na PDF pomoci Dompdf.
     *
     * @param  string $html
     * @param  string $title
     * @return string  raw PDF content
     */
    private function _htmlToPdf(string $html, string $title): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultMediaType', 'print');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Ulozi PDF obsah na disk a vrati relativni cestu od FILE_ROOT.
     *
     * @param  int    $invoiceId
     * @param  string $invoiceNumber
     * @param  string $content
     * @return string  relativni cesta (napr. files/zajeci/invoice/7/2026-00005.pdf)
     */
    private function _saveToDisk(int $invoiceId, string $invoiceNumber, string $content): string
    {
        $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceNumber);
        $dir        = $this->_filesRoot() . '/' . $this->_code . '/invoice/' . $invoiceId;

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }

        $filename = $safeNumber . '.pdf';
        $absPath  = $dir . '/' . $filename;

        if (file_put_contents($absPath, $content) === false) {
            throw new \RuntimeException("Cannot write PDF to: {$absPath}");
        }

        return 'files/' . $this->_code . '/invoice/' . $invoiceId . '/' . $filename;
    }

    /**
     * Vrati absolutni cestu k FILE_ROOT.
     *
     * @return string
     */
    private function _root(): string
    {
        return rtrim($_ENV['FILE_ROOT'] ?? dirname(__DIR__, 3), '/');
    }

    /**
     * Vrati absolutni cestu k /files adresari.
     *
     * @return string
     */
    private function _filesRoot(): string
    {
        return $this->_root() . '/files';
    }

    /**
     * Vrati cestu k sablone faktury.
     *
     * @return string
     */
    private function _templatePath(): string
    {
        return dirname(__DIR__, 3) . '/emails/' . $this->_code . '/invoice.php';
    }

    /**
     * Vrati aktualni ID souboru spojene s fakturou (pres junction tabulku).
     *
     * @param  int $invoiceId
     * @return list<int>
     */
    private function _currentFileIds(int $invoiceId): array
    {
        $files = $this->_file->findByJunctionItem('invoice_file', 'invoice_id', $invoiceId);
        return array_map(static fn($f) => (int) $f['id'], $files);
    }
}
