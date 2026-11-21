<?php

namespace Database\Seeders;

use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanExternal;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\LaporanMingguanProgress;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use App\Services\KurvaSService;
use App\Services\PdfPageService;
use Carbon\Carbon;
use Database\Seeders\Support\SeedFileFactory;
use Illuminate\Database\Seeder;

class LaporanMingguanSeeder extends Seeder
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

        // Seed laporan mingguan untuk SEMUA data kapal — hanya minggu yang
        // sudah selesai (periode_selesai <= hari ini) sehingga proyek berjalan
        // tidak punya laporan di periode masa depan.
        $kontrakService = app(KontrakPeriodeService::class);
        $kurvaService = app(KurvaSService::class);
        $today = Carbon::today();

        foreach ($dataKapals as $dataKapal) {
            $mingguList = collect($kontrakService->getMingguList($dataKapal))
                ->filter(fn ($m) => $m['selesai']->lte($today))
                ->values()
                ->all();

            if (empty($mingguList)) {
                continue;
            }

            // Target realisasi kumulatif: kontrak selesai = 100% bobot,
            // kontrak berjalan = 88% bobot (proyek sedang berjalan).
            $kontrakSelesai = $dataKapal->tanggal_selesai_pelaksanaan?->isPast() ?? false;
            $targetRatio = $kontrakSelesai ? 1.0 : 0.88;

            $this->seedForKapal($dataKapal, $mingguList, $targetRatio, $users, $kurvaService);
        }

        $this->command->info('Laporan Mingguan seeded successfully.');
    }

    /**
     * Seed seluruh laporan mingguan satu data kapal beserta realisasi
     * per work group (diskala ke $targetRatio dari bobot) dan lampiran
     * external contoh.
     */
    private function seedForKapal(DataKapal $dataKapal, array $mingguList, float $targetRatio, $users, KurvaSService $kurvaService): void
    {
        $laporanData = [];
        foreach ($mingguList as $minggu) {
            $laporanData[] = [
                'minggu_ke' => $minggu['minggu_ke'],
                'periode_mulai' => $minggu['mulai'],
                'periode_selesai' => $minggu['selesai'],
            ];
        }

        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->get();

        // Matriks rencana mingguan per work group [work_group_id => [minggu_ke => pct]]
        // (diturunkan dari rencana bulanan — dibagi rata ke minggunya).
        $planByWg = [];
        foreach ($mingguList as $minggu) {
            foreach ($kurvaService->getWeekPlans($dataKapal, $minggu['minggu_ke']) as $wgId => $pct) {
                $planByWg[$wgId][$minggu['minggu_ke']] = (float) $pct;
            }
        }

        // Realisasi mingguan per work group: jitter lebar per minggu (55-150% rencana)
        // agar deviasi naik-turun terlihat di Kurva-S, lalu diskala ke $targetRatio
        // dari bobot — tiap group punya profil penyelesaian berbeda di akhir kurva.
        $realisasiByWg = [];
        foreach ($workGroups as $wg) {
            $raw = [];
            foreach ($mingguList as $minggu) {
                $plan = $planByWg[$wg->id][$minggu['minggu_ke']] ?? 0.0;
                $raw[$minggu['minggu_ke']] = $plan * (rand(55, 150) / 100);
            }

            $sumRaw = array_sum($raw);
            $target = (float) $wg->bobot * $targetRatio;
            $scale = $sumRaw > 0 ? $target / $sumRaw : 0.0;

            $realisasi = [];
            foreach ($raw as $mingguKe => $value) {
                $realisasi[$mingguKe] = round($value * $scale, 2);
            }

            // Koreksi residual pembulatan pada minggu bernilai terbesar
            // agar total realisasi per group = target bobot akhir persis.
            if ($sumRaw > 0) {
                $diff = round($target - array_sum($realisasi), 2);
                if ($diff != 0.0) {
                    $maxWeek = array_keys($realisasi, max($realisasi))[0];
                    $realisasi[$maxWeek] = round($realisasi[$maxWeek] + $diff, 2);
                }
            }

            $realisasiByWg[$wg->id] = $realisasi;
        }

        foreach ($laporanData as $data) {
            $laporan = LaporanMingguan::firstOrCreate(
                [
                    'data_kapal_id' => $dataKapal->id,
                    'minggu_ke' => $data['minggu_ke'],
                ],
                [
                    'user_id' => $users->random()->id,
                    'tanggal_laporan' => $data['periode_selesai'],
                    'periode_mulai' => $data['periode_mulai'],
                    'periode_selesai' => $data['periode_selesai'],
                    'minggu_ke' => $data['minggu_ke'],
                    'ringkasan' => $this->ringkasan($data['minggu_ke']),
                ]
            );

            // Kaitkan laporan harian dalam periode (pola form: auto-select
            // semua harian rentang) + lampiran fotonya agar kolom TANGGAL
            // HARIAN dan picker lampiran mingguan terisi seperti input user.
            // Skip saat testing: test membuat fixture pivot sendiri dan
            // laporan_harian_id bersifat UNIQUE (harian hanya milik 1 mingguan).
            if (! app()->environment('testing')) {
                $harianDalamPeriode = LaporanHarian::where('data_kapal_id', $dataKapal->id)
                    ->whereDate('tanggal_laporan', '>=', $data['periode_mulai'])
                    ->whereDate('tanggal_laporan', '<=', $data['periode_selesai'])
                    // laporan_harian_id UNIQUE di pivot — harian yang sudah
                    // diklaim mingguan lain tidak boleh diattach ulang.
                    ->whereNotIn('id', fn ($q) => $q->select('laporan_harian_id')
                        ->from('laporan_mingguan_harian')
                        ->where('laporan_mingguan_id', '!=', $laporan->id))
                    ->get();

                $laporan->syncLaporanHarian($harianDalamPeriode->pluck('id')->all());
                $laporan->syncLampiran(
                    $harianDalamPeriode->flatMap->lampiran->where('file_status', 'completed')->pluck('id')->all()
                );
            }

            // Realisasi minggu ini dalam poin % proyek — dari matriks yang sudah
            // diskala agar total per group = bobot (lihat blok prekomputasi di atas).
            foreach ($workGroups as $wg) {
                LaporanMingguanProgress::updateOrCreate(
                    [
                        'laporan_mingguan_id' => $laporan->id,
                        'work_group_id' => $wg->id,
                    ],
                    [
                        'pct_realisasi' => $realisasiByWg[$wg->id][$data['minggu_ke']] ?? 0,
                    ]
                );
            }

            // Lampiran eksternal berupa file PDF NYATA di disk — bukan
            // sekadar baris DB, agar preview/download/word generation jalan.
            // Setiap minggu dapat 1 dokumen; tiap minggu ke-3 kelipatan 2
            // agar daftar lampiran variatif seperti input asli.
            if (! app()->environment('testing')) {
                $externalCount = $data['minggu_ke'] % 3 === 0 ? 2 : 1;
                for ($j = 0; $j < $externalCount; $j++) {
                    $judul = self::EXTERNAL_JUDUL[($laporan->id + $j) % count(self::EXTERNAL_JUDUL)];
                    $fileName = 'laporan-eksternal-minggu-'.$data['minggu_ke'].'-'.($j + 1).'.pdf';
                    [$filePath, $fileSize] = SeedFileFactory::storePdf('laporan-external', $judul, [
                        ['heading' => 'Ringkasan', 'lines' => [
                            'Pemeriksaan berkala lapangan pekerjaan pembangunan '.$dataKapal->nama,
                            'Periode: '.$data['periode_mulai'].' s/d '.$data['periode_selesai'],
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
                        'laporan_mingguan_id' => $laporan->id,
                        'judul' => $judul,
                        'deskripsi' => 'Dokumen pendukung laporan minggu ke-'.$data['minggu_ke'],
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'file_size' => $fileSize,
                        'file_status' => 'completed',
                        'rendered_pages' => $pages,
                        'file_processed_at' => now(),
                    ]);
                }
            }
        }

        $this->command->line("  {$dataKapal->nama}: ".count($laporanData).' laporan mingguan.');
    }

    /**
     * Ringkasan mingguan bergaya input manusia — divariasikan per nomor minggu.
     */
    private function ringkasan(int $mingguKe): string
    {
        $pool = [
            'Progress minggu ke-%d berjalan sesuai rencana kerja. Pekerjaan fabrikasi blok berlanjut tanpa kendala berarti, material pendukung tersedia di site.',
            'Minggu ke-%d: pekerjaan perakitan dan pengelasan berjalan baik. Cuaca sempat menghambat pekerjaan outdoor 1 hari namun tidak mempengaruhi jadwal keseluruhan.',
            'Realisasi minggu ke-%d sedikit di atas rencana. Koordinasi dengan pihak galangan berjalan lancar; temuan QC minor sudah ditindaklanjuti di tempat.',
            'Minggu ke-%d diwarnai keterlambatan kedatangan sebagian material sehingga beberapa aktivitas dijadwal ulang. Recovery plan sudah disepakati bersama galangan.',
            'Kegiatan minggu ke-%d fokus pada penyelesaian pekerjaan struktur dan persiapan pengujian. Tidak ada temuan mayor; dokumentasi progres sudah dilengkapi.',
        ];

        return sprintf($pool[$mingguKe % count($pool)], $mingguKe);
    }

    private const EXTERNAL_JUDUL = [
        'Berita Acara Pemeriksaan Material',
        'Laporan Hasil NDT',
        'Berita Acara Uji Kebocoran Tangki',
        'Sertifikat Material Galangan',
        'Laporan Inspeksi Lapangan',
    ];
}
