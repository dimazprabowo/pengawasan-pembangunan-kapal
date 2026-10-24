<?php

namespace Database\Seeders;

use App\Models\JenisKapal;
use App\Models\LaporanBulanan;
use App\Models\LaporanExternal;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use Illuminate\Database\Seeder;

class LaporanBulananSeeder extends Seeder
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

        // Satu jenis kapal yang sama dengan LaporanMingguanSeeder
        $jenisKapal = $jenisKapals->first();
        $kontrakService = app(KontrakPeriodeService::class);

        // Seed SEMUA bulan kontrak — lengkap sampai bulan terakhir.
        $bulanList = $kontrakService->getBulanList($jenisKapal);

        if (empty($bulanList)) {
            $this->command->warn('Jenis kapal tidak punya kontrak. Skipping Laporan Bulanan seeding.');

            return;
        }

        foreach ($bulanList as $bulan) {
            $laporan = LaporanBulanan::firstOrCreate(
                [
                    'jenis_kapal_id' => $jenisKapal->id,
                    'bulan_ke' => $bulan['bulan_ke'],
                ],
                [
                    'user_id' => $users->random()->id,
                    'tanggal_laporan' => $bulan['selesai'],
                    'periode_mulai' => $bulan['mulai'],
                    'periode_selesai' => $bulan['selesai'],
                    'bulan_ke' => $bulan['bulan_ke'],
                    'ringkasan' => 'Rekapitulasi progress '.$bulan['label'].' sesuai laporan mingguan.',
                ]
            );

            // Add sample external reports for some laporan (randomly)
            if (rand(1, 3) === 1) { // 33% chance to have external reports
                $externalCount = rand(1, 2);
                for ($j = 0; $j < $externalCount; $j++) {
                    LaporanExternal::create([
                        'laporan_bulanan_id' => $laporan->id,
                        'judul' => 'Laporan External '.($j + 1).' - Bulan '.$bulan['bulan_ke'],
                        'deskripsi' => 'Dokumen tambahan untuk bulan ke-'.$bulan['bulan_ke'],
                        'file_name' => 'laporan_external_bulanan_'.$laporan->id.'_'.($j + 1).'.pdf',
                        'file_size' => rand(500000, 5000000), // 500KB - 5MB
                        'file_status' => 'completed',
                        'file_processed_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Laporan Bulanan seeded successfully ('.count($bulanList).' records).');
    }
}
