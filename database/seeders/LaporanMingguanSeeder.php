<?php

namespace Database\Seeders;

use App\Models\JenisKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanExternal;
use App\Models\LaporanMingguan;
use App\Models\LaporanMingguanProgress;
use App\Models\User;
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

        // Create 10 consecutive laporan for the same jenis kapal (weeks 1-10)
        $laporanData = [];
        for ($i = 1; $i <= 10; $i++) {
            $laporanData[] = [
                'judul' => 'Laporan Mingguan ' . $i,
                'minggu_ke' => $i,
                'periode_mulai' => Carbon::now()->subDays($i * 7),
                'periode_selesai' => Carbon::now()->subDays(($i - 1) * 7),
                'ringkasan' => 'Progress minggu ke-' . $i . ' sesuai rencana.',
            ];
        }

        foreach ($laporanData as $data) {
            $laporan = LaporanMingguan::firstOrCreate(
                [
                    'user_id' => $users->random()->id,
                    'jenis_kapal_id' => $jenisKapal->id,
                    'judul' => $data['judul'],
                ],
                [
                    'tanggal_laporan' => $data['periode_selesai'],
                    'periode_mulai' => $data['periode_mulai'],
                    'periode_selesai' => $data['periode_selesai'],
                    'minggu_ke' => $data['minggu_ke'],
                    'ringkasan' => $data['ringkasan'],
                ]
            );

            // Create progress records for each work group
            $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
                ->with(['kurvaSRencana' => fn($q) => $q->orderBy('minggu_ke')])
                ->get();
            
            foreach ($workGroups as $wg) {
                // Get the rencana for this work group to determine active weeks
                $rencanas = $wg->kurvaSRencana;
                if ($rencanas->isEmpty()) {
                    continue;
                }
                
                $minWeek = $rencanas->min('minggu_ke');
                $maxWeek = $rencanas->max('minggu_ke');
                $currentWeek = $data['minggu_ke'];
                
                // If current week is before the work group starts, progress is 0
                if ($currentWeek < $minWeek) {
                    $progress = 0;
                } 
                // If current week is after the work group ends, progress is 100%
                elseif ($currentWeek > $maxWeek) {
                    $progress = 100;
                } 
                // Otherwise, calculate progress based on position within the active range
                else {
                    $totalWeeks = $maxWeek - $minWeek + 1;
                    $weekInRange = $currentWeek - $minWeek + 1;
                    $baseProgress = ($weekInRange / $totalWeeks) * 100;
                    // Add some random variation (±5%)
                    $progress = max(0, min(100, $baseProgress + rand(-5, 5)));
                }
                
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
                        'judul' => 'Laporan External ' . ($j + 1) . ' - Minggu ' . $data['minggu_ke'],
                        'deskripsi' => 'Dokumen tambahan untuk minggu ke-' . $data['minggu_ke'],
                        'file_name' => 'laporan_external_' . $laporan->id . '_' . ($j + 1) . '.pdf',
                        'file_size' => rand(500000, 5000000), // 500KB - 5MB
                        'file_status' => 'completed',
                        'file_processed_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Laporan Mingguan seeded successfully (10 records).');
    }
}
