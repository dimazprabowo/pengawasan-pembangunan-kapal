<?php

/**
 * Menyisipkan blok placeholder NC (${blok_nc}, ${blok_nc_foto}) ke template Word
 * default laporan harian & mingguan. Jalankan sekali: php build_nc_template.php
 * Template custom per Data Kapal bisa meniru placeholder yang sama.
 */
$cell = fn (string $text, int $w = 2000, bool $bold = false) => '<w:tc><w:tcPr><w:tcW w:w="'.$w.'" w:type="dxa"/></w:tcPr><w:p><w:r>'.($bold ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p></w:tc>';

$p = fn (string $text) => '<w:p><w:r><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
$heading = fn (string $text) => '<w:p><w:r><w:rPr><w:b/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';

$table = function (array $headers, array $cols) use ($cell) {
    $headerRow = '<w:tr w:rsidR="00C0FFEE">'.implode('', array_map(fn ($h) => $cell($h, 1600, true), $headers)).'</w:tr>';
    $dataRow = '<w:tr w:rsidR="00C0FFEE">'.implode('', array_map(fn ($c) => $cell($c, 1600), $cols)).'</w:tr>';

    return '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
        .'<w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/>'
        .'<w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/>'
        .'</w:tblBorders></w:tblPr>'.$headerRow.$dataRow.'</w:tbl>';
};

// ── Laporan Harian ──────────────────────────────────────────────────
$harian = $p('${blok_nc}')
    .$heading('G. NON-CONFORMITY (NC)')
    .$p('${nc_kosong}')
    .$table(['No', 'Nomor', 'Kendala', 'Tindakan', 'PIC', 'Status'], ['${nc_no}', '${nc_nomor}', '${nc_kendala}', '${nc_tindakan}', '${nc_pic}', '${nc_status}'])
    .$p('${/blok_nc}')
    .$p('${blok_nc_foto}')
    .$p('Foto Before — ${ncf_nomor}')
    .$p('${ncf_foto}')
    .$p('${/blok_nc_foto}');

// ── Laporan Mingguan ────────────────────────────────────────────────
$mingguan = $p('${blok_nc}')
    .$heading('REKAP NON-CONFORMITY (NC)')
    .$p('${nc_kosong}')
    .$table(
        ['No', 'Nomor', 'Tanggal', 'Kendala', 'Tindakan', 'PIC', 'Status', 'Closed'],
        ['${nc_no}', '${nc_nomor}', '${nc_tanggal}', '${nc_kendala}', '${nc_tindakan}', '${nc_pic}', '${nc_status}', '${nc_closed}']
    )
    .$p('${/blok_nc}')
    .$p('${blok_nc_foto}')
    .$p('${ncf_nomor}')
    .'<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
    .'<w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/>'
    .'<w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/>'
    .'</w:tblBorders></w:tblPr>'
    .'<w:tr w:rsidR="00C0FFEE">'.$cell('Before', 2500, true).$cell('After', 2500, true).'</w:tr>'
    .'<w:tr w:rsidR="00C0FFEE">'.$cell('${ncf_before}', 2500).$cell('${ncf_after}', 2500).'</w:tr>'
    .'</w:tbl>'
    .$p('${/blok_nc_foto}');

$inject = function (string $path, string $xml) {
    if (! file_exists($path)) {
        echo "SKIP $path (tidak ada)\n";

        return;
    }

    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        echo "GAGAL buka $path\n";

        return;
    }

    $doc = $zip->getFromName('word/document.xml');
    if (str_contains($doc, '${blok_nc}')) {
        echo "SKIP $path (sudah ada blok NC)\n";
        $zip->close();

        return;
    }

    // Sisipkan sebelum sectPr penutup body (margin/halaman), fallback ke </w:body>
    $pos = strrpos($doc, '<w:sectPr');
    if ($pos === false || $pos > strrpos($doc, '</w:body>')) {
        $pos = strrpos($doc, '</w:body>');
    }
    $doc = substr($doc, 0, $pos).$xml.substr($doc, $pos);

    $zip->addFromString('word/document.xml', $doc);
    $zip->close();
    echo "OK $path\n";
};

$inject(__DIR__.'/storage/app/templates/laporan-harian/template-laporan-harian.docx', $harian);
$inject(__DIR__.'/storage/app/templates/laporan-mingguan/template-laporan-mingguan.docx', $mingguan);
