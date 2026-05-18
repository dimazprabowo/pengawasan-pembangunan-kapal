<?php

namespace Database\Seeders;

use App\Models\JenisKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use Illuminate\Database\Seeder;

class KurvaSWorkGroupSeeder extends Seeder
{
    /**
     * Get the template for 7 standard shipbuilding work groups — 100 weeks, total bobot = 100%.
     * pct_rencana per minggu = incremental % kemajuan group pada minggu tsb (jumlah per group = 100%).
     * Bobot = kontribusi work group terhadap total proyek (total semua bobot = 100%).
     * @param int $jenisKapalId - used to generate different curve patterns per ship type
     */
    private function getTemplate(int $jenisKapalId): array
    {
        // Different sigma values (curve width) for each ship type to create unique S-curve shapes
        // sigma = activeWeeks / sigmaMultiplier
        // Higher multiplier = narrower curve, Lower multiplier = wider curve
        $sigmaMultiplier = 3 + ($jenisKapalId % 5); // Range: 3-7
        
        return [
            [
                'nama'  => 'Engineering & Design',
                'bobot' => 10.00,
                'weeks' => $this->generateWeeks(1, 15, $sigmaMultiplier), // weeks 1-15, sum = 100%
            ],
            [
                'nama'  => 'Konstruksi Lambung',
                'bobot' => 30.00,
                'weeks' => $this->generateWeeks(5, 50, $sigmaMultiplier), // weeks 5-50, sum = 100%
            ],
            [
                'nama'  => 'Permesinan & Perpipaan',
                'bobot' => 20.00,
                'weeks' => $this->generateWeeks(10, 60, $sigmaMultiplier), // weeks 10-60, sum = 100%
            ],
            [
                'nama'  => 'Kelistrikan & Instrumentasi',
                'bobot' => 15.00,
                'weeks' => $this->generateWeeks(15, 65, $sigmaMultiplier), // weeks 15-65, sum = 100%
            ],
            [
                'nama'  => 'Perlengkapan Kapal (Outfitting)',
                'bobot' => 15.00,
                'weeks' => $this->generateWeeks(20, 70, $sigmaMultiplier), // weeks 20-70, sum = 100%
            ],
            [
                'nama'  => 'Pengecatan & Surface Treatment',
                'bobot' => 5.00,
                'weeks' => $this->generateWeeks(40, 80, $sigmaMultiplier), // weeks 40-80, sum = 100%
            ],
            [
                'nama'  => 'Uji Coba & Komisioning',
                'bobot' => 5.00,
                'weeks' => $this->generateWeeks(60, 100, $sigmaMultiplier), // weeks 60-100, sum = 100%
            ],
        ];
    }

    /**
     * Generate 100 weeks with bell-curve (S-curve) distribution for a work group.
     * Peak is in the middle of the active weeks for a nice S-curve shape.
     * Sum of all weeks = 100.00 (100% of that work group's own progress).
     * @param int $startWeek - first week with non-zero percentage
     * @param int $endWeek - last week with non-zero percentage
     * @param int $sigmaMultiplier - multiplier for curve width (higher = narrower curve)
     * @return array - array of 100 float values (sum = 100.00)
     */
    private function generateWeeks(int $startWeek, int $endWeek, int $sigmaMultiplier = 4): array
    {
        $weeks = array_fill(0, 100, 0.0);
        $activeWeeks = $endWeek - $startWeek + 1;
        
        if ($activeWeeks <= 0) {
            return $weeks;
        }
        
        // Bell curve distribution: peak in the middle of active weeks
        $peakWeek = $startWeek + ($activeWeeks / 2);
        $sigma = $activeWeeks / $sigmaMultiplier; // Standard deviation for bell curve width
        $sum = 0;
        
        for ($i = 0; $i < 100; $i++) {
            $weekNum = $i + 1;
            if ($weekNum < $startWeek || $weekNum > $endWeek) {
                continue;
            }
            
            // Gaussian bell curve formula: exp(-((x - peak)^2) / (2 * sigma^2))
            $value = exp(-pow($weekNum - $peakWeek, 2) / (2 * $sigma * $sigma));
            $weeks[$i] = $value;
            $sum += $value;
        }
        
        // Normalize to 100.00 (handle rounding errors)
        if ($sum > 0) {
            foreach ($weeks as $i => $val) {
                $weeks[$i] = round(($val / $sum) * 100.00, 2);
            }
            
            // Adjust the peak week to ensure exact total = 100.00
            $currentSum = array_sum($weeks);
            if (abs($currentSum - 100.00) > 0.01) {
                $peakIndex = round($peakWeek) - 1;
                $weeks[$peakIndex] = round($weeks[$peakIndex] + (100.00 - $currentSum), 2);
            }
        }
        
        return $weeks;
    }

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

            foreach ($this->getTemplate($jenisKapal->id) as $sortOrder => $wgData) {
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

            $this->command->line("  Seeded: {$jenisKapal->nama} — " . count($this->getTemplate($jenisKapal->id)) . " work groups, 100 minggu.");
        }

        $this->command->info('KurvaSWorkGroup seeded successfully.');
    }
}
