<?php

namespace Database\Seeders;

use App\Models\Cuaca;
use App\Models\DataKapal;
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
        $dataKapals = DataKapal::all();
        $cuacas = Cuaca::all();
        $kelembabans = Kelembaban::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        if ($dataKapals->isEmpty()) {
            $this->command->warn('No Data Kapal found. Please run DataKapalSeeder first.');

            return;
        }

        // Rentang tanggal: 14 hari ke belakang s/d 7 hari ke depan agar data demo
        // mencakup periode berjalan maupun periode yang belum sepenuhnya berlalu.
        $tanggalMulai = Carbon::now()->subDays(14);
        $jumlahHari = 21;

        $count = 0;
        foreach ($dataKapals as $dataKapal) {
            for ($i = 0; $i < $jumlahHari; $i++) {
                $tanggal = $tanggalMulai->copy()->addDays($i);

                LaporanHarian::firstOrCreate(
                    [
                        'data_kapal_id' => $dataKapal->id,
                        'tanggal_laporan' => $tanggal->format('Y-m-d'),
                    ],
                    [
                        'user_id' => $users->get($i % $users->count())->id,
                        'suhu' => 28 + ($i % 5),
                        'cuaca_pagi_id' => $cuacas->isNotEmpty() ? $cuacas->get($i % $cuacas->count())->id : null,
                        'kelembaban_pagi_id' => $kelembabans->isNotEmpty() ? $kelembabans->get($i % $kelembabans->count())->id : null,
                        'cuaca_siang_id' => $cuacas->isNotEmpty() ? $cuacas->get(($i + 1) % $cuacas->count())->id : null,
                        'kelembaban_siang_id' => $kelembabans->isNotEmpty() ? $kelembabans->get(($i + 1) % $kelembabans->count())->id : null,
                        'cuaca_sore_id' => $cuacas->isNotEmpty() ? $cuacas->get(($i + 2) % $cuacas->count())->id : null,
                        'kelembaban_sore_id' => $kelembabans->isNotEmpty() ? $kelembabans->get(($i + 2) % $kelembabans->count())->id : null,
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Laporan Harian seeded successfully ({$count} records).");
    }
}
