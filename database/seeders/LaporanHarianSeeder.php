<?php

namespace Database\Seeders;

use App\Models\Cuaca;
use App\Models\JenisKapal;
use App\Models\Kelembaban;
use App\Models\LaporanHarian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LaporanHarianSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $jenisKapals = JenisKapal::all();
        $cuacas = Cuaca::all();
        $kelembabans = Kelembaban::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        if ($jenisKapals->isEmpty()) {
            $this->command->warn('No Jenis Kapal found. Please run JenisKapalSeeder first.');
            return;
        }

        $laporanData = [
            [
                'judul' => 'Laporan Harian Minggu 1 - Konstruksi Lambung',
                'tanggal' => Carbon::now()->subDays(6),
            ],
            [
                'judul' => 'Laporan Harian Minggu 2 - Permesinan',
                'tanggal' => Carbon::now()->subDays(5),
            ],
            [
                'judul' => 'Laporan Harian Minggu 3 - Kelistrikan',
                'tanggal' => Carbon::now()->subDays(4),
            ],
            [
                'judul' => 'Laporan Harian Minggu 4 - Outfitting',
                'tanggal' => Carbon::now()->subDays(3),
            ],
            [
                'judul' => 'Laporan Harian Minggu 5 - Pengecatan',
                'tanggal' => Carbon::now()->subDays(2),
            ],
            [
                'judul' => 'Laporan Harian Minggu 6 - Inspeksi',
                'tanggal' => Carbon::now()->subDays(1),
            ],
            [
                'judul' => 'Laporan Harian Minggu 7 - Finalisasi',
                'tanggal' => Carbon::now(),
            ],
        ];

        foreach ($laporanData as $data) {
            LaporanHarian::firstOrCreate(
                [
                    'user_id' => $users->random()->id,
                    'jenis_kapal_id' => $jenisKapals->random()->id,
                    'judul' => $data['judul'],
                    'tanggal_laporan' => $data['tanggal'],
                ],
                [
                    'suhu' => rand(2800, 3200) / 100,
                    'cuaca_pagi_id' => $cuacas->random()->id ?? null,
                    'kelembaban_pagi_id' => $kelembabans->random()->id ?? null,
                    'cuaca_siang_id' => $cuacas->random()->id ?? null,
                    'kelembaban_siang_id' => $kelembabans->random()->id ?? null,
                    'cuaca_sore_id' => $cuacas->random()->id ?? null,
                    'kelembaban_sore_id' => $kelembabans->random()->id ?? null,
                ]
            );
        }

        $this->command->info('Laporan Harian seeded successfully (7 records).');
    }
}
