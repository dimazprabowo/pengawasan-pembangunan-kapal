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
     * Ambil semua work groups beserta rencana per bulan untuk satu jenis kapal.
     */
    public function getWorkGroups(JenisKapal $jenisKapal): Collection
    {
        return KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Simpan semua work groups dan rencana per bulan (replace total).
     * pct_rencana dinyatakan dalam poin persen proyek (bukan % group):
     * total rencana per group tidak boleh melebihi bobot group tersebut.
     * Rencana per minggu diturunkan otomatis: pct bulan dibagi rata ke
     * jumlah minggu di bulan tersebut.
     * Format $workGroups:
     * [
     *   ['id' => null, 'nama' => 'Engineering', 'bobot' => '5.00',
     *    'months' => [['bulan_ke' => 1, 'pct_rencana' => '3.00', 'keterangan' => ''], ...]],
     *   ...
     * ]
     */
    public function saveWorkGroups(JenisKapal $jenisKapal, array $workGroups): void
    {
        $existingIds = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->pluck('id')
            ->toArray();

        $savedIds = [];
        $now = now();

        foreach ($workGroups as $sortOrder => $wgData) {
            $id = isset($wgData['id']) && $wgData['id'] ? (int) $wgData['id'] : null;
            $bobot = (float) ($wgData['bobot'] ?? 0);

            if ($id && in_array($id, $existingIds)) {
                $wg = KurvaSWorkGroup::find($id);
            } else {
                $wg = new KurvaSWorkGroup;
            }

            $wg->fill([
                'jenis_kapal_id' => $jenisKapal->id,
                'nama' => trim($wgData['nama'] ?? ''),
                'bobot' => $bobot,
                'sort_order' => $sortOrder,
            ])->save();

            KurvaSRencana::where('work_group_id', $wg->id)->delete();

            $insert = [];
            foreach ((array) ($wgData['months'] ?? []) as $m) {
                $bulan = (int) ($m['bulan_ke'] ?? 0);
                if ($bulan <= 0) {
                    continue;
                }
                $insert[] = [
                    'work_group_id' => $wg->id,
                    'bulan_ke' => $bulan,
                    'pct_rencana' => max(0, min($bobot, (float) ($m['pct_rencana'] ?? 0))),
                    'keterangan' => $m['keterangan'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($insert)) {
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
     * pct_realisasi dinyatakan dalam poin persen proyek (kontribusi langsung),
     * di-clamp ke bobot group sebagai batas atas.
     * Format $progressData: [work_group_id => pct_realisasi, ...]
     */
    public function saveProgress(LaporanMingguan $laporan, array $progressData): void
    {
        $bobotMap = KurvaSWorkGroup::whereIn('id', array_keys($progressData))
            ->pluck('bobot', 'id');

        $now = now();
        foreach ($progressData as $workGroupId => $pct) {
            if (! $workGroupId) {
                continue;
            }
            $maxPct = (float) ($bobotMap[$workGroupId] ?? 100);
            LaporanMingguanProgress::updateOrCreate(
                [
                    'laporan_mingguan_id' => $laporan->id,
                    'work_group_id' => (int) $workGroupId,
                ],
                [
                    'pct_realisasi' => max(0, min($maxPct, (float) $pct)),
                    'updated_at' => $now,
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
     * Minggu diambil dari periode kontrak (label rentang tanggal);
     * tanpa kontrak tidak ada definisi minggu/bulan → daftar kosong.
     * Minggu yang sudah memiliki laporan mingguan akan dikecualikan.
     */
    public function getMingguOptions(JenisKapal $jenisKapal, ?int $excludeCurrentWeek = null): array
    {
        if (! $jenisKapal->hasKontrak()) {
            return [];
        }

        return app(KontrakPeriodeService::class)->getMingguOptions($jenisKapal, $excludeCurrentWeek);
    }

    /**
     * Turunkan rencana bulanan menjadi rencana per minggu:
     * pct_rencana bulan dibagi rata ke jumlah minggu di bulan tersebut.
     * Mengikuti pengelompokan bulan KontrakPeriodeService (mayoritas + min 4
     * minggu untuk bulan tengah). Kosong bila kontrak belum diatur.
     *
     * @return array<int, array<int, float>> [work_group_id => [minggu_ke => pct]]
     */
    private function getWeeklyPlanMap(JenisKapal $jenisKapal, ?Collection $workGroups = null): array
    {
        $bulanList = collect(app(KontrakPeriodeService::class)->getBulanList($jenisKapal))
            ->keyBy('bulan_ke');
        if ($bulanList->isEmpty()) {
            return [];
        }

        $workGroups ??= KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->with('kurvaSRencana')
            ->get();

        $map = [];
        foreach ($workGroups as $wg) {
            foreach ($wg->kurvaSRencana as $r) {
                $bulan = $bulanList->get($r->bulan_ke);
                if (! $bulan || $bulan['total_minggu'] <= 0) {
                    continue;
                }
                $perMinggu = (float) $r->pct_rencana / $bulan['total_minggu'];
                for ($m = $bulan['minggu_dari']; $m <= $bulan['minggu_sampai']; $m++) {
                    $map[$wg->id][$m] = round(($map[$wg->id][$m] ?? 0) + $perMinggu, 4);
                }
            }
        }

        return $map;
    }

    /**
     * Ambil data work groups beserta pct_realisasi untuk sebuah laporan (untuk form input).
     * Returns: [['work_group_id' => 1, 'nama' => '...', 'bobot' => 5.0, 'pct_realisasi' => 0.0], ...]
     */
    public function getProgressInputData(LaporanMingguan $laporan): array
    {
        if (! $laporan->jenis_kapal_id) {
            return [];
        }

        $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $laporan->jenis_kapal_id)
            ->orderBy('sort_order')
            ->get();

        $existing = LaporanMingguanProgress::where('laporan_mingguan_id', $laporan->id)
            ->pluck('pct_realisasi', 'work_group_id');

        return $workGroups->map(fn ($wg) => [
            'work_group_id' => $wg->id,
            'nama' => $wg->nama,
            'bobot' => $wg->bobot,
            'pct_realisasi' => (float) ($existing[$wg->id] ?? 0),
        ])->toArray();
    }

    /**
     * Bangun data lengkap untuk Chart Kurva S.
     *
     * pct_rencana  = kontribusi rencana proyek per minggu (poin %, incremental)
     * pct_realisasi = kontribusi realisasi proyek pada laporan tsb (poin %, incremental)
     *
     * Returns array dengan keys:
     *   labels, rencana (kumulatif proyek), aktual (kumulatif proyek),
     *   has_rencana, has_aktual, total_minggu, total_bobot,
     *   progress_terkini, deviasi, work_groups (for detail table)
     *
     * @param  int|null  $previewMingguKe  Minggu ke- yang sedang di-input (belum tersimpan)
     * @param  array  $previewProgress  [work_group_id => pct_realisasi] dari form (belum tersimpan)
     * @param  int|null  $excludeLaporanId  Laporan yang dikecualikan dari DB (digunakan saat edit)
     */
    public function getChartData(
        JenisKapal $jenisKapal,
        ?int $previewMingguKe = null,
        array $previewProgress = [],
        ?int $excludeLaporanId = null
    ): array {
        $workGroups = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();

        if ($workGroups->isEmpty()) {
            return [
                'labels' => [],
                'rencana' => [],
                'aktual' => [],
                'has_rencana' => false,
                'has_aktual' => false,
                'total_minggu' => 0,
                'total_bobot' => 0.0,
                'progress_terkini' => null,
                'deviasi' => null,
                'work_groups' => [],
            ];
        }

        $workGroupIds = $workGroups->pluck('id')->toArray();

        // Rencana per minggu diturunkan dari rencana bulanan (dibagi rata)
        $weeklyPlan = $this->getWeeklyPlanMap($jenisKapal, $workGroups);

        // Ambil laporan mingguan dengan progress untuk jenis kapal ini
        $laporanList = LaporanMingguan::where('jenis_kapal_id', $jenisKapal->id)
            ->whereNotNull('minggu_ke')
            ->whereHas('laporanProgress')
            ->when($excludeLaporanId, fn ($q) => $q->where('id', '!=', $excludeLaporanId))
            ->with(['laporanProgress'])
            ->orderBy('minggu_ke')
            ->orderBy('created_at')
            ->get();

        // Map: minggu_ke -> [work_group_id -> pct_realisasi] (ambil laporan terbaru per minggu)
        $aktualByWeek = [];
        foreach ($laporanList as $laporan) {
            $week = $laporan->minggu_ke;
            $map = [];
            foreach ($laporan->laporanProgress as $prog) {
                if (in_array($prog->work_group_id, $workGroupIds)) {
                    $map[$prog->work_group_id] = (float) $prog->pct_realisasi;
                }
            }
            if (! empty($map)) {
                $aktualByWeek[$week] = $map;
            }
        }

        // Overlay preview data (unsaved form values) for the week being edited/created
        if ($previewMingguKe !== null && ! empty($previewProgress)) {
            $previewMap = [];
            foreach ($workGroupIds as $wgId) {
                if (array_key_exists($wgId, $previewProgress)) {
                    $previewMap[$wgId] = (float) $previewProgress[$wgId];
                }
            }
            if (! empty($previewMap)) {
                $aktualByWeek[$previewMingguKe] = $previewMap;
            }
        }

        // Minggu kurva = seluruh minggu kontrak ∪ minggu yang sudah ada laporannya
        $totalMingguKontrak = app(KontrakPeriodeService::class)->getTotalMinggu($jenisKapal);
        $allWeeks = array_unique(array_merge(
            $totalMingguKontrak > 0 ? range(1, $totalMingguKontrak) : [],
            array_keys($aktualByWeek)
        ));
        sort($allWeeks);

        if (empty($allWeeks)) {
            return [
                'labels' => [],
                'rencana' => [],
                'aktual' => [],
                'has_rencana' => false,
                'has_aktual' => false,
                'total_minggu' => 0,
                'total_bobot' => round($workGroups->sum('bobot'), 2),
                'progress_terkini' => null,
                'deviasi' => null,
                'work_groups' => $workGroups->map(fn ($wg) => ['id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])->toArray(),
            ];
        }

        // Build cumulative project plan and project actual per week
        $labels = [];
        $rencanaKum = [];
        $aktualKum = [];
        $lastAktual = null;
        $cumPlan = 0.0;
        $cumAktual = 0.0;

        foreach ($allWeeks as $week) {
            $labels[] = 'Minggu '.$week;

            // Project plan for this week = Σ rencana mingguan (dari bagi rata bulanan)
            $weekPlan = 0.0;
            foreach ($workGroups as $wg) {
                $weekPlan += (float) ($weeklyPlan[$wg->id][$week] ?? 0);
            }
            $cumPlan += $weekPlan;
            $rencanaKum[] = round($cumPlan, 2);

            // Project actual for this week = Σ pct_realisasi[group] (sudah dalam poin % proyek)
            // Cumulative: add to running total
            if (isset($aktualByWeek[$week])) {
                $weekActual = 0.0;
                foreach ($workGroups as $wg) {
                    $pct = $aktualByWeek[$week][$wg->id] ?? null;
                    if ($pct !== null) {
                        $weekActual += (float) $pct;
                    }
                }
                $cumAktual += $weekActual;
                $aktualKum[] = round($cumAktual, 2);
                $lastAktual = ['minggu_ke' => $week, 'kumulatif' => round($cumAktual, 2), 'rencana' => round($cumPlan, 2)];
            } else {
                $aktualKum[] = null;
            }
        }

        $deviasi = null;
        if ($lastAktual !== null) {
            $deviasi = round($lastAktual['kumulatif'] - $lastAktual['rencana'], 2);
        }

        return [
            'labels' => $labels,
            'rencana' => $rencanaKum,
            'aktual' => $aktualKum,
            'has_rencana' => true,
            'has_aktual' => ! empty($aktualByWeek),
            'total_minggu' => count($allWeeks),
            'total_bobot' => round($workGroups->sum('bobot'), 2),
            'progress_terkini' => $lastAktual ? $lastAktual['kumulatif'] : null,
            'deviasi' => $deviasi,
            'work_groups' => $workGroups->map(fn ($wg) => ['id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])->toArray(),
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
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();

        // Plan per minggu diturunkan dari rencana bulanan (dibagi rata ke minggunya)
        $weeklyPlan = $this->getWeeklyPlanMap($jenisKapal, $workGroups);

        // Get all weeks from laporan to ensure we have plans for all reported weeks
        $allWeeks = $laporanList->pluck('minggu_ke')->unique()->sort()->values()->toArray();

        // Also get all weeks from rencana to include weeks that might not have laporan yet
        $allRencanaWeeks = collect($weeklyPlan)
            ->flatMap(fn ($weeks) => array_keys($weeks))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Merge weeks from both sources
        $allWeeks = array_unique(array_merge($allWeeks, $allRencanaWeeks));
        sort($allWeeks);

        $planMap = [];
        foreach ($workGroups as $wg) {
            foreach ($allWeeks as $week) {
                // Get plan for this specific week (not cumulative)
                $planMap[$week][$wg->id] = round((float) ($weeklyPlan[$wg->id][$week] ?? 0), 2);
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
                'progress' => $progressMap,
                'plans' => $weekPlans,
                'created_at' => $laporan->created_at->format('d M Y'),
                'periode_mulai' => $laporan->periode_mulai?->format('d M Y'),
                'periode_selesai' => $laporan->periode_selesai?->format('d M Y'),
            ];
        }

        // Calculate cumulative totals for each week
        $history = $this->addCumulativeTotalsToHistory($history, $workGroups);

        return $history;
    }

    /**
     * Tambahkan total kumulatif ke setiap entry dalam progress history.
     * plans/progress sudah dalam poin persen proyek — dijumlahkan langsung.
     * Menghitung total rencana, aktual, dan deviasi kumulatif sampai minggu tersebut.
     */
    public function addCumulativeTotalsToHistory(array $history, $workGroups): array
    {
        $workGroupIds = [];
        foreach ($workGroups as $wg) {
            $workGroupIds[] = $wg->id;
        }

        $cumulativePlan = 0.0;
        $cumulativeActual = 0.0;

        // Track total per work group across all weeks
        $totalPerWorkGroup = [];
        foreach ($workGroupIds as $wgId) {
            $totalPerWorkGroup[$wgId] = [
                'plan' => 0.0,
                'actual' => 0.0,
            ];
        }

        foreach ($history as $index => &$entry) {
            // Calculate weekly totals
            $weekPlan = 0.0;
            $weekActual = 0.0;

            foreach ($workGroupIds as $wgId) {
                $kontribusiPlan = (float) ($entry['plans'][$wgId] ?? 0);
                $kontribusiActual = (float) ($entry['progress'][$wgId] ?? 0);

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
        if (! empty($history)) {
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
     * Ambil rencana (pct_rencana mingguan) per work group untuk satu minggu
     * tertentu — diturunkan dari rencana bulanan dibagi rata ke minggunya.
     * Returns: [work_group_id => pct_rencana, ...]
     */
    public function getWeekPlans(JenisKapal $jenisKapal, int $mingguKe): array
    {
        return collect($this->getWeeklyPlanMap($jenisKapal))
            ->mapWithKeys(fn ($weeks, $workGroupId) => [
                $workGroupId => round((float) ($weeks[$mingguKe] ?? 0), 2),
            ])
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
     * pct_realisasi sudah dalam poin persen proyek — kontribusi = nilai itu sendiri.
     * Returns: [work_group_id => kontribusi_value]
     */
    public function calculateKontribusiPerGroup(array $progressPerGroup, $workGroups): array
    {
        $kontribusi = [];
        foreach ($workGroups as $wg) {
            $wgId = is_array($wg) ? $wg['work_group_id'] : $wg->id;
            $kontribusi[$wgId] = round((float) ($progressPerGroup[$wgId] ?? 0), 2);
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
            $total = 0.0;

            foreach ($progressHistory as $hist) {
                if ($excludeMingguKe && $hist['minggu_ke'] == $excludeMingguKe) {
                    continue;
                }
                if (isset($hist['progress'][$wgId])) {
                    $total += (float) $hist['progress'][$wgId];
                }
            }

            $totalKontribusi[$wgId] = round($total, 2);
        }

        return $totalKontribusi;
    }
}
