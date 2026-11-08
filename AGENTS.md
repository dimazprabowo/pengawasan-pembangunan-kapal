# Project notes

## Verification
- `php artisan migrate:fresh --seed` (DB lokal PostgreSQL; PHPUnit memakai SQLite `:memory:`).
- Laporan harian: `php artisan test --filter="LaporanHarianAktivitasLampiranTest|LaporanNavigationTest|LaporanTanggalUnikTest"`.
- `php artisan view:cache`, `npm run build`, `./vendor/bin/pint <file yang diubah>`.
- Di Windows `view:cache` bisa gagal "Access is denied" (file view terkunci dev server) — jalankan `php artisan view:clear` lalu ulangi.
- Audit deprecation (PHP 8.5 / Laravel 13 / PHPUnit 12): `LOG_DEPRECATIONS_CHANNEL=single php artisan test` dan `LOG_DEPRECATIONS_CHANNEL=single php artisan migrate:fresh --seed`, lalu pastikan tidak ada baris baru `local.WARNING` di `storage/logs/laravel.log`. Tinker memakai handler psysh sendiri (tidak menulis log) — jangan dipakai untuk validasi.

## Laporan Harian — Uraian Aktivitas & Lampiran
- Kategori aktivitas = Work Group Kurva-S data kapal (`laporan_aktivitas.kurva_s_work_group_id`, null = "Lainnya") + snapshot nama di `kategori`.
- Foto lampiran 1:1 dengan aktivitas (`laporan_lampiran.laporan_aktivitas_id`, unique). `laporan_harian_id` tetap ada untuk Laporan Mingguan. `LaporanLampiran::keterangan` adalah accessor = uraian aktivitas (eager load `aktivitas`).
- File lampiran/NC di-serve via route `laporan-lampiran.file` & `laporan-nc-file.file` (permission `laporan_lampiran_preview` + `User::canAccessDataKapal` scope, `isFileCompleted`, cache immutable) — JANGAN kembalikan base64 data-URL ke public prop Livewire (penyebab PayloadTooLarge di form mingguan). `LaporanLampiran` & `LaporanNcFile` memakai `HasEncryptedRouteKey`.
- Simpan aktivitas + foto selalu lewat `LaporanHarianService::syncAktivitas()` (transaksi, update by id, hapus foto aktivitas yang dihapus). Form memakai `row_key` stabil; foto di state dikunci per `row_key`, bukan index.
- Template harian dinormalisasi via `php build_laporan_harian_template.php` (idempoten): font Arial 10pt seragam (rFonts run dihapus), blok info = tabel tanpa garis label | : | nilai, judul section A–F biru tua, header tabel `1F3864`.
- Template Word harian memakai blok `${blok_kategori}`…`${/blok_kategori}` (judul `${aktivitas_kategori}` + tabel aktivitas) dan `${blok_lampiran}`…`${/blok_lampiran}` (grid 2x2 `${lp_kategori_N}`, `${lp_gambar_N}`, `${lp_uraian_N}`, N=1..4, plus `${lampiran_pemisah}`, `${lampiran_kosong}`). Template custom per Data Kapal wajib memakai placeholder yang sama.

## Laporan Mingguan — Word
- Template dibangun ulang via `php build_laporan_mingguan_template.php` (cover, info proyek, A–E, pengesahan). Header tabel biru tua `1F3864`, teks putih.
- Tabel tanpa gambar dibangun di `LaporanMingguanWordService` via `setComplexBlock`: `${tabel_kurva_s}`, `${tabel_uraian}`, `${tabel_nc}`, `${tabel_external}`. Foto memakai `${blok_foto}` (`fh_*`, cloneRow + setImageValue).
- `${ringkasan}` dibungkus `${blok_ringkasan}` dan di-clone per baris input (`fillRingkasan`): `setValue` mengubah `\n` menjadi `<w:br/>` dalam satu `<w:p>` — di paragraf justify (`jc="both"`) baris sebelum break ikut di-stretch selebar margin. Template custom lama tanpa blok tetap didukung (fallback `setValue` biasa).
- Lampiran E.2: paragraf `${lampiran_harian}` diganti halaman sampul "LAPORAN HARIAN" (sel tabel setinggi halaman — `vAlign`+`jc` center; tinggi 13400 twip menyisakan ruang untuk paragraf page-break agar tidak ada halaman kosong) + `w:altChunk` (tiap harian diawali page break) berisi docx harian utuh (tampilan identik template harian). Sumber: `doc_path` harian yang sudah ada; bila belum ada di-generate via `GenerateLaporanHarianJob::dispatchSync`. altChunk hanya dirender MS Word (desktop/365), tidak di LibreOffice/Google Docs/preview browser.
- NC mingguan (`LaporanNcService::rekapMingguan`): open terbawa terus, closed hanya tampil di periode ia di-close.

## PhpWord TemplateProcessor gotchas
- `findRowStart()` mencari `<w:tr ` (dengan spasi) lebih dulu: baris tabel yang ditulis manual sebagai `<w:tr>` membuat `cloneRow`/`deleteRow` memotong dari baris tabel lain. Selalu beri atribut, mis. `<w:tr w:rsidR="00C0FFEE">`.
- Aktifkan `Settings::setOutputEscapingEnabled(true)` sebelum `setValue`; teks seperti "Engineering & Design" merusak XML dokumen bila tidak di-escape.
- JANGAN pakai auto-numbering Word (`w:numPr`) untuk judul yang teksnya sudah memuat huruf/nomor literal — hasilnya ganda ("A. A. ..."). Lebih fatal: docx harian disisipkan ke laporan mingguan via `w:altChunk`; list numbering LANJUT antar-chunk (judul laporan harian ke-2 mulai dari "F"). Tulis nomor literal di teks, lepas `numPr`/`ListParagraph`/`w:ind` dari paragraf judul.
- Tabel ber-lebar `dxa` tanpa `tblLayout fixed` bisa menyusut mengikuti isi sel (autofit) — tampak lebih pendek dari tabel lain yang `pct`. Selaraskan semua tabel konten ke `tblW 5000/pct` + `tcW` persen (gridCol tetap dxa).
- `LaporanHarianWordService::generate()`/`LaporanMingguanWordService::generate()` hanya menulis file — `doc_path` di-update oleh `GenerateLaporan*Job`. Jangan panggil service langsung untuk regenerate; pakai `GenerateLaporanHarianJob::dispatchSync($laporan)` agar file lama terhapus & `doc_path` menunjuk file baru (lampiran mingguan membaca `doc_path`).
