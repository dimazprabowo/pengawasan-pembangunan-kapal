<?php

/**
 * Build template-laporan-bulanan.docx via PhpWord.
 *
 * Template sebelumnya adalah copy template HARIAN (placeholder salah).
 * Script ini membuat template bulanan yang benar dengan placeholder:
 *  - Single: judul, perusahaan, lokasi, no_kapal, bulan_ke, periode_label,
 *    tanggal, dibuat_oleh, ringkasan, total_rencana, total_aktual, selisih,
 *    ttd_kota, ttd_tanggal, ttd_nama.
 *  - Tabel: minggu_*, ext_*.
 *  - Blok: blok_nc (nc_* + nc_kosong).
 *
 * Jalankan: php build_laporan_bulanan_template.php
 */

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

$path = 'storage/app/templates/laporan-bulanan/template-laporan-bulanan.docx';
if (is_file($path)) {
    copy($path, $path.'.bak');
}

$phpWord = new PhpWord;
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(11);

$section = $phpWord->addSection();

$titleStyle = ['bold' => true, 'size' => 14];
$center = ['alignment' => Jc::CENTER, 'spaceAfter' => 120];
$headStyle = ['bold' => true, 'size' => 11];
$cellHead = ['bgColor' => 'DEEAF6', 'valign' => 'center'];
$cellBody = ['valign' => 'center'];

$section->addText('LAPORAN BULANAN PEMBANGUNAN KAPAL', $titleStyle, $center);
$section->addText('${judul}', ['bold' => true, 'size' => 12], $center);
$section->addTextBreak(1);

// ── Info umum ──────────────────────────────────────────────────────────
$info = [
    'Perusahaan' => '${perusahaan}',
    'Galangan / Lokasi' => '${lokasi}',
    'Nama Kapal' => '${no_kapal}',
    'Bulan Ke' => '${bulan_ke}',
    'Periode' => '${periode_label}',
    'Tanggal Laporan' => '${tanggal}',
    'Dibuat Oleh' => '${dibuat_oleh}',
];
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
foreach ($info as $label => $val) {
    $table->addRow();
    $table->addCell(3200, $cellHead)->addText($label, $headStyle);
    $table->addCell(6200, $cellBody)->addText($val);
}
$section->addTextBreak(1);

$section->addText('A. Ringkasan', $headStyle);
$section->addText('${ringkasan}');
$section->addTextBreak(1);

// ── B. Progress Kurva-S ────────────────────────────────────────────────
$section->addText('B. Progress Kurva-S (s.d. akhir bulan ini)', $headStyle);
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
$table->addRow();
foreach (['Rencana (%)', 'Realisasi (%)', 'Selisih (%)'] as $h) {
    $table->addCell(3100, $cellHead)->addText($h, $headStyle, $center);
}
$table->addRow();
foreach (['${total_rencana}', '${total_aktual}', '${selisih}'] as $v) {
    $table->addCell(3100, $cellBody)->addText($v, [], $center);
}
$section->addTextBreak(1);

// ── C. Rekap Laporan Mingguan ──────────────────────────────────────────
$section->addText('C. Rekap Laporan Mingguan', $headStyle);
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
$table->addRow();
foreach ([800 => 'No', 2000 => 'Minggu', 4200 => 'Periode', 2200 => 'Progress'] as $w => $h) {
    $table->addCell($w, $cellHead)->addText($h, $headStyle, $center);
}
$table->addRow();
$table->addCell(800, $cellBody)->addText('${minggu_no}', [], $center);
$table->addCell(2000, $cellBody)->addText('${minggu_ke}');
$table->addCell(4200, $cellBody)->addText('${minggu_periode}');
$table->addCell(2200, $cellBody)->addText('${minggu_progress}', [], $center);
$section->addTextBreak(1);

// ── D. Rekap NC (Non-Conformance) ──────────────────────────────────────
$section->addText('D. Rekap NC (Non-Conformance)', $headStyle);
$section->addText('${blok_nc}');
$section->addText('${nc_kosong}');
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
$table->addRow();
foreach ([600 => 'No', 1400 => 'Nomor', 1600 => 'Tanggal', 3000 => 'Kendala', 2200 => 'Tindakan', 1200 => 'PIC', 1000 => 'Status', 1800 => 'Closed'] as $w => $h) {
    $table->addCell($w, $cellHead)->addText($h, $headStyle, $center);
}
$table->addRow();
$table->addCell(600, $cellBody)->addText('${nc_no}', [], $center);
$table->addCell(1400, $cellBody)->addText('${nc_nomor}');
$table->addCell(1600, $cellBody)->addText('${nc_tanggal}');
$table->addCell(3000, $cellBody)->addText('${nc_kendala}');
$table->addCell(2200, $cellBody)->addText('${nc_tindakan}');
$table->addCell(1200, $cellBody)->addText('${nc_pic}');
$table->addCell(1000, $cellBody)->addText('${nc_status}');
$table->addCell(1800, $cellBody)->addText('${nc_closed}');
$section->addText('${/blok_nc}');
$section->addTextBreak(1);

// ── E. Dokumen Eksternal ───────────────────────────────────────────────
$section->addText('E. Dokumen Eksternal', $headStyle);
$table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 60]);
$table->addRow();
foreach ([800 => 'No', 5200 => 'Judul', 3400 => 'File'] as $w => $h) {
    $table->addCell($w, $cellHead)->addText($h, $headStyle, $center);
}
$table->addRow();
$table->addCell(800, $cellBody)->addText('${ext_no}', [], $center);
$table->addCell(5200, $cellBody)->addText('${ext_judul}');
$table->addCell(3400, $cellBody)->addText('${ext_file}');
$section->addTextBreak(2);

// ── F. Tanda tangan ────────────────────────────────────────────────────
$section->addText('${ttd_kota}, ${ttd_tanggal}', [], ['alignment' => Jc::END]);
$section->addText('Dibuat oleh,', [], ['alignment' => Jc::END]);
$section->addTextBreak(3);
$section->addText('( ${ttd_nama} )', ['bold' => true], ['alignment' => Jc::END]);

$phpWord->save($path, 'Word2007');

// Beri atribut pada <w:tr> agar TemplateProcessor::findRowStart()
// (mencari '<w:tr ' dengan spasi) menemukan baris yang benar saat cloneRow.
$z = new ZipArchive;
$z->open($path);
$xml = $z->getFromName('word/document.xml');
$xml = str_replace('<w:tr>', '<w:tr w:rsidR="00C0FFEE">', $xml);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "Template bulanan dibuat: {$path}\n";
