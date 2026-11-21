<?php

namespace Database\Seeders;

use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\LaporanExternal;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use App\Services\PdfPageService;
use Database\Seeders\Support\SeedFileFactory;
use Illuminate\Database\Seeder;

class LaporanBulananSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $dataKapals = DataKapal::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        if ($dataKapals->isEmpty()) {
            $this->command->warn('No Data Kapal found. Please run DataKapalSeeder first.');

            return;
        }

        // Seed laporan bulanan untuk SEMUA data kapal — hanya bulan proyek
        // yang sudah selesai (periode_selesai <= hari ini).
        $kontrakService = app(KontrakPeriodeService::class);
        $today = now()->startOfDay();

        foreach ($dataKapals as $dataKapal) {
            $bulanList = collect($kontrakService->getBulanList($dataKapal))
                ->filter(fn ($b) => $b['selesai']->lte($today))
                ->values()
                ->all();

            foreach ($bulanList as $bulan) {
                $laporan = LaporanBulanan::firstOrCreate(
                    [
                        'data_kapal_id' => $dataKapal->id,
                        'bulan_ke' => $bulan['bulan_ke'],
                    ],
                    [
                        'user_id' => $users->random()->id,
                        'tanggal_laporan' => $bulan['selesai'],
                        'periode_mulai' => $bulan['mulai'],
                        'periode_selesai' => $bulan['selesai'],
                        'bulan_ke' => $bulan['bulan_ke'],
                        'ringkasan' => self::RINGKASAN_POOL[$bulan['bulan_ke'] % count(self::RINGKASAN_POOL)],
                    ]
                );

                // Lampiran eksternal berupa file PDF NYATA di disk agar
                // preview/download/generate Word berjalan seperti data asli.
                // Setiap bulan dapat 1 dokumen; tiap bulan ke-3 kelipatan 2
                // agar daftar lampiran variatif seperti input asli.
                $externalCount = $bulan['bulan_ke'] % 3 === 0 ? 2 : 1;
                for ($j = 0; $j < $externalCount; $j++) {
                    $judul = self::EXTERNAL_JUDUL[($laporan->id + $j) % count(self::EXTERNAL_JUDUL)];
                    $fileName = 'laporan-eksternal-bulan-'.$bulan['bulan_ke'].'-'.($j + 1).'.pdf';
                    [$filePath, $fileSize] = SeedFileFactory::storePdf('laporan-external', $judul, [
                        ['heading' => 'Ringkasan', 'lines' => [
                            'Pemeriksaan berkala lapangan pekerjaan pembangunan '.$dataKapal->nama,
                            'Periode: '.$bulan['mulai'].' s/d '.$bulan['selesai'],
                        ]],
                        ['heading' => 'Hasil', 'lines' => [
                            'Hasil pemeriksaan sesuai spesifikasi teknis dan gambar kerja yang disetujui.',
                            'Catatan minor telah disampaikan ke pihak galangan untuk ditindaklanjuti.',
                        ]],
                    ]);

                    // Render halaman via poppler (pola ProcessLaporanExternal)
                    // — wajib untuk lampiran eksternal di dokumen Word.
                    $pages = app(PdfPageService::class)->render($filePath, 'laporan-external/pages');

                    LaporanExternal::create([
                        'laporan_bulanan_id' => $laporan->id,
                        'judul' => $judul,
                        'deskripsi' => 'Dokumen pendukung laporan bulan ke-'.$bulan['bulan_ke'],
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'file_size' => $fileSize,
                        'file_status' => 'completed',
                        'rendered_pages' => $pages,
                        'file_processed_at' => now(),
                    ]);
                }

            }

            $this->command->line("  {$dataKapal->nama}: ".count($bulanList).' laporan bulanan.');
        }

        $this->command->info('Laporan Bulanan seeded successfully.');
    }

    private const RINGKASAN_POOL = [
        'Rekapitulasi progress bulan berjalan sesuai rencana kerja disepakati. Seluruh aktivitas utama berjalan normal, deviasi kumulatif masih dalam batas toleransi.',
        'Selama periode ini pelaksanaan pekerjaan berjalan lancar. Beberapa keterlambatan pasokan material berhasil dikejar dengan penambahan shift kerja.',
        'Progress kumulatif bulan ini menunjukkan tren positif. Koordinasi tiga pihak (pemilik, pengawas, galangan) berjalan efektif melalui rapat berkala.',
        'Bulan ini tercatat beberapa temuan kualitas minor yang seluruhnya telah ditindaklanjuti galangan dan diverifikasi ulang oleh tim pengawas.',
    ];

    private const EXTERNAL_JUDUL = [
        'Berita Acara Pemeriksaan Bulanan',
        'Laporan Hasil Pengujian',
        'Dokumentasi Sertifikat Material',
        'Berita Acara Verifikasi Progress',
    ];
}
