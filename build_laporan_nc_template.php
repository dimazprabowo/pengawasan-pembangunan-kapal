<?php

/**
 * Bangun template-laporan-nc.docx dari master NC.docx (idempoten — selalu
 * dibangun ulang dari master, aman dijalankan berkali-kali).
 *
 * Master : storage/app/templates/laporan-nc/master-nc.docx (salinan NC.docx asli)
 * Output : storage/app/templates/laporan-nc/template-laporan-nc.docx
 *
 *  - Kop: "Nama Proyek" → ${nama_proyek}, judul → ${judul_form}, "NC - ." → ${nomor_ncr}.
 *  - Tabel NC: header dipertahankan, 14 baris kosong → 1 baris placeholder
 *    (${nc_no} ${nc_masalah} ${nc_tanggal} ${nc_pic} ${nc_status} ${nc_selesai} ${nc_tindakan})
 *    yang di-cloneRow oleh LaporanNcWordService. Kolom "No" dilebarkan karena
 *    diisi nomor NC (NC-001), bukan nomor urut.
 *  - Halaman Lampiran dibungkus ${blok_lampiran}…${/blok_lampiran}; tabel foto
 *    → 1 baris placeholder ${foto_no} ${foto_gambar} ${foto_keterangan}.
 *  - Tanda tangan (Owner / Konsultan Pengawas / Galangan) & format asli tidak diubah.
 *
 * Jalankan: php build_laporan_nc_template.php
 */
const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$master = 'storage/app/templates/laporan-nc/master-nc.docx';
$output = 'storage/app/templates/laporan-nc/template-laporan-nc.docx';

$src = new ZipArchive;
if (! is_file($master) || $src->open($master) !== true) {
    fwrite(STDERR, "Master tidak ditemukan / gagal dibuka: {$master}\n");
    exit(1);
}
$xml = $src->getFromName('word/document.xml');
$src->close();

$doc = new DOMDocument;
$doc->preserveWhiteSpace = true;
$doc->loadXML($xml);
$xp = new DOMXPath($doc);
$xp->registerNamespace('w', W);

$body = $xp->query('/w:document/w:body')->item(0);
$blocks = iterator_to_array($xp->query('./*', $body));
$tables = array_values(array_filter($blocks, fn ($n) => $n->localName === 'tbl'));

if (count($tables) < 4) {
    fwrite(STDERR, "Struktur master tidak dikenali (butuh 4 tabel: kop, NC, tanda tangan, lampiran).\n");
    exit(1);
}
[$kop, $ncTable, , $fotoTable] = $tables;

/** Isi sel dengan satu teks — format run teks pertama (atau rPr paragraf) dipertahankan. */
$setCell = function (DOMElement $tc, string $text) use ($xp, $doc) {
    $paras = iterator_to_array($xp->query('./w:p', $tc));
    $p = $paras[0];
    foreach (array_slice($paras, 1) as $extra) {
        $tc->removeChild($extra);
    }

    $rPr = $xp->query('.//w:r[w:t]/w:rPr', $p)->item(0) ?? $xp->query('./w:pPr/w:rPr', $p)->item(0);
    $rPr = $rPr?->cloneNode(true);

    foreach (iterator_to_array($xp->query('./*[not(self::w:pPr)]', $p)) as $child) {
        $p->removeChild($child);
    }

    $r = $doc->createElementNS(W, 'w:r');
    if ($rPr) {
        // rPr di pPr bisa berisi tag khusus paragraf — cukup salin sebagai format run
        $r->appendChild($doc->importNode($rPr, true));
    }
    $t = $doc->createElementNS(W, 'w:t', $text);
    $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
    $r->appendChild($t);
    $p->appendChild($r);
};

$cells = fn (DOMElement $tr) => iterator_to_array($xp->query('./w:tc', $tr));
$rows = fn (DOMElement $tbl) => iterator_to_array($xp->query('./w:tr', $tbl));

/** Sisakan header + 1 baris data berisi placeholder. */
$singleDataRow = function (DOMElement $tbl, array $placeholders) use ($rows, $cells, $setCell) {
    $all = $rows($tbl);
    foreach (array_slice($all, 2) as $extra) {
        $tbl->removeChild($extra);
    }
    foreach ($cells($all[1]) as $i => $tc) {
        $setCell($tc, $placeholders[$i] ?? '');
    }
};

/** Paragraf marker blok sederhana (cloneBlock butuh marker di paragraf sendiri). */
$marker = function (string $text) use ($doc) {
    $p = $doc->createElementNS(W, 'w:p');
    $r = $doc->createElementNS(W, 'w:r');
    $r->appendChild($doc->createElementNS(W, 'w:t', $text));
    $p->appendChild($r);

    return $p;
};

/** Ubah lebar satu kolom tabel (gridCol + tcW semua sel di kolom tsb). */
$setColWidth = function (DOMElement $tbl, int $colIndex, int $width) use ($xp, $rows, $cells, $doc) {
    $xp->query('./w:tblGrid/w:gridCol', $tbl)->item($colIndex)?->setAttribute('w:w', (string) $width);
    foreach ($rows($tbl) as $tr) {
        $tc = $cells($tr)[$colIndex] ?? null;
        if (! $tc) {
            continue;
        }
        $tcPr = $xp->query('./w:tcPr', $tc)->item(0);
        if (! $tcPr) {
            $tcPr = $doc->createElementNS(W, 'w:tcPr');
            $tc->insertBefore($tcPr, $tc->firstChild);
        }
        $tcW = $xp->query('./w:tcW', $tcPr)->item(0);
        if (! $tcW) {
            $tcW = $doc->createElementNS(W, 'w:tcW');
            $tcPr->insertBefore($tcW, $tcPr->firstChild);
        }
        $tcW->setAttribute('w:w', (string) $width);
        $tcW->setAttribute('w:type', 'dxa');
    }
};

// ── Kop ───────────────────────────────────────────────────────────────────
$kopRows = $rows($kop);
$setCell($cells($kopRows[2])[0], '${nama_proyek}');
$setCell($cells($kopRows[3])[0], '${judul_form}');
$setCell($cells($kopRows[4])[1], '${nomor_ncr}');

// ── Tabel NC ──────────────────────────────────────────────────────────────
$singleDataRow($ncTable, ['${nc_no}', '${nc_masalah}', '${nc_tanggal}', '${nc_pic}', '${nc_status}', '${nc_selesai}', '${nc_tindakan}']);

// Kolom "No" menampung nomor NC (NC-001) — dilebarkan 455 → 1100 twip,
// selisihnya diambil dari kolom Masalah (3925 → 3280); total lebar tetap.
$setColWidth($ncTable, 0, 1100);
$setColWidth($ncTable, 1, 3280);

// ── Lampiran: blok dari paragraf page-break sebelum judul s/d paragraf setelah tabel foto ──
$singleDataRow($fotoTable, ['${foto_no}', '${foto_gambar}', '${foto_keterangan}']);

$fotoIndex = array_search($fotoTable, $blocks, true);
$start = null;
for ($i = $fotoIndex - 1; $i >= 0; $i--) {
    if ($xp->query('.//w:br[@w:type="page"]', $blocks[$i])->length > 0) {
        $start = $blocks[$i];
        break;
    }
}
$start ??= $blocks[$fotoIndex - 1];
$end = $blocks[$fotoIndex + 1] ?? $fotoTable;

$body->insertBefore($marker('${blok_lampiran}'), $start);
$end->parentNode->insertBefore($marker('${/blok_lampiran}'), $end->nextSibling);

// ── Simpan: salin master lalu ganti document.xml ─────────────────────────
if (! copy($master, $output)) {
    fwrite(STDERR, "Gagal menyalin master ke {$output}\n");
    exit(1);
}
$out = new ZipArchive;
$out->open($output);
$out->addFromString('word/document.xml', $doc->saveXML());
$out->close();

echo "Template Form NC dibangun: {$output}\n";
