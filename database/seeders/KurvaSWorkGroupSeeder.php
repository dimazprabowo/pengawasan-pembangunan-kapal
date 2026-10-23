<?php

namespace Database\Seeders;

use App\Models\JenisKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use App\Services\KontrakPeriodeService;
use Illuminate\Database\Seeder;

class KurvaSWorkGroupSeeder extends Seeder
{
    /**
     * Get the template for 7 standard shipbuilding work groups — total bobot = 100%.
     * Rencana diinput per BULAN proyek; pct_rencana per bulan = kontribusi proyek
     * bulan tsb dalam poin % (jumlah per group = bobot-nya). Nilai rencana mingguan
     * di-derive oleh sistem (bulanan dibagi rata ke minggu-minggu di bulan tsb).
     * Bobot = porsi maksimal work group terhadap total proyek (total semua bobot = 100%).
     *
     * @param  int  $jenisKapalId  - used to generate different curve patterns per ship type
     * @param  int  $totalMonths  - jumlah bulan proyek dari kontrak
     */
    private function getTemplate(int $jenisKapalId, int $totalMonths): array
    {
        // Different sigma values (curve width) for each ship type to create unique S-curve shapes
        // sigma = activeMonths / sigmaMultiplier
        // Higher multiplier = narrower curve, Lower multiplier = wider curve
        $sigmaMultiplier = 3 + ($jenisKapalId % 5); // Range: 3-7

        // Fractions of totalMonths for each group's active range (start,end)
        return [
            [
                'nama' => 'Engineering & Design',
                'bobot' => 10.00,
                'months' => $this->generateMonths(1, max(2, (int) round($totalMonths * 0.15)), $totalMonths, $sigmaMultiplier, 10.00),
            ],
            [
                'nama' => 'Konstruksi Lambung',
                'bobot' => 30.00,
                'months' => $this->generateMonths(max(1, (int) round($totalMonths * 0.05)), max(2, (int) round($totalMonths * 0.50)), $totalMonths, $sigmaMultiplier, 30.00),
            ],
            [
                'nama' => 'Permesinan & Perpipaan',
                'bobot' => 20.00,
                'months' => $this->generateMonths(max(1, (int) round($totalMonths * 0.10)), max(3, (int) round($totalMonths * 0.60)), $totalMonths, $sigmaMultiplier, 20.00),
            ],
            [
                'nama' => 'Kelistrikan & Instrumentasi',
                'bobot' => 15.00,
                'months' => $this->generateMonths(max(1, (int) round($totalMonths * 0.15)), max(3, (int) round($totalMonths * 0.65)), $totalMonths, $sigmaMultiplier, 15.00),
            ],
            [
                'nama' => 'Perlengkapan Kapal (Outfitting)',
                'bobot' => 15.00,
                'months' => $this->generateMonths(max(1, (int) round($totalMonths * 0.20)), max(4, (int) round($totalMonths * 0.70)), $totalMonths, $sigmaMultiplier, 15.00),
            ],
            [
                'nama' => 'Pengecatan & Surface Treatment',
                'bobot' => 5.00,
                'months' => $this->generateMonths(max(2, (int) round($totalMonths * 0.40)), max(4, (int) round($totalMonths * 0.80)), $totalMonths, $sigmaMultiplier, 5.00),
            ],
            [
                'nama' => 'Uji Coba & Komisioning',
                'bobot' => 5.00,
                'months' => $this->generateMonths(max(2, (int) round($totalMonths * 0.60)), $totalMonths, $totalMonths, $sigmaMultiplier, 5.00),
            ],
        ];
    }

    /**
     * Generate monthly bell-curve (S-curve) distribution for a work group.
     * Peak is in the middle of the active months for a nice S-curve shape.
     * Sum of all months = $totalPct poin % proyek (= bobot group).
     *
     * @param  int  $startMonth  - first month with non-zero percentage
     * @param  int  $endMonth  - last month with non-zero percentage
     * @param  int  $totalMonths  - jumlah bulan proyek dari kontrak
     * @param  int  $sigmaMultiplier  - multiplier for curve width (higher = narrower curve)
     * @param  float  $totalPct  - target total distribusi (bobot group dalam poin % proyek)
     * @return array - array of $totalMonths float values (sum = $totalPct)
     */
    private function generateMonths(int $startMonth, int $endMonth, int $totalMonths, int $sigmaMultiplier = 4, float $totalPct = 100.00): array
    {
        $months = array_fill(0, $totalMonths, 0.0);
        $startMonth = max(1, $startMonth);
        $endMonth = min($totalMonths, max($startMonth, $endMonth));
        $activeMonths = $endMonth - $startMonth + 1;

        // Bell curve distribution: peak in the middle of active months
        $peakMonth = $startMonth + ($activeMonths / 2);
        $sigma = max($activeMonths / $sigmaMultiplier, 0.5); // Standard deviation for bell curve width
        $sum = 0;

        for ($i = 0; $i < $totalMonths; $i++) {
            $monthNum = $i + 1;
            if ($monthNum < $startMonth || $monthNum > $endMonth) {
                continue;
            }

            // Gaussian bell curve formula: exp(-((x - peak)^2) / (2 * sigma^2))
            $value = exp(-pow($monthNum - $peakMonth, 2) / (2 * $sigma * $sigma));
            $months[$i] = $value;
            $sum += $value;
        }

        // Normalize to $totalPct (handle rounding errors)
        if ($sum > 0) {
            foreach ($months as $i => $val) {
                $months[$i] = round(($val / $sum) * $totalPct, 2);
            }

            // Adjust the peak month to ensure exact total = $totalPct
            $currentSum = array_sum($months);
            if (abs($currentSum - $totalPct) > 0.01) {
                $peakIndex = (int) round($peakMonth) - 1;
                $months[$peakIndex] = round($months[$peakIndex] + ($totalPct - $currentSum), 2);
            }
        }

        return $months;
    }

    public function run(): void
    {
        $jenisKapals = JenisKapal::all();
        $kontrakService = app(KontrakPeriodeService::class);

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

            // Rencana bulanan memerlukan kontrak (bulan di-derive dari tanggal & durasi kontrak)
            if (! $jenisKapal->hasKontrak()) {
                $this->command->warn("  Skip: {$jenisKapal->nama} belum memiliki data kontrak.");

                continue;
            }

            $totalMonths = $kontrakService->getTotalBulan($jenisKapal);

            foreach ($this->getTemplate($jenisKapal->id, $totalMonths) as $sortOrder => $wgData) {
                $wg = KurvaSWorkGroup::create([
                    'jenis_kapal_id' => $jenisKapal->id,
                    'nama' => $wgData['nama'],
                    'bobot' => $wgData['bobot'],
                    'sort_order' => $sortOrder,
                ]);

                $insert = [];
                foreach ($wgData['months'] as $i => $pct) {
                    $insert[] = [
                        'work_group_id' => $wg->id,
                        'bulan_ke' => $i + 1,
                        'pct_rencana' => $pct,
                        'keterangan' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                KurvaSRencana::insert($insert);
            }

            $this->command->line("  Seeded: {$jenisKapal->nama} — 7 work groups, {$totalMonths} bulan.");
        }

        $this->command->info('KurvaSWorkGroup seeded successfully.');
    }
}
