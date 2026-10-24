<?php

namespace Database\Seeders;

use App\Models\JenisKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanExternal;
use App\Models\LaporanMingguan;
use App\Models\LaporanMingguanProgress;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LaporanMingguanSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $jenisKapals = JenisKapal::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        if ($jenisKapals->isEmpty()) {
            $this->command->warn('No Jenis Kapal found. Please run JenisKapalSeeder first.');

            return;
        }

        // Pick ONE jenis kapal for all laporan to show consistent progress across weeks
        $jenisKapal = $jenisKapals->first();
        $kontrakService = app(KontrakPeriodeService::class);

        // Seed SEMUA minggu kontrak untuk jenis kapal ini agar Laporan Bulanan
        // (yang menarik data dari mingguan) lengkap sampai bulan terakhir.
        // Periode diambil dari kontrak; fallback 10 minggu relatif bila tanpa kontrak.
        $laporanData = [];
        $mingguList = $kontrakService->getMingguList($jenisKapal);

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

        foreach ($laporanData as $data) {
            $laporan = LaporanMingguan::firstOrCreate(
                [
                    'jenis_kapal_id' => $jenisKapal->id,
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

            // Create progress records for each work group
            $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->get();

            // Rencana minggu ini diturunkan dari rencana bulanan (dibagi rata ke minggunya)
            $rencanaMinggu = app(\App\Services\KurvaSService::class)->getWeekPlans($jenisKapal, $data['minggu_ke']);

            foreach ($workGroups as $wg) {
                // Realisasi minggu ini dalam poin % proyek — mengikuti rencana minggu tsb
                // dengan variasi ±15% agar kurva aktual sedikit menyimpang dari rencana.
                $pctRencana = (float) ($rencanaMinggu[$wg->id] ?? 0);
                $progress = round(max(0, min((float) $wg->bobot, $pctRencana * (rand(80, 115) / 100))), 2);

                LaporanMingguanProgress::updateOrCreate(
                    [
                        'laporan_mingguan_id' => $laporan->id,
                        'work_group_id' => $wg->id,
                    ],
                    [
                        'pct_realisasi' => $progress,
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
