<?php

namespace Database\Seeders;

use App\Enums\NcStatus;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\User;
use Illuminate\Database\Seeder;

class LaporanNcSeeder extends Seeder
{
    /**
     * Contoh NC per laporan harian: nomor berurutan per kapal (NC-001, NC-002, ...),
     * sebagian closed dengan closed_by agar List NC terlihat variatif.
     */
    public function run(): void
    {
        // Skip saat testing: test membuat fixture sendiri, data contoh hanya mengganggu.
        if (app()->environment('testing')) {
            return;
        }

        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        $pool = [
            ['kendala' => 'Las sambungan pelat lambung tidak rata dan terdapat porositas', 'tindakan' => 'Gouging dan pengelasan ulang sesuai WPS', 'pic' => 'Welder Foreman'],
            ['kendala' => 'Jarak spasi frame tidak sesuai gambar kerja', 'tindakan' => 'Penyesuaian ulang posisi frame sesuai marking', 'pic' => 'Fitter Leader'],
            ['kendala' => 'Coating area tangki terkelupas karena persiapan permukaan kurang baik', 'tindakan' => 'Blasting ulang dan pengecatan ulang sesuai spesifikasi', 'pic' => 'Painting Supervisor'],
            ['kendala' => 'Pipa bilga bocor pada sambungan flange saat uji tekan', 'tindakan' => 'Ganti gasket dan torque ulang sesuai tabel', 'pic' => 'Pipe Foreman'],
            ['kendala' => 'Kabel tray tertabrak pekerjaan piping sehingga jalur bergeser', 'tindakan' => 'Relayout tray dan cek kekuatan tumpuan', 'pic' => 'Electrical Foreman'],
        ];

        $count = 0;
        $i = 0;

        // Secukupnya untuk demo: 5 laporan terakhir per kapal — 1 NC per laporan,
        // +1 NC ekstra di laporan pertama agar ada grup multi-NC.
        LaporanHarian::orderBy('tanggal_laporan', 'desc')->get()
            ->groupBy('data_kapal_id')
            ->each(function ($perKapal) use ($users, $pool, &$count, &$i) {
                $perKapal->take(5)->each(function ($laporan, $n) use ($users, $pool, &$count, &$i) {
                    $jumlah = $n === 0 ? 2 : 1;
                    $creator = $users->get($i % $users->count());
                    $key = $laporan->data_kapal_id;
                    $counter = (int) LaporanNc::where('data_kapal_id', $key)->count();
                    $i++;

                    for ($k = 0; $k < $jumlah; $k++) {
                        $counter++;
                        $nomor = 'NC-'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
                        $data = $pool[($i + $k) % count($pool)];
                        $closed = ($counter % 4 === 0);

                        LaporanNc::firstOrCreate(
                            ['laporan_harian_id' => $laporan->id, 'nomor' => $nomor],
                            [
                                'data_kapal_id' => $laporan->data_kapal_id,
                                'kendala' => $data['kendala'],
                                'tindakan' => $data['tindakan'],
                                'pic' => $data['pic'],
                                'status' => $closed ? NcStatus::Closed : NcStatus::Open,
                                'closed_at' => $closed ? $laporan->tanggal_laporan->copy()->addDays(2) : null,
                                'closed_by' => $closed ? $creator->id : null,
                                'created_by' => $creator->id,
                                'updated_by' => $creator->id,
                            ]
                        );
                        $count++;
                    }
                });
            });

        $this->command->info("Laporan NC seeded successfully ({$count} records).");
    }
}
