<?php

namespace App\Services;

use App\Models\JenisKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanMingguan;
use App\Models\LaporanMingguanProgress;
use Illuminate\Support\Collection;

class KurvaSService
{
    /**
     * Ambil semua work groups beserta rencana per minggu untuk satu jenis kapal.
     */
    public function getWorkGroups(JenisKapal $jenisKapal): Collection
    {
        return KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->with(['kurvaSRencana' => fn($q) => $q->orderBy('minggu_ke')])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Simpan semua work groups dan rencana per minggu (replace total).
     * Format $workGroups:
     * [
     *   ['id' => null, 'nama' => 'Engineering', 'bobot' => '5.00',
     *    'weeks' => [['minggu_ke' => 1, 'pct_rencana' => '3.00', 'keterangan' => ''], ...]],
     *   ...
     * ]
     */
    public function saveWorkGroups(JenisKapal $jenisKapal, array $workGroups): void
    {
        $existingIds = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->pluck('id')
            ->toArray();

        $savedIds = [];
        $now      = now();

        foreach ($workGroups as $sortOrder => $wgData) {
            $id = isset($wgData['id']) && $wgData['id'] ? (int) $wgData['id'] : null;

            if ($id && in_array($id, $existingIds)) {
                $wg = KurvaSWorkGroup::find($id);
            } else {
                $wg = new KurvaSWorkGroup();
            }

            $wg->fill([
                'jenis_kapal_id' => $jenisKapal->id,
                'nama'           => trim($wgData['nama'] ?? ''),
                'bobot'          => (float) ($wgData['bobot'] ?? 0),
                'sort_order'     => $sortOrder,
            ])->save();

            KurvaSRencana::where('work_group_id', $wg->id)->delete();

            $insert = [];
            foreach ((array) ($wgData['weeks'] ?? []) as $w) {
                $minggu = (int) ($w['minggu_ke'] ?? 0);
                if ($minggu <= 0) {
                    continue;
                }
                $insert[] = [
                    'work_group_id' => $wg->id,
                    'minggu_ke'     => $minggu,
                    'pct_rencana'   => max(0, min(100, (float) ($w['pct_rencana'] ?? 0))),
                    'keterangan'    => $w['keterangan'] ?? null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            if (!empty($insert)) {
                KurvaSRencana::insert($insert);
            }

            $savedIds[] = $wg->id;
        }

        KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->whereNotIn('id', $savedIds)
            ->delete();
    }

    /**
     * Simpan realisasi per work group untuk satu laporan mingguan.
     * Format $progressData: [work_group_id => pct_realisasi, ...]
     */
    public function saveProgress(LaporanMingguan $laporan, array $progressData): void
    {
        $now = now();
        foreach ($progressData as $workGroupId => $pct) {
            if (!$workGroupId) {
                continue;
            }
            LaporanMingguanProgress::updateOrCreate(
                [
                    'laporan_mingguan_id' => $laporan->id,
                    'work_group_id'       => (int) $workGroupId,
                ],
                [
                    'pct_realisasi' => max(0, min(100, (float) $pct)),
                    'updated_at'    => $now,
                ]
            );
        }
    }

    /**
     * Cek apakah sebuah jenis kapal sudah memiliki work groups dengan rencana Kurva S.
     */
    public function hasRencana(JenisKapal $jenisKapal): bool
    {
        return KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->exists();
    }

    /**
     * Ambil daftar minggu yang tersedia untuk dropdown di laporan mingguan.
     * Minggu diambil dari union semua minggu yang direncanakan oleh work groups.
     * Minggu yang sudah memiliki laporan mingguan akan dikecualikan.
     */
    public function getMingguOptions(JenisKapal $jenisKapal, ?int $excludeCurrentWeek = null): array
    {
        $workGroupIds = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->pluck('id');

        // Get all planned weeks
        $plannedWeeks = KurvaSRencana::whereIn('work_group_id', $workGroupIds)
            ->select('minggu_ke')
            ->distinct()
            ->orderBy('minggu_ke')
            ->pluck('minggu_ke')
            ->toArray();

        // Get weeks that already have laporan mingguan
        $usedWeeks = LaporanMingguan::where('jenis_kapal_id', $jenisKapal->id)
            ->whereNotNull('minggu_ke')
            ->when($excludeCurrentWeek, fn($q) => $q->where('minggu_ke', '!=', $excludeCurrentWeek))
            ->pluck('minggu_ke')
            ->toArray();

        // Exclude used weeks from planned weeks
        $availableWeeks = array_diff($plannedWeeks, $usedWeeks);

        return collect($availableWeeks)
            ->sort()
            ->values()
            ->map(fn($m) => ['value' => $m, 'label' => 'Minggu ke-' . $m])
            ->values()
            ->toArray();
    }

    /**
     * Ambil data work groups beserta pct_realisasi untuk sebuah laporan (untuk form input).
     * Returns: [['work_group_id' => 1, 'nama' => '...', 'bobot' => 5.0, 'pct_realisasi' => 0.0], ...]
     */
    public function getProgressInputData(LaporanMingguan $laporan): array
    {
        if (!$laporan->jenis_kapal_id) {
            return [];
        }

        $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $laporan->jenis_kapal_id)
            ->orderBy('sort_order')
            ->get();

        $existing = LaporanMingguanProgress::where('laporan_mingguan_id', $laporan->id)
            ->pluck('pct_realisasi', 'work_group_id');

        return $workGroups->map(fn($wg) => [
            'work_group_id' => $wg->id,
            'nama'          => $wg->nama,
            'bobot'         => $wg->bobot,
            'pct_realisasi' => (float) ($existing[$wg->id] ?? 0),
        ])->toArray();
    }

    /**
     * Bangun data lengkap untuk Chart Kurva S.
     *
     * pct_rencana  = incremental % progress group per minggu
     * pct_realisasi = cumulative % progress group as of this report
     *
     * Returns array dengan keys:
     *   labels, rencana (kumulatif proyek), aktual (kumulatif proyek),
     *   has_rencana, has_aktual, total_minggu, total_bobot,
     *   progress_terkini, deviasi, work_groups (for detail table)
     *
     * @param int|null  $previewMingguKe  Minggu ke- yang sedang di-input (belum tersimpan)
     * @param array     $previewProgress  [work_group_id => pct_realisasi] dari form (belum tersimpan)
     * @param int|null  $excludeLaporanId Laporan yang dikecualikan dari DB (digunakan saat edit)
     */
    public function getChartData(
        JenisKapal $jenisKapal,
        ?int $previewMingguKe = null,
        array $previewProgress = [],
        ?int $excludeLaporanId = null
    ): array {
        $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->with(['kurvaSRencana' => fn($q) => $q->orderBy('minggu_ke')])
            ->orderBy('sort_order')
            ->get();

        if ($workGroups->isEmpty()) {
            return [
                'labels'           => [],
                'rencana'          => [],
                'aktual'           => [],
                'has_rencana'      => false,
                'has_aktual'       => false,
                'total_minggu'     => 0,
                'total_bobot'      => 0.0,
                'progress_terkini' => null,
                'deviasi'          => null,
                'work_groups'      => [],
            ];
        }

        $allWeeks = $workGroups
            ->flatMap(fn($wg) => $wg->kurvaSRencana->pluck('minggu_ke'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($allWeeks)) {
            return [
                'labels'           => [],
                'rencana'          => [],
                'aktual'           => [],
                'has_rencana'      => false,
                'has_aktual'       => false,
                'total_minggu'     => 0,
                'total_bobot'      => round($workGroups->sum('bobot'), 2),
                'progress_terkini' => null,
                'deviasi'          => null,
                'work_groups'      => $workGroups->map(fn($wg) => ['id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])->toArray(),
            ];
        }

        $workGroupIds = $workGroups->pluck('id')->toArray();

        // Ambil laporan mingguan dengan progress untuk jenis kapal ini
        $laporanList = LaporanMingguan::where('jenis_kapal_id', $jenisKapal->id)
            ->whereNotNull('minggu_ke')
            ->whereHas('laporanProgress')
            ->when($excludeLaporanId, fn($q) => $q->where('id', '!=', $excludeLaporanId))
            ->with(['laporanProgress'])
            ->orderBy('minggu_ke')
            ->orderBy('created_at')
            ->get();

        // Map: minggu_ke -> [work_group_id -> pct_realisasi] (ambil laporan terbaru per minggu)
        $aktualByWeek = [];
        foreach ($laporanList as $laporan) {
            $week = $laporan->minggu_ke;
            $map  = [];
            foreach ($laporan->laporanProgress as $prog) {
                if (in_array($prog->work_group_id, $workGroupIds)) {
                    $map[$prog->work_group_id] = (float) $prog->pct_realisasi;
                }
            }
            if (!empty($map)) {
                $aktualByWeek[$week] = $map;
            }
        }

        // Overlay preview data (unsaved form values) for the week being edited/created
        if ($previewMingguKe !== null && !empty($previewProgress)) {
            $previewMap = [];
            foreach ($workGroupIds as $wgId) {
                if (array_key_exists($wgId, $previewProgress)) {
                    $previewMap[$wgId] = (float) $previewProgress[$wgId];
                }
            }
            if (!empty($previewMap)) {
                $aktualByWeek[$previewMingguKe] = $previewMap;
            }
        }

        // Build cumulative project plan and project actual per week
        $labels      = [];
        $rencanaKum  = [];
        $aktualKum   = [];
        $lastAktual  = null;
        $cumPlan     = 0.0;
        $cumAktual   = 0.0;

        foreach ($allWeeks as $week) {
            $labels[] = 'Minggu ' . $week;

            // Project plan for this week = Σ(pct_rencana[group, week] × bobot / 100)
            $weekPlan = 0.0;
            foreach ($workGroups as $wg) {
                $rencana = $wg->kurvaSRencana->firstWhere('minggu_ke', $week);
                if ($rencana) {
                    $weekPlan += (float) $rencana->pct_rencana * (float) $wg->bobot / 100.0;
                }
            }
            $cumPlan   += $weekPlan;
            $rencanaKum[] = round($cumPlan, 2);

            // Project actual for this week = Σ(pct_realisasi[group] × bobot / 100)
            // Cumulative: add to running total
            if (isset($aktualByWeek[$week])) {
                $weekActual = 0.0;
                foreach ($workGroups as $wg) {
                    $pct = $aktualByWeek[$week][$wg->id] ?? null;
                    if ($pct !== null) {
                        $weekActual += (float) $pct * (float) $wg->bobot / 100.0;
                    }
                }
                $cumAktual  += $weekActual;
                $aktualKum[] = round($cumAktual, 2);
                $lastAktual  = ['minggu_ke' => $week, 'kumulatif' => round($cumAktual, 2), 'rencana' => round($cumPlan, 2)];
            } else {
                $aktualKum[] = null;
            }
        }

        $deviasi = null;
        if ($lastAktual !== null) {
            $deviasi = round($lastAktual['kumulatif'] - $lastAktual['rencana'], 2);
        }

        return [
            'labels'           => $labels,
            'rencana'          => $rencanaKum,
            'aktual'           => $aktualKum,
            'has_rencana'      => true,
            'has_aktual'       => !empty($aktualByWeek),
            'total_minggu'     => count($allWeeks),
            'total_bobot'      => round($workGroups->sum('bobot'), 2),
            'progress_terkini' => $lastAktual ? $lastAktual['kumulatif'] : null,
            'deviasi'          => $deviasi,
            'work_groups'      => $workGroups->map(fn($wg) => ['id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])->toArray(),
        ];
    }

    /**
     * Ambil history progress per work group untuk semua minggu yang sudah ada laporannya.
     * Returns: [['minggu_ke' => 1, 'progress' => [wg_id => pct_realisasi], 'plans' => [wg_id => pct_rencana], 'created_at' => '...'], ...]
     */
    public function getProgressHistory(JenisKapal $jenisKapal): array
    {
        $laporanList = LaporanMingguan::where('jenis_kapal_id', $jenisKapal->id)
            ->whereNotNull('minggu_ke')
            ->whereHas('laporanProgress')
            ->with(['laporanProgress'])
            ->orderBy('minggu_ke')
            ->orderBy('created_at')
            ->get();

        // Get all work groups for this jenis kapal
        $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->with(['kurvaSRencana' => fn($q) => $q->orderBy('minggu_ke')])
            ->orderBy('sort_order')
            ->get();

        // Build plan map: minggu_ke -> work_group_id -> cumulative plan
        // Get all weeks from laporan to ensure we have plans for all reported weeks
        $allWeeks = $laporanList->pluck('minggu_ke')->unique()->sort()->values()->toArray();

        // Also get all weeks from rencana to include weeks that might not have laporan yet
        $allRencanaWeeks = $workGroups
            ->flatMap(fn($wg) => $wg->kurvaSRencana->pluck('minggu_ke'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Merge weeks from both sources
        $allWeeks = array_unique(array_merge($allWeeks, $allRencanaWeeks));
        sort($allWeeks);

        $planMap = [];
        foreach ($workGroups as $wg) {
            // Sort rencana by minggu_ke
            $rencanaList = $wg->kurvaSRencana->sortBy('minggu_ke');

            foreach ($allWeeks as $week) {
                // Get plan for this specific week (not cumulative)
                $weeklyPlan = $rencanaList
                    ->where('minggu_ke', $week)
                    ->sum('pct_rencana');
                $planMap[$week][$wg->id] = round($weeklyPlan, 2);
            }
        }

        $history = [];
        foreach ($laporanList as $laporan) {
            $week = $laporan->minggu_ke;
            $progressMap = [];
            foreach ($laporan->laporanProgress as $prog) {
                $progressMap[$prog->work_group_id] = (float) $prog->pct_realisasi;
            }

            // Get plans for this week
            $weekPlans = $planMap[$week] ?? [];

            $history[] = [
                'minggu_ke' => $week,
                'progress'  => $progressMap,
                'plans'     => $weekPlans,
                'created_at' => $laporan->created_at->format('d M Y'),
            ];
        }

        // Calculate cumulative totals for each week
        $history = $this->addCumulativeTotalsToHistory($history, $workGroups);

        return $history;
    }

    /**
     * Tambahkan total kumulatif ke setiap entry dalam progress history.
     * Menghitung total rencana, aktual, dan deviasi kumulatif sampai minggu tersebut.
     */
    private function addCumulativeTotalsToHistory(array $history, $workGroups): array
    {
        $bobotMap = [];
        foreach ($workGroups as $wg) {
            $bobotMap[$wg->id] = (float) $wg->bobot;
        }

        $cumulativePlan = 0.0;
        $cumulativeActual = 0.0;
        
        // Track total per work group across all weeks
        $totalPerWorkGroup = [];
        foreach ($bobotMap as $wgId => $bobot) {
            $totalPerWorkGroup[$wgId] = [
                'plan' => 0.0,
                'actual' => 0.0,
            ];
        }

        foreach ($history as $index => &$entry) {
            // Calculate weekly totals
            $weekPlan = 0.0;
            $weekActual = 0.0;

            foreach ($bobotMap as $wgId => $bobot) {
                $plan = $entry['plans'][$wgId] ?? 0;
                $actual = $entry['progress'][$wgId] ?? 0;

                // Kontribusi = (pct * bobot) / 100
                // TIDAK dibulatkan per work group, dijumlahkan dulu
                $kontribusiPlan = ($plan * $bobot) / 100.0;
                $kontribusiActual = ($actual * $bobot) / 100.0;
                
                $weekPlan += $kontribusiPlan;
                $weekActual += $kontribusiActual;
                
                // Accumulate per work group
                $totalPerWorkGroup[$wgId]['plan'] += $kontribusiPlan;
                $totalPerWorkGroup[$wgId]['actual'] += $kontribusiActual;
            }

            // Add to cumulative (juga tidak dibulatkan dulu)
            $cumulativePlan += $weekPlan;
            $cumulativeActual += $weekActual;

            // Bulatkan hanya pada hasil akhir
            $entry['cumulative_plan'] = round($cumulativePlan, 2);
            $entry['cumulative_actual'] = round($cumulativeActual, 2);
            $entry['cumulative_deviation'] = round($cumulativeActual - $cumulativePlan, 2);
            
            // Also add weekly totals for consistency
            $entry['week_plan'] = round($weekPlan, 2);
            $entry['week_actual'] = round($weekActual, 2);
            $entry['week_deviation'] = round($weekActual - $weekPlan, 2);
        }
        
        // Add total per work group to the last entry (for footer display)
        if (!empty($history)) {
            $totalPerWorkGroupRounded = [];
            foreach ($totalPerWorkGroup as $wgId => $totals) {
                $totalPerWorkGroupRounded[$wgId] = [
                    'plan' => round($totals['plan'], 2),
                    'actual' => round($totals['actual'], 2),
                    'deviation' => round($totals['actual'] - $totals['plan'], 2),
                ];
            }
            $history[count($history) - 1]['total_per_work_group'] = $totalPerWorkGroupRounded;
        }

        return $history;
    }

    /**
     * Ambil rencana (pct_rencana) per work group untuk satu minggu tertentu.
     * Returns: [work_group_id => pct_rencana, ...]
     */
    public function getWeekPlans(JenisKapal $jenisKapal, int $mingguKe): array
    {
        $workGroupIds = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->pluck('id');

        return KurvaSRencana::whereIn('work_group_id', $workGroupIds)
            ->where('minggu_ke', $mingguKe)
            ->pluck('pct_rencana', 'work_group_id')
            ->map(fn($v) => round((float) $v, 2))
            ->toArray();
    }

    /**
     * Hitung total rencana dan aktual dari progress history.
     * Returns: ['total_rencana' => float, 'total_aktual' => float, 'total_deviasi' => float]
     */
    public function calculateTotalsFromHistory(array $progressHistory, $workGroups): array
    {
        if (empty($progressHistory)) {
            return [
                'total_rencana' => 0.0,
                'total_aktual' => 0.0,
                'total_deviasi' => 0.0,
            ];
        }

        // Get last entry which contains cumulative totals
        $lastEntry = end($progressHistory);
        
        return [
            'total_rencana' => $lastEntry['cumulative_plan'] ?? 0.0,
            'total_aktual' => $lastEntry['cumulative_actual'] ?? 0.0,
            'total_deviasi' => $lastEntry['cumulative_deviation'] ?? 0.0,
        ];
    }

    /**
     * Hitung kontribusi per work group untuk minggu tertentu.
     * Returns: [work_group_id => kontribusi_value]
     */
    public function calculateKontribusiPerGroup(array $progressPerGroup, $workGroups): array
    {
        $kontribusi = [];
        foreach ($workGroups as $wg) {
            $wgId = is_array($wg) ? $wg['work_group_id'] : $wg->id;
            $bobot = is_array($wg) ? (float)$wg['bobot'] : (float)$wg->bobot;
            $pct = (float)($progressPerGroup[$wgId] ?? 0);
            $kontribusi[$wgId] = round($pct * $bobot / 100.0, 2);
        }
        return $kontribusi;
    }

    /**
     * Hitung total kontribusi history per work group (excluding current week).
     * Returns: [work_group_id => total_kontribusi]
     */
    public function calculateTotalKontribusiHistory(array $progressHistory, $workGroups, ?int $excludeMingguKe = null): array
    {
        $totalKontribusi = [];
        
        foreach ($workGroups as $wg) {
            $wgId = is_array($wg) ? $wg['work_group_id'] : $wg->id;
            $bobot = is_array($wg) ? (float)$wg['bobot'] : (float)$wg->bobot;
            $total = 0.0;
            
            foreach ($progressHistory as $hist) {
                if ($excludeMingguKe && $hist['minggu_ke'] == $excludeMingguKe) {
                    continue;
                }
                if (isset($hist['progress'][$wgId])) {
                    $total += (float)$hist['progress'][$wgId] * $bobot / 100.0;
                }
            }
            
            $totalKontribusi[$wgId] = round($total, 2);
        }
        
        return $totalKontribusi;
    }

    /**
     * Bangun data detail tabel untuk satu laporan (untuk halaman show).
     * Returns per-group breakdown: group plan, project plan, group realization, project realization, deviasi
     */
    public function getDetailTableData(LaporanMingguan $laporan): array
    {
        if (!$laporan->jenis_kapal_id || !$laporan->minggu_ke) {
            return [];
        }

        $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $laporan->jenis_kapal_id)
            ->with(['kurvaSRencana' => fn($q) => $q->orderBy('minggu_ke')])
            ->orderBy('sort_order')
            ->get();

        if ($workGroups->isEmpty()) {
            return [];
        }

        $progressMap = LaporanMingguanProgress::where('laporan_mingguan_id', $laporan->id)
            ->pluck('pct_realisasi', 'work_group_id');

        $rows       = [];
        $totalBobot = 0.0;
        $totalPlanGroup  = 0.0;
        $totalPlanProj   = 0.0;
        $totalRealGroup  = 0.0;
        $totalRealProj   = 0.0;

        foreach ($workGroups as $wg) {
            // Cumulative group plan until minggu_ke
            $groupPlan = $wg->kurvaSRencana
                ->where('minggu_ke', '<=', $laporan->minggu_ke)
                ->sum('pct_rencana');

            $projectPlan = round($groupPlan * $wg->bobot / 100, 2);
            $groupReal   = (float) ($progressMap[$wg->id] ?? 0);
            $projectReal = round($groupReal * $wg->bobot / 100, 2);

            $rows[] = [
                'nama'         => $wg->nama,
                'bobot'        => $wg->bobot,
                'group_plan'   => round($groupPlan, 2),
                'project_plan' => $projectPlan,
                'group_real'   => round($groupReal, 2),
                'project_real' => $projectReal,
                'dev_group'    => round($groupReal - $groupPlan, 2),
                'dev_project'  => round($projectReal - $projectPlan, 2),
            ];

            $totalBobot     += $wg->bobot;
            $totalPlanGroup += $groupPlan;
            $totalPlanProj  += $projectPlan;
            $totalRealGroup += $groupReal;
            $totalRealProj  += $projectReal;
        }

        return [
            'minggu_ke'  => $laporan->minggu_ke,
            'rows'       => $rows,
            'totals'     => [
                'bobot'        => round($totalBobot, 2),
                'project_plan' => round($totalPlanProj, 2),
                'project_real' => round($totalRealProj, 2),
                'dev_project'  => round($totalRealProj - $totalPlanProj, 2),
            ],
        ];
    }
}
