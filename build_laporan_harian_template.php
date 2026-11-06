<?php

/**
 * Normalisasi template-laporan-harian.docx (idempoten — aman dijalankan ulang).
 *
 *  - Font seragam Arial 10pt: semua w:rFonts run dihapus (ikut default dokumen),
 *    default & style di styles.xml diset Arial, ukuran run diratakan 10pt.
 *  - Blok info (Pekerjaan, Lokasi, Pemberi Tugas, Konsultan Pengawas, Hull Number)
 *    yang sebelumnya dirapikan dengan tab → tabel tanpa garis 3 kolom
 *    (label | : | nilai) agar titik dua sejajar vertikal.
 *  - Judul section berhuruf, tebal, biru tua 11pt: A. Kondisi Cuaca, B. Personel,
 *    C. Peralatan, D. Consumable dan Material, E. Uraian Aktivitas,
 *    F. Lampiran: Dokumentasi. Auto-numbering Word (w:numPr) pada judul section &
 *    judul kategori dilepas — huruf/nomor ditulis literal agar tidak tampil ganda
 *    dan tidak melanjutkan hitungan antar-dokumen saat disisipkan via altChunk.
 *  - Tabel Kondisi Cuaca memakai lebar persen (100%) seperti tabel Personel/
 *    Peralatan/Consumable agar tidak menyusut mengikuti isi sel.
 *  - Warna header tabel biru tua 1F3864 (sama dengan Laporan Mingguan), grid foto D9E2F3.
 *
 * Jalankan: php build_laporan_harian_template.php
 */
$path = 'storage/app/templates/laporan-harian/template-laporan-harian.docx';
const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
const NAVY = '1F3864';
const FONT = 'Arial';

$z = new ZipArchive;
if (! is_file($path) || $z->open($path) !== true) {
    fwrite(STDERR, "Template tidak ditemukan / gagal dibuka: {$path}\n");
    exit(1);
}

$normalizeRuns = function (string $xml): string {
    $xml = preg_replace('/<w:rFonts\b[^>]*\/>/', '', $xml);
    $xml = preg_replace('/<w:sz w:val="\d+"\/>/', '<w:sz w:val="20"/>', $xml);

    return preg_replace('/<w:szCs w:val="\d+"\/>/', '<w:szCs w:val="20"/>', $xml);
};

// ── styles.xml: default & semua style → Arial 10pt ────────────────────────
$fontTag = '<w:rFonts w:ascii="'.FONT.'" w:hAnsi="'.FONT.'" w:eastAsia="'.FONT.'" w:cs="'.FONT.'"/>';
$styles = preg_replace('/<w:rFonts\b[^>]*\/>/', $fontTag, $z->getFromName('word/styles.xml'));
$styles = preg_replace_callback('/<w:docDefaults>.*?<\/w:docDefaults>/s', fn ($m) => preg_replace(
    ['/<w:sz w:val="\d+"\/>/', '/<w:szCs w:val="\d+"\/>/'],
    ['<w:sz w:val="20"/>', '<w:szCs w:val="20"/>'],
    $m[0]
), $styles);

// ── header: ikut font default ─────────────────────────────────────────────
$header = preg_replace('/<w:rFonts\b[^>]*\/>/', '', $z->getFromName('word/header1.xml'));

// ── document.xml ──────────────────────────────────────────────────────────
$xml = $normalizeRuns($z->getFromName('word/document.xml'));
$xml = str_ireplace(['w:fill="0070C0"', 'w:fill="DEEAF6"'], ['w:fill="'.NAVY.'"', 'w:fill="D9E2F3"'], $xml);

$doc = new DOMDocument;
$doc->preserveWhiteSpace = true;
$doc->loadXML($xml);
$xp = new DOMXPath($doc);
$xp->registerNamespace('w', W);
$text = fn (DOMNode $n) => trim(preg_replace('/\s+/u', ' ', $n->textContent));

// 1) Judul section: berhuruf + gaya seragam
$titles = [
    'Kondisi Cuaca' => 'A', 'Personel' => 'B', 'Peralatan' => 'C',
    'Consumable dan Material' => 'D', 'Uraian Aktivitas' => 'E', 'Lampiran: Dokumentasi' => 'F',
];
foreach ($xp->query('/w:document/w:body/w:p') as $p) {
    $base = preg_replace('/^[A-F]\.\s*/', '', $text($p));
    $isTitle = isset($titles[$base]);
    if (! $isTitle && $text($p) !== '${aktivitas_kategori}') {
        continue;
    }
    // Hapus auto-numbering Word: huruf/nomor sudah literal di teks. Tanpa ini
    // judul tampil ganda ("A. A. ...") dan numbering LANJUT antar dokumen saat
    // docx disisipkan via altChunk (laporan harian ke-2 mulai dari "F").
    foreach ($xp->query('w:pPr/w:numPr|w:pPr/w:ind|w:pPr/w:pStyle', $p) as $n) {
        $n->parentNode->removeChild($n);
    }
    if (! $isTitle) {
        continue;
    }
    // Bookmark dibiarkan (pasangan bookmarkEnd bisa berada di luar paragraf)
    foreach ($xp->query('w:r|w:proofErr', $p) as $old) {
        $p->removeChild($old);
    }
    $r = $doc->createElementNS(W, 'w:r');
    $rPr = $r->appendChild($doc->createElementNS(W, 'w:rPr'));
    $rPr->appendChild($doc->createElementNS(W, 'w:b'));
    $rPr->appendChild($doc->createElementNS(W, 'w:color'))->setAttributeNS(W, 'w:val', NAVY);
    $rPr->appendChild($doc->createElementNS(W, 'w:sz'))->setAttributeNS(W, 'w:val', '22');
    $rPr->appendChild($doc->createElementNS(W, 'w:szCs'))->setAttributeNS(W, 'w:val', '22');
    $t = $r->appendChild($doc->createElementNS(W, 'w:t'));
    $t->textContent = $titles[$base].'. '.$base;
    $p->appendChild($r);
}

// 1b) Tabel Kondisi Cuaca: lebar persen (5000 = 100%) seperti tabel B-D.
// tblW dxa + autofit membuat Word menyusutkan tabel mengikuti isi sel
// (tampak lebih pendek dari tabel lain). gridCol tetap dxa.
$afterCuacaTitle = false;
foreach ($xp->query('/w:document/w:body/*') as $node) {
    if ($node->localName === 'p' && str_contains($text($node), 'Kondisi Cuaca')) {
        $afterCuacaTitle = true;

        continue;
    }
    if ($node->localName !== 'tbl' || ! $afterCuacaTitle) {
        continue;
    }
    $gridTotal = 0;
    foreach ($xp->query('w:tblGrid/w:gridCol', $node) as $g) {
        $gridTotal += (int) $g->getAttributeNS(W, 'w');
    }
    if ($gridTotal <= 0) {
        break;
    }
    $tblW = $xp->query('w:tblPr/w:tblW', $node)->item(0);
    $tblW->setAttributeNS(W, 'w:w', '5000');
    $tblW->setAttributeNS(W, 'w:type', 'pct');
    $scale = 5000 / $gridTotal;
    foreach ($xp->query('.//w:tcPr/w:tcW[@w:type="dxa"]', $node) as $tcW) {
        $tcW->setAttributeNS(W, 'w:w', (string) max(1, (int) round(((int) $tcW->getAttributeNS(W, 'w')) * $scale)));
        $tcW->setAttributeNS(W, 'w:type', 'pct');
    }
    break;
}

// 1c) Hapus paragraf "Lampiran" stray (sisa heading lama) tepat sebelum judul F.
$prev = null;
foreach ($xp->query('/w:document/w:body/*') as $node) {
    if ($node->localName === 'p' && $prev?->localName === 'p'
        && $text($prev) === 'Lampiran' && $text($node) === 'F. Lampiran: Dokumentasi') {
        $prev->parentNode->removeChild($prev);
        break;
    }
    $prev = $node;
}

// 2) Ambil isi blok info (paragraf "Label<tab>: nilai") sebelum tabel pertama
$info = [];
foreach ($xp->query('/w:document/w:body/*') as $node) {
    if ($node->localName !== 'p') {
        break;
    }
    if (preg_match('/^([^:]+?)\s*:\s*(.*)$/u', $text($node), $m)) {
        $info[] = [$m[1], $m[2]];
    }
    if (str_starts_with($text($node), 'Hull Number')) {
        break;
    }
}

$xml = $doc->saveXML();

if ($info) {
    $esc = fn (string $s) => htmlspecialchars($s, ENT_XML1);
    $cell = fn (int $w, string $s, bool $bold = false, string $jc = 'left') => '<w:tc><w:tcPr><w:tcW w:w="'.$w.'" w:type="dxa"/></w:tcPr>'
        .'<w:p><w:pPr><w:spacing w:before="40" w:after="40"/><w:jc w:val="'.$jc.'"/></w:pPr>'
        .'<w:r><w:rPr>'.($bold ? '<w:b/>' : '').'<w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr><w:t xml:space="preserve">'.$esc($s).'</w:t></w:r></w:p></w:tc>';

    $rows = '';
    foreach ($info as [$label, $value]) {
        $rows .= '<w:tr w:rsidR="00C0FFEE">'.$cell(2600, $label, true).$cell(300, ':', false, 'center').$cell(6126, $value).'</w:tr>';
    }
    $table = '<w:tbl><w:tblPr><w:tblW w:w="9026" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        .'<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
        .'<w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar></w:tblPr>'
        .'<w:tblGrid><w:gridCol w:w="2600"/><w:gridCol w:w="300"/><w:gridCol w:w="6126"/></w:tblGrid>'.$rows.'</w:tbl>'
        .'<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';

    // Ganti paragraf awal body s.d. paragraf "Hull Number" (hanya bila masih berupa paragraf)
    $xml = preg_replace('/(<w:body>)(?:(?!<w:tbl\b).)*?Hull Number.*?<\/w:p>/s', '$1'.$table, $xml, 1);
}

$z->addFromString('word/document.xml', $xml);
$z->addFromString('word/styles.xml', $styles);
$z->addFromString('word/header1.xml', $header);
$z->close();

echo "Template harian dinormalisasi: {$path}\n";
