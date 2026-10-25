<?php

namespace App\Services;

use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSRencanaMingguan;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanMingguan;
use App\Models\LaporanMingguanProgress;
use Illuminate\Support\Collection;

class KurvaSService
{
    /**
     * Ambil semua work groups beserta rencana per bulan untuk satu data kapal.
     */
    public function getWorkGroups(DataKapal $dataKapal): Collection
    {
        return KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Simpan semua work groups dan rencana per bulan (replace total).
     * pct_rencana dinyatakan dalam poin persen proyek (bukan % group):
     * total rencana per group tidak boleh melebihi bobot group tersebut.
     * Rencana per minggu: bila sebuah bulan membawa 'weeks' (override
     * mingguan), nilainya disimpan apa adanya; bulan tanpa 'weeks' nantinya
     * memakai bagi-rata bulanan (lihat getWeeklyPlanMap).
     * Format $workGroups:
     * [
     *   ['id' => null, 'nama' => 'Engineering', 'bobot' => '5.00',
     *    'months' => [['bulan_ke' => 1, 'pct_rencana' => '3.00', 'keterangan' => '',
     *                  'weeks' => [['minggu_ke' => 1, 'pct_rencana' => '0.750'], ...]], ...]],
     *   ...
     * ]
     */
    public function saveWorkGroups(DataKapal $dataKapal, array $workGroups): void
    {
        $existingIds = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
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
                'data_kapal_id' => $dataKapal->id,
                'nama' => trim($wgData['nama'] ?? ''),
                'bobot' => $bobot,
                'sort_order' => $sortOrder,
            ])->save();

            KurvaSRencana::where('work_group_id', $wg->id)->delete();
            KurvaSRencanaMingguan::where('work_group_id', $wg->id)->delete();

            $insert = [];
            $insertMingguan = [];
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

                foreach ((array) ($m['weeks'] ?? []) as $w) {
                    $mingguKe = (int) ($w['minggu_ke'] ?? 0);
                    if ($mingguKe <= 0) {
                        continue;
                    }
                    $insertMingguan[] = [
                        'work_group_id' => $wg->id,
                        'minggu_ke' => $mingguKe,
                        'pct_rencana' => max(0, min($bobot, (float) ($w['pct_rencana'] ?? 0))),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($insert)) {
                KurvaSRencana::insert($insert);
            }
            if (! empty($insertMingguan)) {
                KurvaSRencanaMingguan::insert($insertMingguan);
            }

            $savedIds[] = $wg->id;
        }

        KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
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
     * Cek apakah sebuah data kapal sudah memiliki work groups dengan rencana Kurva S.
     */
    public function hasRencana(DataKapal $dataKapal): bool
    {
        return KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->exists();
    }

    /**
     * Ambil daftar minggu yang tersedia untuk dropdown di laporan mingguan.
     * Minggu diambil dari periode kontrak (label rentang tanggal);
     * tanpa kontrak tidak ada definisi minggu/bulan → daftar kosong.
     * Minggu yang sudah memiliki laporan mingguan akan dikecualikan.
     */
    public function getMingguOptions(DataKapal $dataKapal, ?int $excludeCurrentWeek = null): array
    {
        if (! $dataKapal->hasKontrak()) {
            return [];
        }

        return app(KontrakPeriodeService::class)->getMingguOptions($dataKapal, $excludeCurrentWeek);
    }

    /**
     * Bagi rata sebuah pct bulan ke N minggu: tiap minggu di-TRUNCATE ke 3
     * desimal (bukan dibulatkan), sisa pembagian ditambahkan ke minggu
     * terakhir sehingga total = pct bulan persis.
     *
     * @return array<int, float> nilai per minggu (urut minggu_dari → sampai)
     */
    public function splitToWeeks(float $pct, int $totalMinggu): array
    {
        if ($totalMinggu <= 0) {
            return [];
        }

        $per = $this->truncate3($pct / $totalMinggu);
        $values = array_fill(0, $totalMinggu, $per);
        $values[$totalMinggu - 1] = round($pct - $per * ($totalMinggu - 1), 3);

        return $values;
    }

    /**
     * Potong (bukan bulatkan) angka ke maksimal 3 desimal.
     */
    private function truncate3(float $value): float
    {
        return $value >= 0
            ? floor($value * 1000) / 1000
            : ceil($value * 1000) / 1000;
    }

    /**
     * Rencana per minggu untuk setiap work group. "Mingguan menang":
     * bulan yang memiliki data di kurva_s_rencana_mingguan memakai nilai
     * tersimpan apa adanya; bulan tanpa data mingguan memakai bagi-rata
     * pct bulan (splitToWeeks). Mengikuti pengelompokan bulan
     * KontrakPeriodeService. Kosong bila kontrak belum diatur.
     *
     * @return array<int, array<int, float>> [work_group_id => [minggu_ke => pct]]
     */
    private function getWeeklyPlanMap(DataKapal $dataKapal, ?Collection $workGroups = null): array
    {
        $bulanList = collect(app(KontrakPeriodeService::class)->getBulanList($dataKapal))
            ->keyBy('bulan_ke');
        if ($bulanList->isEmpty()) {
            return [];
        }

        $workGroups ??= KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
            ->get();
        $workGroups->loadMissing(['kurvaSRencana', 'kurvaSRencanaMingguan']);

        $map = [];
        foreach ($workGroups as $wg) {
            $rencanaByBulan = $wg->kurvaSRencana->pluck('pct_rencana', 'bulan_ke');
            $storedByMinggu = $wg->kurvaSRencanaMingguan->pluck('pct_rencana', 'minggu_ke');

            foreach ($bulanList as $bulanKe => $bulan) {
                $weeks = range($bulan['minggu_dari'], $bulan['minggu_sampai']);
                $hasStored = collect($weeks)->contains(fn ($m) => $storedByMinggu->has($m));

                if ($hasStored) {
                    foreach ($weeks as $m) {
                        $map[$wg->id][$m] = round(($map[$wg->id][$m] ?? 0) + (float) ($storedByMinggu[$m] ?? 0), 3);
                    }
                } else {
                    $pct = (float) ($rencanaByBulan[$bulanKe] ?? 0);
                    if ($pct <= 0) {
                        continue;
                    }
                    $split = $this->splitToWeeks($pct, count($weeks));
                    foreach (array_values($weeks) as $i => $m) {
                        $map[$wg->id][$m] = round(($map[$wg->id][$m] ?? 0) + $split[$i], 3);
                    }
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
        if (! $laporan->data_kapal_id) {
            return [];
        }

        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $laporan->data_kapal_id)
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
        DataKapal $dataKapal,
        ?int $previewMingguKe = null,
        array $previewProgress = [],
        ?int $excludeLaporanId = null
    ): array {
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
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
        $weeklyPlan = $this->getWeeklyPlanMap($dataKapal, $workGroups);

        // Map: minggu_ke -> [work_group_id -> pct_realisasi] (ambil laporan terbaru per minggu)
        $aktualByWeek = $this->getAktualByWeek($dataKapal, $workGroupIds, $excludeLaporanId);

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
        $totalMingguKontrak = app(KontrakPeriodeService::class)->getTotalMinggu($dataKapal);
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
     * Map minggu_ke → [work_group_id => pct_realisasi] dari laporan terbaru
     * per minggu (bila ada lebih dari satu laporan pada minggu yang sama,
     * yang dibuat belakangan menang — konsisten di seluruh Kurva S).
     *
     * @return array<int, array<int, float>>
     */
    private function getAktualByWeek(DataKapal $dataKapal, array $workGroupIds, ?int $excludeLaporanId = null): array
    {
        $laporanList = LaporanMingguan::where('data_kapal_id', $dataKapal->id)
            ->whereNotNull('minggu_ke')
            ->whereHas('laporanProgress')
            ->when($excludeLaporanId, fn ($q) => $q->where('id', '!=', $excludeLaporanId))
            ->with(['laporanProgress'])
            ->orderBy('minggu_ke')
            ->orderBy('created_at')
            ->get();

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

        return $aktualByWeek;
    }

    /**
     * Data Kurva S periode BULANAN.
     * Rencana bulan diambil langsung dari kurva_s_rencana (pct_rencana memang
     * per bulan — TIDAK dibagi), realisasi bulan = jumlah pct_realisasi
     * minggu-minggu yang masuk bulan tersebut (aturan laporan terbaru per
     * minggu sama dengan getChartData).
     *
     * Return shape sama dengan getChartData (labels/rencana/aktual kumulatif,
     * has_*, total_bobot, progress_terkini, deviasi, work_groups) plus
     * 'unit_label' => 'Bulan' dan 'total_unit' => jumlah bulan kontrak.
     */
    public function getMonthlyChartData(DataKapal $dataKapal): array
    {
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();

        $empty = [
            'labels' => [],
            'rencana' => [],
            'aktual' => [],
            'has_rencana' => false,
            'has_aktual' => false,
            'total_unit' => 0,
            'unit_label' => 'Bulan',
            'total_bobot' => round($workGroups->sum('bobot'), 2),
            'progress_terkini' => null,
            'deviasi' => null,
            'work_groups' => $workGroups->map(fn ($wg) => ['id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])->toArray(),
        ];

        if ($workGroups->isEmpty()) {
            return $empty;
        }

        $workGroupIds = $workGroups->pluck('id')->toArray();

        $kontrak = app(KontrakPeriodeService::class);
        $bulanList = $kontrak->getBulanList($dataKapal);
        if (empty($bulanList)) {
            return $empty;
        }
        $mingguBulanMap = $kontrak->getMingguBulanMap($dataKapal);

        // Rencana per bulan langsung dari kurva_s_rencana
        $planByBulan = $this->getMonthlyPlanMap($workGroups);

        // Realisasi per bulan = Σ realisasi minggu-minggu di dalamnya
        $aktualByBulan = [];
        foreach ($this->getAktualByWeek($dataKapal, $workGroupIds) as $week => $map) {
            $bulanKe = $mingguBulanMap[$week] ?? null;
            if ($bulanKe === null) {
                continue;
            }
            foreach ($map as $wgId => $pct) {
                $aktualByBulan[$bulanKe][$wgId] = round(($aktualByBulan[$bulanKe][$wgId] ?? 0) + $pct, 4);
            }
        }

        $labels = [];
        $rencanaKum = [];
        $aktualKum = [];
        $lastAktual = null;
        $cumPlan = 0.0;
        $cumAktual = 0.0;

        foreach ($bulanList as $bulan) {
            $bulanKe = $bulan['bulan_ke'];
            $labels[] = 'Bulan '.$bulanKe;

            $monthPlan = 0.0;
            foreach ($workGroups as $wg) {
                $monthPlan += (float) ($planByBulan[$bulanKe][$wg->id] ?? 0);
            }
            $cumPlan += $monthPlan;
            $rencanaKum[] = round($cumPlan, 2);

            if (isset($aktualByBulan[$bulanKe])) {
                $monthActual = 0.0;
                foreach ($workGroups as $wg) {
                    $monthActual += (float) ($aktualByBulan[$bulanKe][$wg->id] ?? 0);
                }
                $cumAktual += $monthActual;
                $aktualKum[] = round($cumAktual, 2);
                $lastAktual = ['bulan_ke' => $bulanKe, 'kumulatif' => round($cumAktual, 2), 'rencana' => round($cumPlan, 2)];
            } else {
                $aktualKum[] = null;
            }
        }

        $deviasi = $lastAktual !== null
            ? round($lastAktual['kumulatif'] - $lastAktual['rencana'], 2)
            : null;

        return [
            'labels' => $labels,
            'rencana' => $rencanaKum,
            'aktual' => $aktualKum,
            'has_rencana' => true,
            'has_aktual' => ! empty($aktualByBulan),
            'total_unit' => count($bulanList),
            'unit_label' => 'Bulan',
            'total_bobot' => round($workGroups->sum('bobot'), 2),
            'progress_terkini' => $lastAktual ? $lastAktual['kumulatif'] : null,
            'deviasi' => $deviasi,
            'work_groups' => $workGroups->map(fn ($wg) => ['id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])->toArray(),
        ];
    }

    /**
     * Map bulan_ke → [work_group_id => pct_rencana] langsung dari
     * kurva_s_rencana (nilai memang per bulan — tidak dibagi).
     *
     * @return array<int, array<int, float>>
     */
    private function getMonthlyPlanMap(Collection $workGroups): array
    {
        $map = [];
        foreach ($workGroups as $wg) {
            foreach ($wg->kurvaSRencana as $r) {
                $map[$r->bulan_ke][$wg->id] = round(($map[$r->bulan_ke][$wg->id] ?? 0) + (float) $r->pct_rencana, 4);
            }
        }

        return $map;
    }

    /**
     * History progress per BULAN untuk seluruh bulan kontrak yang sudah
     * memiliki laporan mingguan berprogress. plans[] = rencana bulan,
     * progress[] = Σ realisasi minggu dalam bulan; kumulatif ditambahkan
     * oleh addCumulativeTotalsToHistory (shape sama dengan getProgressHistory).
     */
    public function getMonthlyProgressHistory(DataKapal $dataKapal): array
    {
        $kontrak = app(KontrakPeriodeService::class);
        $bulanList = collect($kontrak->getBulanList($dataKapal))->keyBy('bulan_ke');
        if ($bulanList->isEmpty()) {
            return [];
        }

        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();
        $workGroupIds = $workGroups->pluck('id')->toArray();

        $mingguBulanMap = $kontrak->getMingguBulanMap($dataKapal);
        $planByBulan = $this->getMonthlyPlanMap($workGroups);

        // Laporan terbaru per minggu → realisasi + referensi tanggal laporan
        $laporanList = LaporanMingguan::where('data_kapal_id', $dataKapal->id)
            ->whereNotNull('minggu_ke')
            ->whereHas('laporanProgress')
            ->with(['laporanProgress'])
            ->orderBy('minggu_ke')
            ->orderBy('created_at')
            ->get();

        $latestByWeek = [];
        foreach ($laporanList as $laporan) {
            $latestByWeek[$laporan->minggu_ke] = $laporan;
        }

        $aktualByBulan = [];
        $lastReportByBulan = [];
        foreach ($latestByWeek as $week => $laporan) {
            $bulanKe = $mingguBulanMap[$week] ?? null;
            if ($bulanKe === null) {
                continue;
            }

            $tanggal = $laporan->tanggal_laporan ?? $laporan->created_at;
            if (! isset($lastReportByBulan[$bulanKe]) || $tanggal->gt($lastReportByBulan[$bulanKe])) {
                $lastReportByBulan[$bulanKe] = $tanggal;
            }

            foreach ($laporan->laporanProgress as $prog) {
                if (! in_array($prog->work_group_id, $workGroupIds)) {
                    continue;
                }
                $aktualByBulan[$bulanKe][$prog->work_group_id] = round(($aktualByBulan[$bulanKe][$prog->work_group_id] ?? 0) + (float) $prog->pct_realisasi, 4);
            }
        }

        // Parity dengan getProgressHistory: hanya bulan yang sudah ada laporannya.
        $history = [];
        foreach ($bulanList as $bulanKe => $bulan) {
            if (! isset($aktualByBulan[$bulanKe])) {
                continue;
            }

            $plans = [];
            foreach ($workGroups as $wg) {
                $plans[$wg->id] = round((float) ($planByBulan[$bulanKe][$wg->id] ?? 0), 2);
            }

            $history[] = [
                'unit_ke' => $bulanKe,
                'bulan_ke' => $bulanKe,
                'progress' => $aktualByBulan[$bulanKe],
                'plans' => $plans,
                'created_at' => $lastReportByBulan[$bulanKe]->format('d M Y'),
                'periode_mulai' => $bulan['mulai']->format('d M Y'),
                'periode_selesai' => $bulan['selesai']->format('d M Y'),
            ];
        }

        return $this->addCumulativeTotalsToHistory($history, $workGroups);
    }

    /**
     * Ambil history progress per work group untuk semua minggu yang sudah ada laporannya.
     * Returns: [['minggu_ke' => 1, 'progress' => [wg_id => pct_realisasi], 'plans' => [wg_id => pct_rencana], 'created_at' => '...'], ...]
     */
    public function getProgressHistory(DataKapal $dataKapal): array
    {
        $laporanList = LaporanMingguan::where('data_kapal_id', $dataKapal->id)
            ->whereNotNull('minggu_ke')
            ->whereHas('laporanProgress')
            ->with(['laporanProgress'])
            ->orderBy('minggu_ke')
            ->orderBy('created_at')
            ->get();

        // Get all work groups for this data kapal
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)
            ->with(['kurvaSRencana' => fn ($q) => $q->orderBy('bulan_ke')])
            ->orderBy('sort_order')
            ->get();

        // Plan per minggu diturunkan dari rencana bulanan (dibagi rata ke minggunya)
        $weeklyPlan = $this->getWeeklyPlanMap($dataKapal, $workGroups);

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
                $planMap[$week][$wg->id] = round((float) ($weeklyPlan[$wg->id][$week] ?? 0), 3);
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
     * tertentu — override mingguan tersimpan atau bagi-rata dari bulanan.
     * Returns: [work_group_id => pct_rencana, ...]
     */
    public function getWeekPlans(DataKapal $dataKapal, int $mingguKe): array
    {
        return collect($this->getWeeklyPlanMap($dataKapal))
            ->mapWithKeys(fn ($weeks, $workGroupId) => [
                $workGroupId => round((float) ($weeks[$mingguKe] ?? 0), 3),
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
