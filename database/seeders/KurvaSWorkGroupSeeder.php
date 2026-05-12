<?php

namespace Database\Seeders;

use App\Models\JenisKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use Illuminate\Database\Seeder;

class KurvaSWorkGroupSeeder extends Seeder
{
    /**
     * 7 standard shipbuilding work groups — 12 weeks, total bobot = 100%.
     * pct_rencana per minggu = incremental % kemajuan group pada minggu tsb (jumlah per group = 100%).
     */
    private array $template = [
        [
            'nama'  => 'Engineering & Design',
            'bobot' => 10.00,
            'weeks' => [25.00, 25.00, 25.00, 25.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00],
        ],
        [
            'nama'  => 'Konstruksi Lambung',
            'bobot' => 30.00,
            'weeks' => [2.00, 5.00, 8.00, 10.00, 12.00, 15.00, 15.00, 13.00, 10.00, 7.00, 2.00, 1.00],
        ],
        [
            'nama'  => 'Permesinan & Perpipaan',
            'bobot' => 20.00,
            'weeks' => [0.00, 2.00, 5.00, 8.00, 10.00, 12.00, 15.00, 15.00, 13.00, 12.00, 5.00, 3.00],
        ],
        [
            'nama'  => 'Kelistrikan & Instrumentasi',
            'bobot' => 15.00,
            'weeks' => [0.00, 0.00, 2.00, 5.00, 8.00, 10.00, 15.00, 18.00, 18.00, 14.00, 7.00, 3.00],
        ],
        [
            'nama'  => 'Perlengkapan Kapal (Outfitting)',
            'bobot' => 15.00,
            'weeks' => [0.00, 0.00, 0.00, 2.00, 5.00, 8.00, 12.00, 15.00, 18.00, 20.00, 13.00, 7.00],
        ],
        [
            'nama'  => 'Pengecatan & Surface Treatment',
            'bobot' => 5.00,
            'weeks' => [0.00, 0.00, 0.00, 0.00, 0.00, 5.00, 10.00, 20.00, 25.00, 25.00, 15.00, 0.00],
        ],
        [
            'nama'  => 'Uji Coba & Komisioning',
            'bobot' => 5.00,
            'weeks' => [0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 5.00, 10.00, 25.00, 35.00, 25.00],
        ],
    ];

    public function run(): void
    {
        $jenisKapals = JenisKapal::all();

        if ($jenisKapals->isEmpty()) {
            $this->command->warn('Tidak ada Jenis Kapal. Jalankan JenisKapalSeeder terlebih dahulu.');
            return;
        }

        $now = now();

        foreach ($jenisKapals as $jenisKapal) {
            if (KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->exists()) {
                $this->command->line("  Skip: {$jenisKapal->nama} sudah memiliki work group.");
                continue;
            }

            foreach ($this->template as $sortOrder => $wgData) {
                $wg = KurvaSWorkGroup::create([
                    'jenis_kapal_id' => $jenisKapal->id,
                    'nama'           => $wgData['nama'],
                    'bobot'          => $wgData['bobot'],
                    'sort_order'     => $sortOrder,
                ]);

                $insert = [];
                foreach ($wgData['weeks'] as $i => $pct) {
                    $insert[] = [
                        'work_group_id' => $wg->id,
                        'minggu_ke'     => $i + 1,
                        'pct_rencana'   => $pct,
                        'keterangan'    => null,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }

                KurvaSRencana::insert($insert);
            }

            $this->command->line("  Seeded: {$jenisKapal->nama} — " . count($this->template) . " work groups, 12 minggu.");
        }

        $this->command->info('KurvaSWorkGroup seeded successfully.');
    }
}
