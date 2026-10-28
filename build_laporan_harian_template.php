<?php

/**
 * Build/retouch template-laporan-harian.docx
 *
 * Menormalisasi template Word Laporan Harian agar konsisten:
 *  - Judul section berhuruf: A. Kondisi Cuaca, B. Personel, C. Peralatan,
 *    D. Consumable dan Material, E. Uraian Aktivitas, F. Lampiran.
 *  - Warna header SEMUA tabel seragam (DEEAF6, sama seperti header grid foto).
 *  - Ukuran font body seragam (20 half-point = 10pt).
 *
 * Jalankan: php build_laporan_harian_template.php
 */

$path = 'storage/app/templates/laporan-harian/template-laporan-harian.docx';
if (! is_file($path)) {
    fwrite(STDERR, "Template tidak ditemukan: {$path}\n");
    exit(1);
}

$z = new ZipArchive;
if ($z->open($path) !== true) {
    fwrite(STDERR, "Gagal membuka docx.\n");
    exit(1);
}
$xml = $z->getFromName('word/document.xml');
$z->close();

// 1) Lettering judul section (run-level, aman untuk judul yang terpecah run)
$map = [
    '>Kondisi<' => '>A. Kondisi<',                       // run "Kondisi" + " " + "Cuaca"
    '>Personel<' => '>B. Personel<',
    '>Peralatan<' => '>C. Peralatan<',
    '>Consumable dan Material<' => '>D. Consumable dan Material<',
    '>Uraian<' => '>E. Uraian<',                          // run "Uraian" + " " + "Aktivitas"
    '>Lampiran <' => '>F. Lampiran<',                     // w:t xml:space="preserve" "Lampiran "
    '>Lampiran<' => '>F. Lampiran<',
];
foreach ($map as $from => $to) {
    // hanya isi <w:t ...> — aman dari nama placeholder
    $xml = preg_replace(
        '/<w:t([^>]*)>'.preg_quote(substr($from, 1, -1), '/').'<\/w:t>/',
        '<w:t$1>'.substr($to, 1, -1).'</w:t>',
        $xml
    );
}

// 2) Header fill seragam: tambahkan shading DEEAF6 ke SEMUA sel pada baris
//    yang mengandung teks header kolom (Jabatan / Jenis / Pagi / Uraian+PIC).
$xml = preg_replace_callback('/<w:tr\b.*?<\/w:tr>/s', function (array $m) {
    $row = $m[0];
    $isHeader = str_contains($row, '>Jabatan<')
        || str_contains($row, '>Jenis<')
        || str_contains($row, '>Pagi<')
        || (str_contains($row, '>Uraian<') && str_contains($row, '>PIC<'));
    if (! $isHeader) {
        return $row;
    }

    return preg_replace_callback('/<w:tcPr>(.*?)<\/w:tcPr>/s', function (array $c) {
        $pr = $c[1];
        // Lepaskan shd yang ada lalu sisipkan ulang di posisi valid —
        // memperbaiki file yang sempat dinormalisasi dengan urutan salah.
        $pr = preg_replace('/<w:shd\b[^>]*\/>|<w:shd\b[^>]*>.*?<\/w:shd>/s', '', $pr);
        $shd = '<w:shd w:val="clear" w:color="auto" w:fill="DEEAF6"/>';
        // Urutan child tcPr: tcW → gridSpan/hMerge/vMerge → tcBorders → shd →
        // noWrap/tcMar/textDirection/tcFitText/vAlign/hideMark.
        $pr = preg_replace(
            '/<w:(?:noWrap|tcMar|textDirection|tcFitText|vAlign|hideMark)\b/',
            $shd.'$0',
            $pr, 1
        );
        if (! str_contains($pr, '<w:shd')) {
            $pr .= $shd;
        }

        return '<w:tcPr>'.$pr.'</w:tcPr>';
    }, $row);
}, $xml);

// 3) Font body seragam 10pt (sz=20); judul lp_kategori ikut seragam.
$xml = preg_replace('/<w:sz w:val="(?:22|24|22)\"\/>/', '<w:sz w:val="20"/>', $xml);
$xml = preg_replace('/<w:szCs w:val="(?:22|24)\"\/>/', '<w:szCs w:val="20"/>', $xml);
// ukuran tersisa yang bukan 20 di body juga diratakan
$xml = preg_replace('/<w:sz w:val="(?:18|26|28|30|32)\"\/>/', '<w:sz w:val="20"/>', $xml);
$xml = preg_replace('/<w:szCs w:val="(?:18|26|28|30|32)\"\/>/', '<w:szCs w:val="20"/>', $xml);

copy($path, $path.'.bak');

// Open TANPA ZipArchive::OVERWRITE (flag itu me-truncate seluruh arsip).
// addFromString pada entry yang sudah ada menggantikan entry tersebut saja.
$z = new ZipArchive;
$z->open($path);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "Template dinormalisasi: {$path} (backup: {$path}.bak)\n";
