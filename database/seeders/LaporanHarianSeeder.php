<?php

namespace Database\Seeders;

use App\Models\Cuaca;
use App\Models\DataKapal;
use App\Models\Kelembaban;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanHarian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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
            $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->orderBy('sort_order')->get();

            for ($i = 0; $i < $jumlahHari; $i++) {
                $tanggal = $tanggalMulai->copy()->addDays($i);

                $laporan = LaporanHarian::firstOrCreate(
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

                if ($laporan->wasRecentlyCreated) {
                    $this->seedAktivitas($laporan, $workGroups, $i);
                }
                $count++;
            }
        }

        $this->command->info("Laporan Harian seeded successfully ({$count} records).");
    }

    /**
     * Uraian aktivitas contoh: 2 work group bergiliran per hari (kategori lain sengaja
     * kosong agar tidak tergenerate di laporan) + sesekali segmen "Lainnya".
     */
    private function seedAktivitas(LaporanHarian $laporan, Collection $workGroups, int $hari): void
    {
        $contoh = [
            'Pemeriksaan pekerjaan sesuai gambar kerja dan spesifikasi',
            'Monitoring progres pekerjaan bersama pihak galangan',
            'Inspeksi visual hasil pekerjaan dan pencatatan temuan',
        ];

        foreach ($workGroups->isEmpty() ? [] : [$hari, $hari + 1] as $n) {
            $wg = $workGroups[$n % $workGroups->count()];
            foreach (array_slice($contoh, 0, 1 + ($n % 2)) as $k => $uraian) {
                $laporan->aktivitas()->create([
                    'kurva_s_work_group_id' => $wg->id,
                    'kategori' => $wg->nama,
                    'aktivitas' => "{$uraian} — {$wg->nama}",
                    'pic' => ['QC Galangan', 'Surveyor BKI', 'Owner Surveyor'][($n + $k) % 3],
                ]);
            }
        }

        if ($hari % 3 === 0) {
            $laporan->aktivitas()->create([
                'kurva_s_work_group_id' => null,
                'kategori' => null,
                'aktivitas' => 'Rapat koordinasi harian dan pembahasan K3',
                'pic' => 'Lead Inspector',
            ]);
        }
    }
}
