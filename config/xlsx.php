<?php

/* =========================================================
   PEMBUAT FILE EXCEL (.xlsx) SEDERHANA
   Tidak memerlukan pustaka tambahan (cukup ekstensi zip PHP,
   sudah aktif bawaan XAMPP).
========================================================= */

function xlsxKolom($n)
{
    $s = '';

    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - $m - 1, 26);
    }

    return $s;
}

function xlsxTeks($v)
{
    $v = (string) $v;

    /* buang karakter kontrol yang tidak boleh ada di XML */
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);

    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/*
   $judul  : daftar baris judul di atas tabel (array string)
   $header : judul kolom
   $baris  : array dari array nilai (int/float = angka, selain itu = teks)
   $lebar  : lebar kolom (karakter), opsional
*/
function kirimXlsx($nama_file, $nama_sheet, array $judul, array $header, array $baris, array $lebar = [])
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'Ekstensi zip PHP belum aktif. Aktifkan extension=zip pada php.ini lalu restart Apache.';
        exit;
    }

    $jml_kol = count($header);
    $baris_header = count($judul) + ($judul ? 2 : 1);  // satu baris kosong setelah judul

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
    $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
          . '<pane ySplit="' . $baris_header . '" topLeftCell="A' . ($baris_header + 1) . '" activePane="bottomLeft" state="frozen"/>'
          . '</sheetView></sheetViews>';

    $xml .= '<cols>';
    for ($c = 1; $c <= $jml_kol; $c++) {
        $w = $lebar[$c - 1] ?? 20;
        $xml .= '<col min="' . $c . '" max="' . $c . '" width="' . (float) $w . '" customWidth="1"/>';
    }
    $xml .= '</cols>';

    $xml .= '<sheetData>';

    $r = 1;

    /* judul */
    foreach ($judul as $i => $teks) {

        $gaya = ($i === 0) ? 2 : 4;

        $xml .= '<row r="' . $r . '"' . ($i === 0 ? ' ht="24" customHeight="1"' : '') . '>';
        $xml .= '<c r="A' . $r . '" s="' . $gaya . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxTeks($teks) . '</t></is></c>';
        $xml .= '</row>';

        $r++;
    }

    if ($judul) {
        $r++;  // baris kosong
    }

    /* header */
    $xml .= '<row r="' . $r . '" ht="22" customHeight="1">';
    foreach ($header as $i => $h) {
        $xml .= '<c r="' . xlsxKolom($i + 1) . $r . '" s="1" t="inlineStr"><is><t xml:space="preserve">' . xlsxTeks($h) . '</t></is></c>';
    }
    $xml .= '</row>';

    $r_header = $r;
    $r++;

    /* data */
    foreach ($baris as $row) {

        $xml .= '<row r="' . $r . '">';

        $i = 0;

        foreach ($row as $v) {

            $i++;
            $ref = xlsxKolom($i) . $r;

            if ($v === null || $v === '') {
                $xml .= '<c r="' . $ref . '" s="3"/>';
            } elseif (is_int($v) || is_float($v)) {
                $xml .= '<c r="' . $ref . '" s="5"><v>' . $v . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" s="3" t="inlineStr"><is><t xml:space="preserve">' . xlsxTeks($v) . '</t></is></c>';
            }
        }

        $xml .= '</row>';
        $r++;
    }

    $xml .= '</sheetData>';

    if ($baris) {
        $xml .= '<autoFilter ref="A' . $r_header . ':' . xlsxKolom($jml_kol) . ($r - 1) . '"/>';
    }

    $xml .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
    $xml .= '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
    $xml .= '</worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="4">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="15"/><name val="Calibri"/></font>'
        . '<font><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border>'
        . '<left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right>'
        . '<top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/>'
        . '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="6">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $nama_sheet = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $nama_sheet), 0, 31);

    if ($nama_sheet === '') {
        $nama_sheet = 'Laporan';
    }

    $tmp = tempnam(sys_get_temp_dir(), 'xls');

    $zip = new ZipArchive();

    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Gagal membuat berkas Excel.';
        exit;
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsxTeks($nama_sheet) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $xml);

    $zip->close();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $nama_file) . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store, no-cache, must-revalidate');

    readfile($tmp);
    @unlink($tmp);

    exit;
}