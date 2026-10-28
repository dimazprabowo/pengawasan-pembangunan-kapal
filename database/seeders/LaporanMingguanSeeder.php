<?php

namespace Database\Seeders;

use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanExternal;
use App\Models\LaporanMingguan;
use App\Models\LaporanMingguanProgress;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use App\Services\KurvaSService;
use Carbon\Carbon;
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

        // Pick ONE data kapal for all laporan to show consistent progress across weeks
        $dataKapal = $dataKapals->first();
        $kontrakService = app(KontrakPeriodeService::class);

        // Seed SEMUA minggu kontrak untuk data kapal ini agar Laporan Bulanan
        // (yang menarik data dari mingguan) lengkap sampai bulan terakhir.
        // Periode diambil dari kontrak; fallback 10 minggu relatif bila tanpa kontrak.
        $laporanData = [];
        $mingguList = $kontrakService->getMingguList($dataKapal);

        if (empty($mingguList)) {
            $mingguList = collect(range(1, 10))->map(fn ($i) => [
                'minggu_ke' => $i,
                'mulai' => Carbon::now()->subDays($i * 7),
                'selesai' => Carbon::now()->subDays(($i - 1) * 7),
            ])->all();
        }

        foreach ($mingguList as $minggu) {
            $i = $minggu['minggu_ke'];
            $laporanData[] = [
                'minggu_ke' => $i,
                'periode_mulai' => $minggu['mulai'],
                'periode_selesai' => $minggu['selesai'],
                'ringkasan' => 'Progress minggu ke-'.$i.' sesuai rencana.',
            ];
        }

        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->get();
        $kurvaService = app(KurvaSService::class);

        // Matriks rencana mingguan per work group [work_group_id => [minggu_ke => pct]]
        // (diturunkan dari rencana bulanan — dibagi rata ke minggunya).
        $planByWg = [];
        foreach ($mingguList as $minggu) {
            foreach ($kurvaService->getWeekPlans($dataKapal, $minggu['minggu_ke']) as $wgId => $pct) {
                $planByWg[$wgId][$minggu['minggu_ke']] = (float) $pct;
            }
        }

        // Realisasi mingguan per work group: jitter lebar per minggu (55-150% rencana)
        // agar deviasi naik-turun terlihat di Kurva-S, lalu diskala ke sebagian bobot
        // (75-100%) — tiap group punya profil keterlambatan berbeda di akhir kurva.
        $realisasiByWg = [];
        foreach ($workGroups as $wg) {
            $raw = [];
            foreach ($mingguList as $minggu) {
                $plan = $planByWg[$wg->id][$minggu['minggu_ke']] ?? 0.0;
                $raw[$minggu['minggu_ke']] = $plan * (rand(55, 150) / 100);
            }

            $sumRaw = array_sum($raw);
            $target = (float) $wg->bobot * (rand(75, 100) / 100);
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
                    'ringkasan' => $data['ringkasan'],
                ]
            );

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

            // Add sample external reports for some laporan (randomly)
            if (rand(1, 3) === 1) { // 33% chance to have external reports
                $externalCount = rand(1, 3);
                for ($j = 0; $j < $externalCount; $j++) {
                    LaporanExternal::create([
                        'laporan_mingguan_id' => $laporan->id,
                        'judul' => 'Laporan External '.($j + 1).' - Minggu '.$data['minggu_ke'],
                        'deskripsi' => 'Dokumen tambahan untuk minggu ke-'.$data['minggu_ke'],
                        'file_name' => 'laporan_external_'.$laporan->id.'_'.($j + 1).'.pdf',
                        'file_size' => rand(500000, 5000000), // 500KB - 5MB
                        'file_status' => 'completed',
                        'file_processed_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Laporan Mingguan seeded successfully ('.count($laporanData).' records).');
    }
}
