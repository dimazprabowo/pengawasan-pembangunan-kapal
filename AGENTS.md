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
- Simpan aktivitas + foto selalu lewat `LaporanHarianService::syncAktivitas()` (transaksi, update by id, hapus foto aktivitas yang dihapus). Form memakai `row_key` stabil; foto di state dikunci per `row_key`, bukan index.
- Template Word harian memakai blok `${blok_kategori}`…`${/blok_kategori}` (judul `${aktivitas_kategori}` + tabel aktivitas) dan `${blok_lampiran}`…`${/blok_lampiran}` (grid 2x2 `${lp_kategori_N}`, `${lp_gambar_N}`, `${lp_uraian_N}`, N=1..4, plus `${lampiran_pemisah}`, `${lampiran_kosong}`). Template custom per Data Kapal wajib memakai placeholder yang sama.

## PhpWord TemplateProcessor gotchas
- `findRowStart()` mencari `<w:tr ` (dengan spasi) lebih dulu: baris tabel yang ditulis manual sebagai `<w:tr>` membuat `cloneRow`/`deleteRow` memotong dari baris tabel lain. Selalu beri atribut, mis. `<w:tr w:rsidR="00C0FFEE">`.
- Aktifkan `Settings::setOutputEscapingEnabled(true)` sebelum `setValue`; teks seperti "Engineering & Design" merusak XML dokumen bila tidak di-escape.
