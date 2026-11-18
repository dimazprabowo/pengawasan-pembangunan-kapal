<?php

/**
 * Build template-laporan-bulanan.docx via PhpWord (acuan: Monthly Report BKI/ASDP).
 *
 * Struktur: Cover → Lembar Pengesahan → Daftar Isi (TOC field) → Kata
 * Pengantar → Executive Summary (tabel progress + informasi umum) →
 * BAB 1 Pendahuluan → BAB 2 {nama kapal} (spesifikasi, design & approval
 * drawing, realisasi, vendor, material, waktu & progress mingguan,
 * pengujian) → BAB 3 Penutup (rapat, problem & rekomendasi, NC) →
 * BAB 4 Lampiran (dokumen eksternal + altChunk laporan mingguan).
 *
 * Tabel dinamis dibangun di LaporanBulananWordService via setComplexBlock:
 *   ${tabel_exec_progress} ${tabel_info_umum} ${tabel_spesifikasi}
 *   ${tabel_drawing_galangan} ${tabel_drawing_mno} ${tabel_drawing_klas}
 *   ${tabel_realisasi} ${tabel_vendor} ${tabel_material} ${tabel_waktu}
 *   ${tabel_progress_mingguan} ${tabel_pengujian} ${tabel_rapat}
 *   ${tabel_nc} ${tabel_external}
 * BAB 3.2 problem & rekomendasi = ${tabel_nc} (tarikan rekap NC periode).
 * ${lampiran_mingguan} diganti altChunk docx laporan mingguan (dokumen utuh).
 *
 * Penomoran (pola contoh ASDP — numPr asli, bukan teks literal):
 * - Judul BAB via numbering 'numJudulBab' yang ter-link pStyle JudulBab1
 *   ("BAB %1.") & JudulBab2 ("%1.%2."); post-process menyuntik numPr balik
 *   ke definisi style agar konsisten di Word/OnlyOffice.
 * - Judul tanpa nomor (LEMBAR PENGESAHAN, DAFTAR ISI, KATA PENGANTAR,
 *   EXECUTIVE SUMMARY) memakai JudulBab0/JudulBab0C — tetap masuk TOC level 1.
 * - Poin-poin (Kata Pengantar, Lingkup Pekerjaan) via addListItem 'numPoin'.
 *
 * Jalankan: php build_laporan_bulanan_template.php
 */

require __DIR__.'/vendor/autoload.php';

use App\Services\WordStyleService;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Tab;

$path = 'storage/app/templates/laporan-bulanan/template-laporan-bulanan.docx';

const NAVY = '1F3864';
const LIGHT = 'D9E2F3';
const BORDER = '8EAADB';
const CONTENT_WIDTH = 9026; // A4 (11906) - margin kiri/kanan 1440

Settings::setOutputEscapingEnabled(true);

$phpWord = new PhpWord;
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10);
$phpWord->setDefaultParagraphStyle(['spaceAfter' => 0, 'spacing' => 0]);

// Judul memakai style KHUSUS (bukan Heading1/2 bawaan) agar Daftar Isi bisa
// memakai switch \t "JudulBab*,level" — heading di dokumen laporan
// mingguan yang disisipkan via altChunk ikut style Heading1 mereka sendiri
// dan tidak boleh masuk daftar isi bulanan.
$phpWord->addFontStyle('JudulBabF1', ['bold' => true, 'size' => 20, 'color' => '1F4E79']);
$phpWord->addFontStyle('JudulBabF2', ['bold' => true, 'size' => 13, 'color' => '1F4E79']);
$judulBabLook = [
    'spaceBefore' => 240, 'spaceAfter' => 120, 'keepNext' => true,
    'borderBottomSize' => 8, 'borderBottomColor' => NAVY,
];
// JudulBab0/C = tampilan judul BAB TANPA penomoran (front matter). JudulBab1/2
// dinomori otomatis via numbering 'numJudulBab' (pStyle-linked, pola contoh).
$phpWord->addParagraphStyle('JudulBab0', $judulBabLook);
$phpWord->addParagraphStyle('JudulBab0C', $judulBabLook + ['alignment' => Jc::CENTER]);
$phpWord->addParagraphStyle('JudulBab1', array_replace($judulBabLook, [
    'alignment' => Jc::END, 'lineHeight' => 1.5,
    'borderBottomSize' => 24, 'borderBottomColor' => '1F4E79',
]));
$phpWord->addParagraphStyle('JudulBab2', [
    'spaceBefore' => 240, 'spaceAfter' => 240, 'keepNext' => true,
    'alignment' => Jc::BOTH,
]);

// Penomoran poin "1. 2. 3." — numPr sungguhan (hanging indent), bukan literal.
// PENTING: satu numId = satu counter berkesinambungan di seluruh dokumen —
// setiap list independen WAJIB numbering style sendiri agar mulai dari 1.
$levelPoin = [[
    'format' => 'decimal', 'text' => '%1.', 'alignment' => Jc::START,
    'left' => 720, 'hanging' => 360, 'tabPos' => 720,
]];
$phpWord->addNumberingStyle('numPoin', ['type' => 'singleLevel', 'levels' => $levelPoin]);
$phpWord->addNumberingStyle('numPoinLingkup', [
    'type' => 'singleLevel',
    'levels' => $levelPoin,
]);
// Penomoran judul BAB terhubung ke style (multilevel): lvl0 → "BAB 1.",
// lvl1 → "1.1." (counter lvl1 restart otomatis tiap lvl0 baru).
$phpWord->addNumberingStyle('numJudulBab', [
    'type' => 'multilevel',
    'levels' => [
        [
            'format' => 'decimal', 'text' => 'BAB %1.', 'alignment' => Jc::START,
            'left' => 0, 'hanging' => 0, 'tabPos' => 0, 'suffix' => 'space',
            'pStyle' => 'JudulBab1',
        ],
        [
            'format' => 'decimal', 'text' => '%1.%2.', 'alignment' => Jc::START,
            'left' => 720, 'hanging' => 720, 'tabPos' => 720, 'suffix' => 'tab',
            'pStyle' => 'JudulBab2',
        ],
    ],
]);

foreach ([1 => 0, 2 => 360] as $level => $indent) {
    $phpWord->addParagraphStyle('TOC'.$level, [
        'spaceAfter' => 80, 'indentation' => ['left' => $indent],
        'tabs' => [new Tab('right', CONTENT_WIDTH, 'dot')],
    ]);
}

$section = $phpWord->addSection([
    'paperSize' => 'A4',
    'marginTop' => 1440, 'marginBottom' => 1440, 'marginLeft' => 1440, 'marginRight' => 1440,
]);

$footer = $section->addFooter();
$footer->addText('Laporan Bulanan ${nama_kapal} — ${bulan_label}', ['size' => 8, 'color' => '7F7F7F'], ['alignment' => Jc::END]);
$footer->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['size' => 8, 'color' => '7F7F7F'], ['alignment' => Jc::END]);

$tableStyle = [
    'borderSize' => 4, 'borderColor' => BORDER, 'cellMarginLeft' => 80, 'cellMarginRight' => 80,
    'cellMarginTop' => 40, 'cellMarginBottom' => 40, 'alignment' => JcTable::CENTER,
    'layout' => 'fixed', 'unit' => TblWidth::TWIP, 'width' => CONTENT_WIDTH,
];
$head = ['bgColor' => LIGHT, 'valign' => 'center'];
$headFont = ['bold' => true, 'color' => NAVY, 'size' => 9];
$center = ['alignment' => Jc::CENTER];
$justify = ['alignment' => Jc::BOTH, 'spacing' => 60];
$tableIntro = ['lineHeight' => 1.15, 'spaceBefore' => 120, 'spaceAfter' => 120, 'keepNext' => true];
$body = ['valign' => 'center'];

$p = fn (string $text, array $font = [], array $para = []) => $section->addText($text, $font, $para);

// ══ COVER ═════════════════════════════════════════════════════════════════
$section->addTextBreak(3);
$p('LAPORAN BULANAN', ['bold' => true, 'size' => 28, 'color' => NAVY], $center);
$p('${bulan_label}', ['bold' => true, 'size' => 16, 'color' => NAVY], $center + ['spaceBefore' => 160]);
$section->addTextBreak(3);
$p('PELAKSANAAN PEKERJAAN', ['bold' => true, 'size' => 12], $center);
$p('${nama_pekerjaan}', ['bold' => true, 'size' => 12], $center + ['spaceBefore' => 60]);
$section->addTextBreak(5);

$cover = $section->addTable($tableStyle + ['borderSize' => 0, 'borderColor' => 'FFFFFF']);
$cover->addRow();
$c = $cover->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('DISAMPAIKAN KEPADA PEMILIK / OWNER :', ['bold' => true, 'size' => 9, 'color' => NAVY], $center);
$c->addText('${perusahaan}', ['bold' => true, 'size' => 11], $center + ['spaceBefore' => 120]);
$c->addText('${perusahaan_alamat}', ['size' => 9], $center + ['spaceBefore' => 60]);
$c = $cover->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('DISUSUN OLEH KONSULTAN PENGAWAS :', ['bold' => true, 'size' => 9, 'color' => NAVY], $center);
$c->addText('PT. BIRO KLASIFIKASI INDONESIA (PERSERO)', ['bold' => true, 'size' => 11], $center + ['spaceBefore' => 120]);
$c->addText('SBU MARINE & OFFSHORE MIGAS', ['size' => 9], $center);
$section->addPageBreak();

// ══ LEMBAR PENGESAHAN ══════════════════════════════════════════════════════
$section->addTextBreak(1);
$section->addText('LEMBAR PENGESAHAN', ['bold' => true, 'size' => 16, 'color' => NAVY], 'JudulBab0C');
$p('LAPORAN BULANAN', ['bold' => true, 'size' => 12], $center + ['spaceBefore' => 240]);
$p('${bulan_label}', ['bold' => true, 'size' => 12], $center);
$p('CUT OFF DATE. ${cutoff}', ['bold' => true, 'size' => 11], $center + ['spaceBefore' => 120]);
$section->addTextBreak(1);
$p('PELAKSANAAN PEKERJAAN', ['bold' => true, 'size' => 11], $center);
$p('${nama_pekerjaan}', ['bold' => true, 'size' => 11], $center + ['spaceBefore' => 60]);
$section->addTextBreak(4);

$sign = $section->addTable($tableStyle + ['borderSize' => 0, 'borderColor' => 'FFFFFF']);
$sign->addRow();
$c = $sign->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('${ttd1_label}', ['bold' => true], $center);
$c->addTextBreak(4);
$c->addText('${ttd1_nama}', ['bold' => true, 'underline' => 'single'], $center);
$c->addText('${ttd1_jabatan}', ['size' => 9], $center);
$c->addText('${ttd1_instansi}', ['size' => 9], $center);
$c = $sign->addCell(CONTENT_WIDTH / 2, $body);
$c->addText('${ttd2_label}', ['bold' => true], $center);
$c->addTextBreak(4);
$c->addText('${ttd2_nama}', ['bold' => true, 'underline' => 'single'], $center);
$c->addText('${ttd2_jabatan}', ['size' => 9], $center);
$c->addText('${ttd2_instansi}', ['size' => 9], $center);
$section->addPageBreak();

// ══ DAFTAR ISI ═════════════════════════════════════════════════════════════
$section->addText('DAFTAR ISI', 'JudulBabF1', 'JudulBab0');
// Field TOC ditulis di post-process: addTOC() PhpWord menulis instruksi field
// hanya bila ada elemen Title (kami pakai addText+style JudulBab agar heading
// mingguan di altChunk tidak ikut daftar isi).
$section->addText('TOC_FIELD_PLACEHOLDER');
$section->addPageBreak();

// ══ KATA PENGANTAR ═════════════════════════════════════════════════════════
$section->addText('KATA PENGANTAR', 'JudulBabF1', 'JudulBab0');
$p('Laporan bulan ini merupakan rangkuman dari keseluruhan aktivitas, yaitu mendampingi ${perusahaan} dalam proses pekerjaan review design dan supervisi proses fabrikasi / pembangunan ${nama_pekerjaan}, yang dilaksanakan di galangan kapal ${galangan} untuk periode ${periode_label}.', [], $justify);
$p('Laporan ini ditujukan kepada ${perusahaan}, terdiri atas:', [], $justify + ['spaceBefore' => 120]);
$section->addListItem('Laporan Progress Proses Kajian dan Persetujuan Gambar Design dari galangan oleh Owner Consultant;', 0, [], 'numPoin', $justify);
$section->addListItem('Laporan Mingguan Supervisi Proses Fabrikasi / Pembangunan;', 0, [], 'numPoin', $justify);
$section->addListItem('Laporan Progress Fisik dan S-Curve Rencana dan Realisasi;', 0, [], 'numPoin', $justify);
$p('Diharapkan laporan ini dapat memberikan gambaran pencapaian progress pembangunan pada periode ${bulan_label} dalam rangka ${nama_pekerjaan}.', [], $justify + ['spaceBefore' => 120]);
$p('Ucapan terima kasih ditujukan kepada pihak ${perusahaan} dan pihak Project Manager ${galangan} atas kerjasamanya, khususnya dalam mempersiapkan Laporan Bulanan ini.', [], $justify + ['spaceBefore' => 120]);
$p('Demikian laporan ini dibuat untuk dipergunakan sebagaimana mestinya.', [], $justify + ['spaceBefore' => 120]);
$section->addTextBreak(2);
$p('${ttd_kota}, ${ttd_tanggal}', [], ['alignment' => Jc::END]);
$p('PT. BIRO KLASIFIKASI INDONESIA (Persero)', [], ['alignment' => Jc::END]);
$p('SBU MARINE & OFFSHORE MIGAS', [], ['alignment' => Jc::END]);
$section->addTextBreak(3);
$p('${ttd1_nama}', ['bold' => true, 'underline' => 'single'], ['alignment' => Jc::END]);
$section->addPageBreak();

// ══ EXECUTIVE SUMMARY ══════════════════════════════════════════════════════
$section->addText('EXECUTIVE SUMMARY', 'JudulBabF1', 'JudulBab0');
$p('${exec_summary}', [], $justify);
$section->addTextBreak(1);
$p('${tabel_exec_progress}');
$section->addTextBreak(1);
$p('Keterangan :', ['size' => 9]);
$p('Kemajuan ( + ) : Progress Maju.', ['size' => 9]);
$p('Kemajuan ( - ) : Progress Mundur.', ['size' => 9]);
$section->addText('INFORMASI UMUM', 'JudulBabF2', ['spaceBefore' => 160, 'spaceAfter' => 80, 'keepNext' => true]);
$p('${tabel_info_umum}');
$section->addPageBreak();

// ══ BAB 1 — PENDAHULUAN ════════════════════════════════════════════════════
$section->addText('PENDAHULUAN', 'JudulBabF1', 'JudulBab1');
$section->addText('LATAR BELAKANG', 'JudulBabF2', 'JudulBab2');
$p('${perusahaan} memberikan pekerjaan ${nama_pekerjaan} kepada pihak Konsultansi yaitu PT. Biro Klasifikasi Indonesia (Persero) SBU Marine & Offshore Migas, berdasarkan Surat Perjanjian Nomor : ${nomor_kontrak}.', [], $justify);
$section->addText('MAKSUD DAN TUJUAN', 'JudulBabF2', 'JudulBab2');
$p('Maksud dari pekerjaan ${nama_pekerjaan} adalah melakukan pengawasan dalam hal teknis maupun administratif selama proses pembangunan kapal berdasarkan spesifikasi teknis dan kontrak pembangunan kapal yang telah disepakati antara owner dan penyedia/pelaksana pembangunan kapal, dengan tetap memperhatikan regulasi yang terkait pembangunan kapal; serta memastikan dan memberikan masukan/saran, rekomendasi, alternatif inovasi, pemecahan masalah dan tindakan korektif yang tepat pada semua proses pembangunan kapal tersebut.', [], $justify);
$p('Tujuan dari kegiatan pekerjaan ini adalah agar proses pembangunan kapal dapat berjalan dengan tepat waktu, tepat biaya, dan tepat kualitas pekerjaan pembangunan sesuai kontrak, serta tersusunnya administrasi proses pembangunan dari awal sampai serah terima pekerjaan secara rapi, lengkap, dan terdokumentasi dengan baik.', [], $justify + ['spaceBefore' => 80]);
$section->addText('LINGKUP PEKERJAAN', 'JudulBabF2', 'JudulBab2');
$p('Dalam melaksanakan pekerjaan ${nama_pekerjaan}, konsultan pengawas mempunyai tugas sebagai berikut:', [], $justify);
foreach ([
    'Melakukan reviu dan memberikan rekomendasi masukan baik berupa gambar teknis, justifikasi teknis (pemilihan merk maupun material), perhitungan teknis maupun kebutuhan biaya kepada Owner terkait pelaksanaan pekerjaan pembangunan kapal.',
    'Melaksanakan rapat awal persiapan pekerjaan pembangunan kapal (Kick Off Meeting), rapat berkala dengan materi laporan harian, mingguan atau bulanan berdasarkan realisasi kegiatan pembangunan kapal dan rapat akhir (Final Meeting).',
    'Meneliti dan memeriksa gambar, perhitungan, spesifikasi dan lainnya untuk kemudian apabila dianggap perlu memberikan rekomendasi dan koreksi ke galangan yang bersangkutan, atas persetujuan Owner selama proses pembangunan kapal.',
    'Memeriksa semua material/barang, dokumen-dokumen, mesin-mesin dan perlengkapan kapal yang diperlukan atau dibeli untuk pembangunan kapal.',
    'Mengawasi semua pengajuan progress pekerjaan pembangunan kapal secara keseluruhan dari segi kualitas, kuantitas, dan laju pencapaian volume sesuai dengan realisasi pekerjaan fisik.',
    'Melakukan koordinasi terhadap pihak eksternal maupun pihak terkait baik itu badan klasifikasi maupun Marine Inspector selama proses pembangunan kapal sampai serah terima pada Owner.',
    'Melaporkan kepada Owner semua kekurangan-kekurangan dalam pembangunan kapal baik dalam hal kualitas maupun volume pekerjaannya.',
    'Memeriksa gambar as built drawing yang dibuat oleh galangan yang menunjukkan hasil akhir dari pekerjaan pembangunan kapal secara lengkap.',
] as $lingkup) {
    $section->addListItem($lingkup, 0, [], 'numPoinLingkup', $justify);
}
$section->addPageBreak();

// ══ BAB 2 — {NAMA KAPAL} ═══════════════════════════════════════════════════
$section->addText('${nama_kapal}', 'JudulBabF1', 'JudulBab1');

$section->addText('UMUM', 'JudulBabF2', 'JudulBab2');
$p('Secara umum spesifikasi dan ukuran utama dari kapal adalah sebagai berikut :', [], $tableIntro + ['alignment' => Jc::BOTH]);
$p('${tabel_spesifikasi}');

$section->addText('STATUS DESIGN & APPROVAL DRAWING', 'JudulBabF2', 'JudulBab2');
$p('Proses Penyelesaian Basic Design & Production Drawing Oleh Galangan', ['bold' => true, 'size' => 9], $tableIntro);
$p('${tabel_drawing_galangan}');
$p('Review Drawing oleh Konsultansi Pengawas (MNO)', ['bold' => true, 'size' => 9], $tableIntro);
$p('${tabel_drawing_mno}');
$p('Review / Approval Drawing oleh Klas', ['bold' => true, 'size' => 9], $tableIntro);
$p('${tabel_drawing_klas}');

$section->addText('REALISASI PROGRESS PEKERJAAN', 'JudulBabF2', 'JudulBab2');
$p('${tabel_realisasi}');

$section->addText('DAFTAR SUB-CONTRACTOR, VENDOR', 'JudulBabF2', 'JudulBab2');
$p('${tabel_vendor}');

$section->addText('MATERIAL DAN KOMPONEN', 'JudulBabF2', 'JudulBab2');
$p('${tabel_material}');

$section->addText('RENCANA DAN REALISASI WAKTU PELAKSANAAN PEMBANGUNAN', 'JudulBabF2', 'JudulBab2');
$p('${tabel_waktu}');
$p('Presentase Bobot penyelesaian', ['bold' => true, 'size' => 9], $tableIntro);
$p('Total progress yang telah dicapai dalam pekerjaan ${nama_pekerjaan} sampai dengan ${cutoff} adalah sebagai berikut :', [], $tableIntro + ['alignment' => Jc::BOTH]);
$p('${tabel_progress_mingguan}');

$section->addText('PENGUJIAN', 'JudulBabF2', 'JudulBab2');
$p('${tabel_pengujian}');
$section->addPageBreak();

// ══ BAB 3 — PENUTUP ════════════════════════════════════════════════════════
$section->addText('PENUTUP', 'JudulBabF1', 'JudulBab1');
$section->addText('RAPAT BULANAN / MINGGUAN', 'JudulBabF2', 'JudulBab2');
$p('${tabel_rapat}');
$section->addText('PROBLEM DAN REKOMENDASI', 'JudulBabF2', 'JudulBab2');
// Problem & rekomendasi = tarikan otomatis rekap NC periode (open + closed).
$p('${tabel_nc}');
$section->addPageBreak();

// ══ BAB 4 — LAMPIRAN ═══════════════════════════════════════════════════════
$section->addText('LAMPIRAN', 'JudulBabF1', 'JudulBab1');
$section->addText('DOKUMEN PENDUKUNG', 'JudulBabF2', 'JudulBab2');
$p('${tabel_external}');
$section->addText('LAPORAN MINGGUAN', 'JudulBabF2', 'JudulBab2');
$p('${tabel_lampiran_mingguan}');
// Diganti service dengan halaman pembatas + isi dokumen eksternal (gambar + halaman PDF)
$p('${lampiran_external}');
// Diganti service dengan halaman pembatas + altChunk docx laporan mingguan (tampilan identik template mingguan)
$p('${lampiran_mingguan}', ['italic' => true, 'color' => '7F7F7F']);

WordStyleService::prepare($phpWord);
$phpWord->save($path, 'Word2007');

// ── Post-process ─────────────────────────────────────────────────────────
$z = new ZipArchive;
$z->open($path);
$xml = $z->getFromName('word/document.xml');
// Beri atribut pada <w:tr> agar TemplateProcessor::findRowStart()
// (mencari '<w:tr ' dengan spasi) menemukan baris yang benar saat cloneRow.
$xml = str_replace('<w:tr>', '<w:tr w:rsidR="00C0FFEE">', $xml);
// Field TOC lengkap: \t "nama-style,level" hanya menjaring paragraf bergaya
// JudulBab* → heading laporan mingguan (altChunk) tidak ikut; field di-update
// Word saat dibuka (updateFields=true di bawah) → nomor halaman riil.
$xml = preg_replace(
    '/<w:p>(?:(?!<w:p>).)*TOC_FIELD_PLACEHOLDER.*?<\/w:p>/s',
    '<w:p><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>'
    .'<w:r><w:instrText xml:space="preserve"> TOC \t &quot;JudulBab0;1;JudulBab0C;1;JudulBab1;1;JudulBab2;2&quot; \h \z </w:instrText></w:r>'
    .'<w:r><w:fldChar w:fldCharType="separate"/></w:r></w:p>'
    .'<w:p><w:r><w:t xml:space="preserve">Klik kanan → Update Field untuk memuat daftar isi.</w:t></w:r></w:p>'
    .'<w:p><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>',
    $xml, 1
);
$document = new DOMDocument;
$document->loadXML($xml);
$documentXPath = new DOMXPath($document);
$wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
$documentXPath->registerNamespace('w', $wordNamespace);
foreach ($documentXPath->query('//w:p[w:pPr/w:pStyle[@w:val="JudulBab1"]]') as $heading) {
    $text = $documentXPath->query('w:r/w:t', $heading)->item(0);
    if ($text !== null) {
        $text->parentNode->insertBefore($document->createElementNS($wordNamespace, 'w:br'), $text);
    }
}
$tocResult = $documentXPath->query('//w:p[w:r/w:t="Klik kanan → Update Field untuk memuat daftar isi."]')->item(0);
$chapter = 0;
$subchapter = 0;
$bookmarkId = 0;
foreach ($documentXPath->query('//w:bookmarkStart') as $bookmark) {
    $bookmarkId = max($bookmarkId, (int) $bookmark->getAttributeNS($wordNamespace, 'id') + 1);
}
$headings = $documentXPath->query('//w:body/w:p[w:pPr/w:pStyle[@w:val="JudulBab0" or @w:val="JudulBab0C" or @w:val="JudulBab1" or @w:val="JudulBab2"]]');
foreach ($headings as $index => $heading) {
    $style = $documentXPath->query('w:pPr/w:pStyle', $heading)->item(0)->getAttributeNS($wordNamespace, 'val');
    $level = $style === 'JudulBab2' ? 2 : 1;
    $prefix = '';
    if ($style === 'JudulBab1') {
        $chapter++;
        $subchapter = 0;
        $prefix = 'BAB '.$chapter.'. ';
    } elseif ($style === 'JudulBab2') {
        $prefix = $chapter.'.'.++$subchapter.'. ';
    }
    $label = $prefix;
    foreach ($documentXPath->query('.//w:t', $heading) as $text) {
        $label .= $text->textContent;
    }
    $anchor = '_MonthlyToc'.($index + 1);
    $start = $document->createElementNS($wordNamespace, 'w:bookmarkStart');
    $start->setAttributeNS($wordNamespace, 'w:id', (string) $bookmarkId);
    $start->setAttributeNS($wordNamespace, 'w:name', $anchor);
    $properties = $documentXPath->query('w:pPr', $heading)->item(0);
    $heading->insertBefore($start, $properties->nextSibling);
    $end = $document->createElementNS($wordNamespace, 'w:bookmarkEnd');
    $end->setAttributeNS($wordNamespace, 'w:id', (string) $bookmarkId++);
    $heading->appendChild($end);
    $escapedLabel = htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $entry = $document->createDocumentFragment();
    $entry->appendXML('<w:p xmlns:w="'.$wordNamespace.'"><w:pPr><w:pStyle w:val="TOC'.$level.'"/></w:pPr>'
        .'<w:hyperlink w:anchor="'.$anchor.'" w:history="1"><w:r><w:t xml:space="preserve">'.$escapedLabel.'</w:t></w:r>'
        .'<w:r><w:tab/></w:r><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>'
        .'<w:r><w:instrText xml:space="preserve"> PAGEREF '.$anchor.' \\h </w:instrText></w:r>'
        .'<w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>—</w:t></w:r>'
        .'<w:r><w:fldChar w:fldCharType="end"/></w:r></w:hyperlink></w:p>');
    $tocResult->parentNode->insertBefore($entry, $tocResult);
}
$tocResult->parentNode->removeChild($tocResult);
$z->addFromString('word/document.xml', $document->saveXML());
// updateFields agar Daftar Isi (TOC) terisi otomatis saat dokumen dibuka Word.
$settings = $z->getFromName('word/settings.xml');
if ($settings && str_contains($settings, '<w:updateFields w:val="false"/>')) {
    $settings = str_replace('<w:updateFields w:val="false"/>', '<w:updateFields w:val="true"/>', $settings);
}
$settingsDocument = new DOMDocument;
$settingsDocument->loadXML($settings);
$settingsXPath = new DOMXPath($settingsDocument);
$settingsXPath->registerNamespace('w', $wordNamespace);
$view = $settingsXPath->query('/w:settings/w:view')->item(0);
if ($view === null) {
    $view = $settingsDocument->createElementNS($wordNamespace, 'w:view');
    $settingsDocument->documentElement->insertBefore($view, $settingsDocument->documentElement->firstChild);
}
$view->setAttributeNS($wordNamespace, 'w:val', 'print');
$z->addFromString('word/settings.xml', $settingsDocument->saveXML());
// Cari numId yang PhpWord berikan ke numbering 'numJudulBab' (abstractNum
// yang berisi lvlText "BAB %1.") — dipakai untuk suntik numPr ke style.
$babNumId = null;
$numbering = $z->getFromName('word/numbering.xml');
$numberingDocument = new DOMDocument;
$numberingDocument->loadXML($numbering);
$numberingXPath = new DOMXPath($numberingDocument);
$numberingXPath->registerNamespace('w', $wordNamespace);
foreach ($numberingXPath->query('//w:lvl[w:pStyle[@w:val="JudulBab1" or @w:val="JudulBab2"]]/w:rPr') as $properties) {
    foreach (['b' => null, 'color' => '1F4E79', 'sz' => $properties->parentNode->getAttributeNS($wordNamespace, 'ilvl') === '0' ? '40' : '26'] as $name => $value) {
        $property = $numberingDocument->createElementNS($wordNamespace, 'w:'.$name);
        if ($value !== null) {
            $property->setAttributeNS($wordNamespace, 'w:val', $value);
        }
        $properties->appendChild($property);
    }
}
$numbering = $numberingDocument->saveXML();
$z->addFromString('word/numbering.xml', $numbering);
if ($numbering
    && preg_match('/<w:abstractNum w:abstractNumId="(\d+)"[^>]*>(?:(?!<\/w:abstractNum>).)*BAB %1\..*?<\/w:abstractNum>/s', $numbering, $absMatch)
    && preg_match('/<w:num w:numId="(\d+)"[^>]*>\s*<w:abstractNumId w:val="'.$absMatch[1].'"/', $numbering, $numMatch)) {
    $babNumId = $numMatch[1];
}
// Link balik style → numId (pola style Heading bawaan Word): pStyle di w:lvl
// mengaitkan numbering ke style, numPr di style mengikat level-nya. Tanpa
// numPr, sebagian renderer mengabaikan penomoran judul.
$styles = $z->getFromName('word/styles.xml');
$styles = preg_replace_callback(
    '/<w:style [^>]*w:styleId="(JudulBab0C?|JudulBab[12])"[^>]*>.*?<\/w:style>/s',
    function ($m) use ($babNumId) {
        $style = $m[0];
        $lvl = match ($m[1]) {
            'JudulBab2' => 1,
            default => 0,
        };
        // Suntik numPr ke JudulBab1 (ilvl 0) & JudulBab2 (ilvl 1) — posisi
        // sebelum pBdr/shd/spacing/ind/jc/rPr agar urutan anak w:pPr valid.
        if ($babNumId !== null && $m[1] !== 'JudulBab0' && $m[1] !== 'JudulBab0C'
            && ! str_contains($style, '<w:numPr>')) {
            $numPr = '<w:numPr><w:ilvl w:val="'.$lvl.'"/><w:numId w:val="'.$babNumId.'"/></w:numPr>';
            if (preg_match('/<w:(pBdr|shd|tabs|suppressAutoHyphens|spacing|ind|contextualSpacing|jc|textAlignment|outlineLvl|sectPr|rPr)\b/', $style, $mm, PREG_OFFSET_CAPTURE)) {
                $style = substr_replace($style, $numPr, $mm[0][1], 0);
            } else {
                $style = str_replace('</w:pPr>', $numPr.'</w:pPr>', $style);
            }
        }
        // outlineLvl agar entry Daftar Isi & panel navigasi mengenali heading.
        if (! str_contains($style, '<w:outlineLvl')) {
            if (str_contains($style, '<w:pPr>')) {
                return preg_replace('/<\/w:pPr>/', '<w:outlineLvl w:val="'.$lvl.'"/></w:pPr>', $style, 1);
            }

            return preg_replace('/<w:rPr>/', '<w:pPr><w:outlineLvl w:val="'.$lvl.'"/></w:pPr><w:rPr>', $style, 1);
        }

        return $style;
    },
    $styles
);
$styleDocument = new DOMDocument;
$styleDocument->loadXML($styles);
$styleXPath = new DOMXPath($styleDocument);
$styleXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$propertyOrder = array_flip(['keepNext', 'keepLines', 'pageBreakBefore', 'numPr', 'pBdr', 'shd', 'tabs', 'spacing', 'ind', 'contextualSpacing', 'jc', 'outlineLvl']);
foreach ($styleXPath->query('//w:style[starts-with(@w:styleId, "JudulBab")]/w:pPr') as $properties) {
    $children = iterator_to_array($properties->childNodes);
    usort($children, fn ($a, $b) => ($propertyOrder[$a->localName] ?? 99) <=> ($propertyOrder[$b->localName] ?? 99));
    foreach ($children as $child) {
        $properties->appendChild($child);
    }
}
foreach (['JudulBab1' => 40, 'JudulBab2' => 26] as $styleId => $size) {
    $headingStyle = $styleXPath->query('//w:style[@w:styleId="'.$styleId.'"]')->item(0);
    $runProperties = $styleDocument->createElementNS($wordNamespace, 'w:rPr');
    $runProperties->appendChild($styleDocument->createElementNS($wordNamespace, 'w:b'));
    $color = $styleDocument->createElementNS($wordNamespace, 'w:color');
    $color->setAttributeNS($wordNamespace, 'w:val', '1F4E79');
    $runProperties->appendChild($color);
    $fontSize = $styleDocument->createElementNS($wordNamespace, 'w:sz');
    $fontSize->setAttributeNS($wordNamespace, 'w:val', (string) $size);
    $runProperties->appendChild($fontSize);
    $headingStyle->appendChild($runProperties);
}
$z->addFromString('word/styles.xml', $styleDocument->saveXML());
$z->close();

echo "Template bulanan dibuat: {$path}\n";
