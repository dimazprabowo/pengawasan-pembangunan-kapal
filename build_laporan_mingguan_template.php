<?php

/**
 * Build template-laporan-mingguan.docx via PhpWord (acuan: Weekly Report BKI).
 *
 * Tabel tanpa gambar (Kurva-S, Uraian Kegiatan, NC, Dokumen Eksternal) dibangun
 * di LaporanMingguanWordService via setComplexBlock, sehingga template hanya
 * memuat placeholder paragraf: ${tabel_kurva_s}, ${tabel_uraian}, ${tabel_nc},
 * ${tabel_external}. Foto memakai cloneBlock/cloneRow:
 *  ${blok_foto}: tabel baris ${fh_no} ${fh_gambar} ${fh_ket} (satu tabel gabungan)
 * ${lampiran_harian} diganti altChunk docx laporan harian (dokumen utuh).
 *
 * Jalankan: php build_laporan_mingguan_template.php
 */

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;

$path = 'storage/app/templates/laporan-mingguan/template-laporan-mingguan.docx';

const NAVY = '1F3864';
const LIGHT = 'D9E2F3';
const BORDER = '8EAADB';
const CONTENT_WIDTH = 9026; // A4 (11906) - margin kiri/kanan 1440

// Teks statis seperti "Personel, Peralatan & Consumable" wajib di-escape
Settings::setOutputEscapingEnabled(true);

$phpWord = new PhpWord;
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10);
$phpWord->setDefaultParagraphStyle(['spaceAfter' => 0, 'spacing' => 0]);

$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 12, 'color' => NAVY], [
    'spaceBefore' => 240, 'spaceAfter' => 120,
    'borderBottomSize' => 8, 'borderBottomColor' => NAVY,
]);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 10, 'color' => NAVY], ['spaceBefore' => 160, 'spaceAfter' => 80]);

$section = $phpWord->addSection([
    'paperSize' => 'A4',
    'marginTop' => 1440, 'marginBottom' => 1440, 'marginLeft' => 1440, 'marginRight' => 1440,
]);

$footer = $section->addFooter();
// Placeholder ${...} tidak boleh di addPreserveText (kurung kurawal diparse sebagai field)
$footer->addText('Laporan Mingguan ${no_kapal} — Minggu Ke-${minggu_ke}', ['size' => 8, 'color' => '7F7F7F'], ['alignment' => Jc::END]);
$footer->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['size' => 8, 'color' => '7F7F7F'], ['alignment' => Jc::END]);

$tableStyle = [
    'borderSize' => 4, 'borderColor' => BORDER, 'cellMarginLeft' => 80, 'cellMarginRight' => 80,
    'cellMarginTop' => 40, 'cellMarginBottom' => 40, 'alignment' => JcTable::CENTER,
    'layout' => 'fixed', 'unit' => TblWidth::TWIP, 'width' => CONTENT_WIDTH,
];
$head = ['bgColor' => NAVY, 'valign' => 'center'];
$headFont = ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];
$center = ['alignment' => Jc::CENTER];
$body = ['valign' => 'center'];
$small = ['size' => 9];

$p = fn (string $text, array $font = [], array $para = []) => $section->addText($text, $font, $para);

// ══ COVER ═════════════════════════════════════════════════════════════════
$section->addTextBreak(4);
$p('LAPORAN MINGGUAN', ['bold' => true, 'size' => 24, 'color' => NAVY], $center);
$p('MINGGU KE-${minggu_ke}', ['bold' => true, 'size' => 14, 'color' => NAVY], $center + ['spaceBefore' => 120]);
$p('${periode_label}', ['size' => 12], $center + ['spaceBefore' => 80]);
$section->addTextBreak(3);
$p('PELAKSANAAN PEKERJAAN JASA KONSULTAN PENGAWAS', ['bold' => true, 'size' => 12], $center);
$p('PEMBANGUNAN ${no_kapal}', ['bold' => true, 'size' => 12], $center + ['spaceBefore' => 60]);
$section->addTextBreak(4);

$cover = $section->addTable($tableStyle + ['borderSize' => 0, 'borderColor' => 'FFFFFF']);
$cover->addRow();
$c = $cover->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('DISAMPAIKAN KEPADA PEMILIK / OWNER :', ['bold' => true, 'size' => 9, 'color' => NAVY], $center);
$c->addText('${perusahaan}', ['bold' => true, 'size' => 11], $center + ['spaceBefore' => 120]);
$c = $cover->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('DISUSUN OLEH KONSULTAN PENGAWAS :', ['bold' => true, 'size' => 9, 'color' => NAVY], $center);
$c->addText('PT. BIRO KLASIFIKASI INDONESIA (PERSERO)', ['bold' => true, 'size' => 11], $center + ['spaceBefore' => 120]);
$c->addText('SBU MARINE SERVICES', ['size' => 9], $center);
$section->addPageBreak();

// ══ INFORMASI PROYEK ═════════════════════════════════════════════════════
$section->addTitle('INFORMASI PROYEK', 1);
$info = $section->addTable($tableStyle);
foreach ([
    'Pekerjaan' => 'Jasa Konsultan Pengawas Pembangunan Kapal',
    'Nama Kapal' => '${no_kapal}',
    'Lokasi / Galangan' => '${lokasi}',
    'Pemberi Tugas' => '${perusahaan}',
    'Konsultan Pengawas' => 'PT Biro Klasifikasi Indonesia (Persero) / SBU Marine Services',
    'Minggu Ke' => '${minggu_ke}',
    'Periode' => '${periode_label}',
    'Tanggal Laporan' => '${tanggal}',
] as $label => $val) {
    $info->addRow();
    $info->addCell(2800, ['bgColor' => LIGHT, 'valign' => 'center'])->addText($label, ['bold' => true, 'color' => NAVY]);
    $info->addCell(CONTENT_WIDTH - 2800, $body)->addText($val);
}

// ══ A. RESUME ════════════════════════════════════════════════════════════
$section->addTitle('A. RESUME', 1);
$section->addTitle('1. Ringkasan Kegiatan Major', 2);
$p('${ringkasan}', [], ['alignment' => Jc::BOTH, 'spacing' => 60]);
$section->addTitle('2. Progress Kurva-S', 2);
$p('${tabel_kurva_s}');

// ══ B. URAIAN KEGIATAN ═══════════════════════════════════════════════════
$section->addTitle('B. URAIAN KEGIATAN', 1);
$p('${tabel_uraian}');

// ══ C. REKOMENDASI / NC ══════════════════════════════════════════════════
$section->addTitle('C. REKOMENDASI / NC', 1);
$p('${tabel_nc}');

// ══ D. FOTO / DOKUMENTASI ════════════════════════════════════════════════
$section->addTitle('D. FOTO / DOKUMENTASI', 1);
$p('${foto_kosong}', ['italic' => true, 'color' => '7F7F7F']);
$p('${blok_foto}');
$foto = $section->addTable($tableStyle);
$foto->addRow(null, ['tblHeader' => true]);
$foto->addCell(600, $head)->addText('No', $headFont, $center);
$foto->addCell(5326, $head)->addText('Dokumentasi', $headFont, $center);
$foto->addCell(3100, $head)->addText('Keterangan', $headFont, $center);
// Ukuran sel gambar statis (lebar kolom 5326 & tinggi baris persis 3600 twip ≈ 9,4 × 6,35 cm);
// service mem-fit gambar ke kotak ini (FOTO_BOX_* di LaporanMingguanWordService)
$foto->addRow(3600, ['cantSplit' => true, 'exactHeight' => true]);
$foto->addCell(600, $body)->addText('${fh_no}', $small, $center);
$foto->addCell(5326, $body)->addText('${fh_gambar}', $small, $center);
$foto->addCell(3100, $body)->addText('${fh_ket}', $small);
$p('${/blok_foto}');

// ══ PENGESAHAN ═══════════════════════════════════════════════════════════
$section->addTextBreak(1);
$sign = $section->addTable($tableStyle + ['borderSize' => 0, 'borderColor' => 'FFFFFF']);
$sign->addRow();
$sign->addCell(CONTENT_WIDTH / 2);
$c = $sign->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('${ttd_kota}, ${ttd_tanggal}', [], $center);
$c->addText('Konsultan Pengawas', [], $center);
$c->addText('PT BKI – SBU Marine Services', ['bold' => true], $center);
$c->addTextBreak(3);
$c->addText('${ttd_nama}', ['bold' => true, 'underline' => 'single'], $center);

// ══ E. LAMPIRAN ══════════════════════════════════════════════════════════
$section->addPageBreak();
$section->addTitle('E. LAMPIRAN', 1);
$section->addTitle('1. Dokumen Eksternal', 2);
$p('${tabel_external}');
$section->addTitle('2. Laporan Harian', 2);
// Diganti service dengan altChunk docx laporan harian (tampilan identik template harian)
$p('${lampiran_harian}', ['italic' => true, 'color' => '7F7F7F']);

$phpWord->save($path, 'Word2007');

// Beri atribut pada <w:tr> agar TemplateProcessor::findRowStart()
// (mencari '<w:tr ' dengan spasi) menemukan baris yang benar saat cloneRow.
$z = new ZipArchive;
$z->open($path);
$xml = $z->getFromName('word/document.xml');
$xml = str_replace('<w:tr>', '<w:tr w:rsidR="00C0FFEE">', $xml);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "Template mingguan dibuat: {$path}\n";
