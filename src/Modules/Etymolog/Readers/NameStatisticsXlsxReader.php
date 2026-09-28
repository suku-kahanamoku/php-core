<?php

declare(strict_types=1);
namespace App\Modules\Etymolog\Readers;

use App\Modules\Etymolog\SyncException;

/** Minimal bounded XLSX reader for the reviewed CSU name/count/rank tables; no formulas or external links. */
final class NameStatisticsXlsxReader
{
    public function read(string $bytes): array
    {
        if (!class_exists(\ZipArchive::class)) { throw new SyncException('php_zip_required'); }
        if (strlen($bytes) > 2000000) { throw new SyncException('xlsx_too_large'); }
        $path = tempnam(sys_get_temp_dir(), 'ety-xlsx-');
        if ($path === false) { throw new SyncException('xlsx_buffer_failed'); }
        $zip = new \ZipArchive(); $opened = false;
        try {
            if (file_put_contents($path, $bytes) !== strlen($bytes) || $zip->open($path) !== true) { throw new SyncException('invalid_xlsx'); }
            $opened = true;
            $read = static function (string $name) use ($zip): \DOMXPath {
                $stat = $zip->statName($name);
                if (!$stat || $stat['size'] > 2000000) { throw new SyncException('invalid_xlsx_part'); }
                $xml = $zip->getFromName($name);
                if (!is_string($xml) || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) { throw new SyncException('invalid_xlsx_xml'); }
                $dom = new \DOMDocument(); $previous = libxml_use_internal_errors(true);
                try { $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
                finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
                if (!$ok) { throw new SyncException('invalid_xlsx_xml'); }
                $xp = new \DOMXPath($dom);
                $xp->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                return $xp;
            };
            $wb = $read('xl/workbook.xml');
            $sheets = $wb->query('//x:sheets/x:sheet');
            if ($sheets->length !== 2 || $sheets->item(0)->getAttribute('name') !== 'Chlapci' || $sheets->item(1)->getAttribute('name') !== 'Dívky') { throw new SyncException('statistics_schema_changed'); }
            // Validate relationships instead of assuming worksheet file order.
            $relsStat = $zip->statName('xl/_rels/workbook.xml.rels');
            if (!$relsStat || $relsStat['size'] > 100000) { throw new SyncException('invalid_xlsx_relationships'); }
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if (!is_string($relsXml) || strlen($relsXml) > 100000 || stripos($relsXml, '<!DOCTYPE') !== false || stripos($relsXml, '<!ENTITY') !== false) { throw new SyncException('invalid_xlsx_relationships'); }
            $relsDom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try { $ok = $relsDom->loadXML($relsXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
            if (!$ok) { throw new SyncException('invalid_xlsx_relationships'); }
            $targets = [];
            foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
                if (str_ends_with($rel->getAttribute('Type'), '/worksheet') && $rel->getAttribute('TargetMode') !== 'External') { $targets[$rel->getAttribute('Id')] = $rel->getAttribute('Target'); }
            }
            $strings = [];
            $sx = $read('xl/sharedStrings.xml');
            foreach ($sx->query('//x:si') as $si) {
                $value = '';
                foreach ($sx->query('.//x:t', $si) as $t) { $value .= $t->textContent; }
                $strings[] = $value;
            }
            $result = [];
            foreach ($sheets as $index => $sheet) {
                $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                $target = $targets[$rid] ?? '';
                if (!preg_match('~^worksheets/sheet[0-9]+\.xml$~D', $target)) { throw new SyncException('invalid_xlsx_relationships'); }
                $xp = $read('xl/'.$target);
                $rows = []; $seen = [];
                foreach ($xp->query('//x:sheetData/x:row') as $row) {
                    $values = [];
                    foreach ($xp->query('./x:c', $row) as $cell) {
                        if ($xp->query('./x:f', $cell)->length) { throw new SyncException('xlsx_formula_not_allowed'); }
                        if (!preg_match('/^([A-C])[0-9]+$/D', $cell->getAttribute('r'), $m)) { continue; }
                        $value = $xp->evaluate('string(x:v)', $cell);
                        if ($cell->getAttribute('t') === 's') {
                            if (!ctype_digit($value) || !isset($strings[(int)$value])) { throw new SyncException('invalid_xlsx_string'); }
                            $value = $strings[(int)$value];
                        } elseif ($cell->getAttribute('t') === 'inlineStr') { $value = $xp->evaluate('string(x:is/x:t)', $cell); }
                        $values[$m[1]] = $value;
                    }
                    if ($values === [] || count(array_filter($values, static fn ($v) => $v !== '')) === 0) { continue; }
                    if ($rows === []) {
                        if ($values !== ['A' => 'Jméno', 'B' => 'Počet', 'C' => 'Pořadí']) { throw new SyncException('statistics_schema_changed'); }
                        $rows[] = $values; continue;
                    }
                    if (!isset($values['A'], $values['B'], $values['C']) || trim($values['A']) === '' || strlen($values['A']) > 255 || isset($seen[$values['A']]) || !ctype_digit($values['B']) || (float)$values['B'] > 2147483647 || !preg_match('/^([1-9][0-9]{0,2})(?:-([1-9][0-9]{0,2}))?$/D', $values['C'], $rank) || (int)$rank[1] > 100 || (isset($rank[2]) && ((int)$rank[2] < (int)$rank[1] || (int)$rank[2] > 150))) { throw new SyncException('invalid_statistics_row'); }
                    $seen[$values['A']] = true;
                    $rows[] = $values;
                    $result[] = ['name' => $values['A'], 'count' => (int)$values['B'], 'rank' => $values['C'], 'sex' => $index === 0 ? 'male' : 'female'];
                }
                if (count($rows) < 101 || count($rows) > 151) { throw new SyncException('statistics_top100_incomplete'); }
            }
            return $result;
        } finally {
            if ($opened) { $zip->close(); }
            unlink($path);
        }
    }
}
