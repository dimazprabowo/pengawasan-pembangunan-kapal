<?php

namespace App\Livewire\MasterData;

use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Services\DataKapalService;
use App\Services\KontrakPeriodeService;
use App\Services\KurvaSService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class KurvaSManagement extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public bool $showModal = false;

    public ?int $dataKapalId = null;

    public ?string $dataKapalNama = null;

    // Import modal states
    public bool $showKurvaSImportModal = false;

    public $kurvaS_file;

    /**
     * Array of work groups:
     * [
     *   [
     *     'id'    => null|int,
     *     'nama'  => 'Engineering & Design',
     *     'bobot' => '5.00',
     *     'months' => [['bulan_ke' => 1, 'pct_rencana' => '3.00', 'keterangan' => '',
     *                   'weeks' => [['minggu_ke' => 1, 'pct_rencana' => '0.750'], ...]], ...]
     *   ],
     *   ...
     * ]
     * 'weeks' hanya ada pada bulan yang dibagi manual (Bagi Rata / edit
     * mingguan); bulan tanpa 'weeks' memakai bagi-rata otomatis di sistem.
     */
    public array $workGroups = [];

    /** Index work group yang sedang di-expand untuk edit bulan (-1 = none) */
    public int $expandedGroupIdx = -1;

    /** Key "{gi}-{mi}" bulan yang panel pembagian minggunya sedang dibuka */
    public ?string $expandedWeeksKey = null;

    /** Jumlah bulan bawaan untuk work group baru (dari kontrak → group existing → default) */
    public int $totalBulan = 4;

    /** Periode kontrak (hari kalender) — disimpan di data_kapal */
    public ?string $tanggal_mulai_kontrak = null;

    public ?int $durasi_kontrak_hari = null;

    /** Konfirmasi hapus work group */
    public ?int $confirmDeleteGroupIdx = null;

    /** Konfirmasi reset nilai rencana bulan (slot bulan tidak dihapus) */
    public ?int $confirmResetMonthGroupIdx = null;

    public ?int $confirmResetMonthIdx = null;

    /** Konfirmasi terapkan bulan kontrak ke semua work group */
    public bool $confirmApplyMonths = false;

    /** Render lazy detail periode — tabel baru digenerate saat user membuka panel. */
    public bool $showPeriodeDetail = false;

    #[On('open-kurvas-modal')]
    public function openModal(int $dataKapalId): void
    {
        $dataKapal = DataKapal::findOrFail($dataKapalId);
        $this->authorize('managekurvaSRencana', $dataKapal);

        $this->dataKapalId = $dataKapal->id;
        $this->dataKapalNama = $dataKapal->nama;
        $this->expandedGroupIdx = -1;
        $this->loadData();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['dataKapalId', 'dataKapalNama', 'workGroups', 'expandedGroupIdx', 'expandedWeeksKey', 'totalBulan', 'tanggal_mulai_kontrak', 'durasi_kontrak_hari', 'confirmApplyMonths', 'showPeriodeDetail']);
        $this->resetValidation();
    }

    private function loadData(): void
    {
        $dataKapal = DataKapal::findOrFail($this->dataKapalId);
        $groups = $dataKapal->kurvaSWorkGroups()
            ->with(['kurvaSRencana', 'kurvaSRencanaMingguan'])
            ->get();

        // Range minggu per bulan berdasarkan kontrak TERSIMPAN — untuk memetakan
        // rencana mingguan tersimpan ke slot bulan yang sesuai.
        $bulanRanges = $dataKapal->hasKontrak()
            ? collect(app(KontrakPeriodeService::class)->getBulanList($dataKapal))->keyBy('bulan_ke')
            : collect();

        $this->workGroups = $groups->map(function ($wg) use ($bulanRanges) {
            $storedMinggu = $wg->kurvaSRencanaMingguan->pluck('pct_rencana', 'minggu_ke');

            return [
                'id' => $wg->id,
                'nama' => $wg->nama,
                'bobot' => number_format((float) $wg->bobot, 2, '.', ''),
                'months' => $wg->kurvaSRencana->map(function ($r) use ($storedMinggu, $bulanRanges) {
                    $month = [
                        'bulan_ke' => $r->bulan_ke,
                        'pct_rencana' => number_format((float) $r->pct_rencana, 2, '.', ''),
                        'keterangan' => $r->keterangan ?? '',
                    ];

                    // Lampirkan 'weeks' hanya bila bulan ini punya override
                    // mingguan tersimpan — seluruh minggu bulan ditampilkan,
                    // yang tidak tersimpan diisi 0.
                    $bulan = $bulanRanges->get($r->bulan_ke);
                    if ($bulan) {
                        $mingguRange = range($bulan['minggu_dari'], $bulan['minggu_sampai']);
                        if (collect($mingguRange)->contains(fn ($m) => $storedMinggu->has($m))) {
                            $month['weeks'] = collect($mingguRange)
                                ->map(fn ($m) => [
                                    'minggu_ke' => $m,
                                    'pct_rencana' => number_format((float) ($storedMinggu[$m] ?? 0), 3, '.', ''),
                                ])->values()->all();
                        }
                    }

                    return $month;
                })->toArray(),
            ];
        })->toArray();

        $this->tanggal_mulai_kontrak = $dataKapal->tanggal_mulai_kontrak?->format('Y-m-d');
        $this->durasi_kontrak_hari = $dataKapal->durasi_kontrak_hari;

        $totalBulanKontrak = $this->totalBulanKontrak();
        if ($totalBulanKontrak > 0) {
            $this->totalBulan = $totalBulanKontrak;
        } elseif (! empty($this->workGroups)) {
            $this->totalBulan = max(1, count($this->workGroups[0]['months'] ?? []));
        }
    }

    /**
     * Total bulan periode dari input kontrak di form (belum tentu tersimpan).
     * Bulan = pengelompokan minggu berdasarkan mayoritas tanggal
     * (lihat KontrakPeriodeService::getBulanList).
     */
    private function totalBulanKontrak(): int
    {
        if (! $this->tanggal_mulai_kontrak || ! $this->durasi_kontrak_hari) {
            return 0;
        }

        return app(KontrakPeriodeService::class)->getTotalBulan($this->kontrakDraft());
    }

    /**
     * Instance DataKapal sementara berisi input kontrak form (untuk kalkulasi service).
     */
    private function kontrakDraft(): DataKapal
    {
        return new DataKapal([
            'tanggal_mulai_kontrak' => $this->tanggal_mulai_kontrak ?: null,
            'durasi_kontrak_hari' => $this->durasi_kontrak_hari ?: null,
        ]);
    }

    public function updatedTanggalMulaiKontrak(): void
    {
        $this->syncTotalBulanDariKontrak();
    }

    public function updatedDurasiKontrakHari(): void
    {
        $this->syncTotalBulanDariKontrak();
    }

    /**
     * Saat kontrak terisi lengkap, jumlah bulan bawaan mengikuti periode kontrak.
     * Perubahan kontrak menggeser cakupan minggu per bulan → override mingguan
     * di form dikosongkan (data tersimpan tetap aman sampai user save).
     */
    private function syncTotalBulanDariKontrak(): void
    {
        $total = $this->totalBulanKontrak();
        if ($total > 0) {
            $this->totalBulan = $total;
        }
        $this->clearAllWeeks();
    }

    /**
     * Hapus semua data 'weeks' di form — minggu kembali memakai bagi-rata
     * bulanan sampai user mengisi ulang via Bagi Rata / panel mingguan.
     */
    private function clearAllWeeks(): void
    {
        foreach ($this->workGroups as $gi => $wg) {
            foreach ($wg['months'] ?? [] as $mi => $m) {
                unset($this->workGroups[$gi]['months'][$mi]['weeks']);
            }
        }
        $this->expandedWeeksKey = null;
    }

    /**
     * Minta konfirmasi sebelum menerapkan bulan kontrak — penyesuaian dapat
     * memangkas bulan beserta nilai rencana yang sudah diisi.
     */
    public function requestApplyContractMonths(): void
    {
        if ($this->totalBulanKontrak() <= 0) {
            $this->notifyWarning('Isi tanggal mulai & durasi kontrak terlebih dahulu.');

            return;
        }

        if (count($this->workGroups) === 0) {
            $this->notifyWarning('Tidak ada work group. Tambahkan work group terlebih dahulu.');

            return;
        }

        $this->confirmApplyMonths = true;
    }

    /**
     * Toggle panel detail periode — konten tabel baru digenerate saat panel
     * dibuka agar tidak ikut ter-render pada setiap update input kontrak.
     */
    public function togglePeriodeDetail(): void
    {
        $this->showPeriodeDetail = ! $this->showPeriodeDetail;
    }

    public function cancelApplyContractMonths(): void
    {
        $this->confirmApplyMonths = false;
    }

    public function confirmApplyContractMonths(): void
    {
        $this->applyContractMonths();
        $this->confirmApplyMonths = false;
    }

    /**
     * Sesuaikan jumlah bulan SEMUA work group ke total bulan kontrak.
     * Dipicu manual via tombol di card kontrak — kontrak boleh belum tersimpan.
     */
    public function applyContractMonths(): void
    {
        $total = $this->totalBulanKontrak();

        if ($total <= 0) {
            $this->notifyWarning('Isi tanggal mulai & durasi kontrak terlebih dahulu.');

            return;
        }

        if (count($this->workGroups) === 0) {
            $this->notifyWarning('Tidak ada work group. Tambahkan work group terlebih dahulu.');

            return;
        }

        foreach ($this->workGroups as $gi => $wg) {
            $current = count($wg['months']);
            if ($total > $current) {
                for ($i = $current + 1; $i <= $total; $i++) {
                    $this->workGroups[$gi]['months'][] = [
                        'bulan_ke' => $i,
                        'pct_rencana' => '0.00',
                        'keterangan' => '',
                    ];
                }
            } elseif ($total < $current) {
                $this->workGroups[$gi]['months'] = array_slice($wg['months'], 0, $total);
            }
        }

        $this->totalBulan = $total;
        $this->expandedWeeksKey = null;

        $this->notifyInfo(count($this->workGroups)." work group disesuaikan ke {$total} bulan sesuai kontrak.");
    }

    // ─── Work Group Actions ──────────────────────────────────────────────────

    public function addWorkGroup(): void
    {
        if ($this->totalBulanKontrak() <= 0) {
            $this->notifyWarning('Isi tanggal mulai & durasi kontrak terlebih dahulu — periode bulan rencana diturunkan dari kontrak.');

            return;
        }

        $months = [];
        for ($i = 1; $i <= max(1, $this->totalBulan); $i++) {
            $months[] = ['bulan_ke' => $i, 'pct_rencana' => '0.00', 'keterangan' => ''];
        }

        $this->workGroups[] = [
            'id' => null,
            'nama' => '',
            'bobot' => '0.00',
            'months' => $months,
        ];

        $this->expandedGroupIdx = count($this->workGroups) - 1;
    }

    public function requestDeleteGroup(int $idx): void
    {
        $this->confirmDeleteGroupIdx = $idx;
    }

    public function cancelDeleteGroup(): void
    {
        $this->confirmDeleteGroupIdx = null;
    }

    public function confirmDeleteGroup(): void
    {
        if ($this->confirmDeleteGroupIdx !== null) {
            $this->removeWorkGroup($this->confirmDeleteGroupIdx);
        }
        $this->confirmDeleteGroupIdx = null;
    }

    private function removeWorkGroup(int $idx): void
    {
        array_splice($this->workGroups, $idx, 1);
        $this->expandedWeeksKey = null;
        if ($this->expandedGroupIdx === $idx) {
            $this->expandedGroupIdx = -1;
        } elseif ($this->expandedGroupIdx > $idx) {
            $this->expandedGroupIdx--;
        }
    }

    public function toggleGroup(int $idx): void
    {
        $this->expandedGroupIdx = $this->expandedGroupIdx === $idx ? -1 : $idx;
        $this->expandedWeeksKey = null;
    }

    /**
     * Sinkronisasi nilai input workGroups ke server TANPA render ulang.
     * Dipanggil via debounce saat mengetik (bobot/pct_rencana): pembaruan
     * properti ikut terkirim pada payload commit; skipRender menghindari
     * render Blade + morph penuh sehingga respons tetap ringan.
     */
    public function syncDraft(): void
    {
        $this->skipRender();
    }

    // ─── Month Actions ───────────────────────────────────────────────────────

    public function requestResetMonth(int $groupIdx, int $monthIdx): void
    {
        $this->confirmResetMonthGroupIdx = $groupIdx;
        $this->confirmResetMonthIdx = $monthIdx;
    }

    public function cancelResetMonth(): void
    {
        $this->confirmResetMonthGroupIdx = null;
        $this->confirmResetMonthIdx = null;
    }

    public function confirmResetMonth(): void
    {
        if ($this->confirmResetMonthGroupIdx !== null && $this->confirmResetMonthIdx !== null) {
            $this->resetMonthInGroup($this->confirmResetMonthGroupIdx, $this->confirmResetMonthIdx);
        }
        $this->confirmResetMonthGroupIdx = null;
        $this->confirmResetMonthIdx = null;
    }

    private function resetMonthInGroup(int $groupIdx, int $monthIdx): void
    {
        $this->workGroups[$groupIdx]['months'][$monthIdx]['pct_rencana'] = '0.00';
        $this->workGroups[$groupIdx]['months'][$monthIdx]['keterangan'] = '';
        unset($this->workGroups[$groupIdx]['months'][$monthIdx]['weeks']);
        if ($this->expandedWeeksKey === $groupIdx.'-'.$monthIdx) {
            $this->expandedWeeksKey = null;
        }
    }

    // ─── Weekly Split Actions ───────────────────────────────────────────────

    /**
     * Bagi rata pct bulan ke minggu-minggunya (truncate 3 desimal, sisa ke
     * minggu terakhir) — mengisi override mingguan bulan tsb di form.
     */
    public function bagiRata(int $gi, int $mi): void
    {
        if (! $this->fillWeeksFromMonthly($gi, $mi)) {
            $this->notifyWarning('Minggu untuk bulan ini tidak tersedia — pastikan kontrak terisi.');

            return;
        }

        $this->expandedWeeksKey = $gi.'-'.$mi;
        $weeks = $this->workGroups[$gi]['months'][$mi]['weeks'];
        $this->notifyInfo('Rencana bulan ke-'.($this->workGroups[$gi]['months'][$mi]['bulan_ke']).' dibagi rata ke '.count($weeks).' minggu.');
    }

    /**
     * Buka/tutup panel pembagian mingguan sebuah bulan. Saat pertama dibuka
     * dan belum ada data mingguan, diisi bagi-rata bulanan sebagai titik awal.
     */
    public function toggleWeeks(int $gi, int $mi): void
    {
        $key = $gi.'-'.$mi;
        if ($this->expandedWeeksKey === $key) {
            $this->expandedWeeksKey = null;

            return;
        }

        if (empty($this->workGroups[$gi]['months'][$mi]['weeks'] ?? [])) {
            if (! $this->fillWeeksFromMonthly($gi, $mi)) {
                $this->notifyWarning('Minggu untuk bulan ini tidak tersedia — pastikan kontrak terisi.');

                return;
            }
        }

        $this->expandedWeeksKey = $key;
    }

    /**
     * Hapus override mingguan sebuah bulan — bulan tsb kembali memakai
     * bagi-rata otomatis dari pct bulanan.
     */
    public function clearWeeks(int $gi, int $mi): void
    {
        unset($this->workGroups[$gi]['months'][$mi]['weeks']);
        if ($this->expandedWeeksKey === $gi.'-'.$mi) {
            $this->expandedWeeksKey = null;
        }
        $this->notifyInfo('Pembagian mingguan bulan ini dihapus — kembali ke bagi rata otomatis.');
    }

    /**
     * Isi 'weeks' sebuah bulan dari pct bulanan dibagi rata ke cakupan
     * minggunya (periode kontrak form). False bila kontrak/bulan tak valid.
     */
    private function fillWeeksFromMonthly(int $gi, int $mi): bool
    {
        $month = $this->workGroups[$gi]['months'][$mi] ?? null;
        if (! $month) {
            return false;
        }

        $mingguList = $this->mingguPerBulan[(int) $month['bulan_ke']] ?? [];
        if (empty($mingguList)) {
            return false;
        }

        $split = app(KurvaSService::class)->splitToWeeks((float) ($month['pct_rencana'] ?? 0), count($mingguList));

        $this->workGroups[$gi]['months'][$mi]['weeks'] = collect($mingguList)
            ->values()
            ->map(fn ($w, $i) => [
                'minggu_ke' => $w['minggu_ke'],
                'pct_rencana' => number_format($split[$i] ?? 0, 3, '.', ''),
            ])->values()->all();

        return true;
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    public function getTotalBobotProperty(): float
    {
        return array_reduce($this->workGroups, fn ($c, $wg) => $c + (float) ($wg['bobot'] ?? 0), 0.0);
    }

    /**
     * Ringkasan periode kontrak dari input form (untuk preview live di modal).
     */
    public function getKontrakSummaryProperty(): ?array
    {
        $draft = $this->kontrakDraft();
        if (! $draft->hasKontrak()) {
            return null;
        }

        $service = app(KontrakPeriodeService::class);

        return [
            'selesai' => $draft->tanggal_selesai_kontrak->translatedFormat('d M Y'),
            'total_minggu' => $service->getTotalMinggu($draft),
            'total_bulan' => $service->getTotalBulan($draft),
        ];
    }

    /**
     * Preview detail periodisasi hasil generate sistem (untuk verifikasi user):
     * tiap minggu proyek → rentang tanggal; tiap bulan kalender → rentang minggu + tanggal.
     */
    public function getPeriodePreviewProperty(): ?array
    {
        $draft = $this->kontrakDraft();
        if (! $draft->hasKontrak()) {
            return null;
        }

        $service = app(KontrakPeriodeService::class);
        $mingguBulanMap = $service->getMingguBulanMap($draft);

        return [
            'minggu' => collect($service->getMingguList($draft))
                ->map(fn ($m) => [
                    'minggu_ke' => $m['minggu_ke'],
                    'bulan_ke' => $mingguBulanMap[$m['minggu_ke']] ?? null,
                    'periode' => $m['label'],
                    'total_hari' => $m['total_hari'],
                ])
                ->all(),
            'bulan' => collect($service->getBulanList($draft))
                ->map(fn ($b) => [
                    'bulan_ke' => $b['bulan_ke'],
                    'nama' => $b['label'],
                    'minggu_dari' => $b['minggu_dari'],
                    'minggu_sampai' => $b['minggu_sampai'],
                    'total_minggu' => $b['total_minggu'],
                    'periode' => $service->formatRange($b['mulai'], $b['selesai']),
                    'total_hari' => $b['total_hari'],
                ])
                ->all(),
        ];
    }

    /**
     * Meta tiap bulan periode (label nama bulan + cakupan minggu) untuk
     * ditampilkan di kolom input rencana bulanan. Key = bulan_ke.
     */
    public function getBulanMetaProperty(): array
    {
        $draft = $this->kontrakDraft();
        if (! $draft->hasKontrak()) {
            return [];
        }

        return collect(app(KontrakPeriodeService::class)->getBulanList($draft))
            ->mapWithKeys(fn ($b) => [$b['bulan_ke'] => [
                'nama' => $b['label'],
                'cakupan' => $b['minggu_dari'] === $b['minggu_sampai']
                    ? 'Mgg '.$b['minggu_dari']
                    : 'Mgg '.$b['minggu_dari'].'–'.$b['minggu_sampai'],
            ]])
            ->all();
    }

    /**
     * Daftar minggu per bulan periode kontrak (input form). Key = bulan_ke,
     * tiap entri: ['minggu_ke' => int, 'periode' => 'dd - dd Mmm YYYY'].
     * Dipakai untuk panel pembagian mingguan dan Bagi Rata.
     */
    public function getMingguPerBulanProperty(): array
    {
        $draft = $this->kontrakDraft();
        if (! $draft->hasKontrak()) {
            return [];
        }

        $service = app(KontrakPeriodeService::class);
        $mingguList = collect($service->getMingguList($draft))->keyBy('minggu_ke');

        return collect($service->getBulanList($draft))
            ->mapWithKeys(fn ($b) => [$b['bulan_ke'] => collect(
                range($b['minggu_dari'], $b['minggu_sampai'])
            )->mapWithKeys(fn ($m) => [$m => [
                'minggu_ke' => $m,
                'periode' => $mingguList[$m]['label'] ?? '',
            ]])->all()])
            ->all();
    }

    // ─── Validation ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $maxBulan = $this->totalBulanKontrak();
        $bulanKeRule = 'required|integer|min:1'.($maxBulan > 0 ? '|max:'.$maxBulan : '');

        return [
            'tanggal_mulai_kontrak' => 'nullable|date|required_with:durasi_kontrak_hari',
            'durasi_kontrak_hari' => 'nullable|integer|min:1|max:7300|required_with:tanggal_mulai_kontrak',
            'workGroups' => 'required|array|min:1',
            'workGroups.*.nama' => 'required|string|max:255',
            'workGroups.*.bobot' => 'required|numeric|min:0|max:100',
            'workGroups.*.months' => 'array',
            'workGroups.*.months.*.bulan_ke' => $bulanKeRule,
            'workGroups.*.months.*.pct_rencana' => 'required|numeric|min:0|max:100',
            'workGroups.*.months.*.keterangan' => 'nullable|string|max:255',
            'workGroups.*.months.*.weeks' => 'array',
            'workGroups.*.months.*.weeks.*.minggu_ke' => 'required|integer|min:1',
            'workGroups.*.months.*.weeks.*.pct_rencana' => 'required|numeric|min:0|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            'tanggal_mulai_kontrak.required_with' => 'Tanggal mulai kontrak wajib diisi jika durasi diisi.',
            'durasi_kontrak_hari.required_with' => 'Durasi kontrak wajib diisi jika tanggal mulai diisi.',
            'durasi_kontrak_hari.min' => 'Durasi kontrak minimal 1 hari.',
            'workGroups.required' => 'Minimal harus ada 1 work group.',
            'workGroups.min' => 'Minimal harus ada 1 work group.',
            'workGroups.*.nama.required' => 'Nama work group wajib diisi.',
            'workGroups.*.bobot.required' => 'Bobot wajib diisi.',
            'workGroups.*.bobot.numeric' => 'Bobot harus berupa angka.',
            'workGroups.*.months.*.bulan_ke.max' => 'Nomor bulan melebihi total bulan kontrak.',
            'workGroups.*.months.*.pct_rencana.required' => 'Rencana % wajib diisi.',
            'workGroups.*.months.*.pct_rencana.max' => 'Rencana % per bulan maksimal 100%.',
            'workGroups.*.months.*.weeks.*.pct_rencana.required' => 'Rencana % per minggu wajib diisi.',
            'workGroups.*.months.*.weeks.*.pct_rencana.max' => 'Rencana % per minggu maksimal 100%.',
        ];
    }

    // ─── Save ────────────────────────────────────────────────────────────────

    public function save(KurvaSService $service): void
    {
        $dataKapal = DataKapal::findOrFail($this->dataKapalId);
        $this->authorize('managekurvaSRencana', $dataKapal);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        // Rencana bulanan memerlukan kontrak — periode bulan diturunkan dari kontrak
        $hasMonths = collect($this->workGroups)->contains(fn ($wg) => count($wg['months'] ?? []) > 0);
        if ($hasMonths && $this->totalBulanKontrak() <= 0) {
            $this->notifyError('Isi tanggal mulai & durasi kontrak terlebih dahulu — periode bulan rencana diturunkan dari kontrak.');

            return;
        }

        $total = $this->totalBobot;
        if ($total > 100.01) {
            $this->notifyError('Total bobot semua work group tidak boleh melebihi 100%. Saat ini: '.number_format($total, 2).'%');

            return;
        }

        // Total rencana (poin % proyek) per work group tidak boleh melebihi bobotnya.
        // "Mingguan menang": bulan dengan override mingguan dihitung dari total minggunya.
        $kurang = [];
        foreach ($this->workGroups as $wg) {
            $bobot = (float) ($wg['bobot'] ?? 0);
            $sum = array_reduce($wg['months'] ?? [], function ($c, $m) {
                if (! empty($m['weeks'])) {
                    return $c + array_reduce($m['weeks'], fn ($cw, $w) => $cw + (float) ($w['pct_rencana'] ?? 0), 0.0);
                }

                return $c + (float) ($m['pct_rencana'] ?? 0);
            }, 0.0);
            $nama = trim($wg['nama'] ?? '') ?: 'Tanpa Nama';

            if ($sum > $bobot + 0.01) {
                $this->notifyError('Total rencana work group "'.$nama.'" ('.number_format($sum, 2).'%) melebihi bobotnya ('.number_format($bobot, 2).'%).');

                return;
            }
            if ($sum < $bobot - 0.01) {
                $kurang[] = '"'.$nama.'" '.number_format($sum, 2).'/'.number_format($bobot, 2).'%';
            }
        }

        try {
            app(KontrakPeriodeService::class)->saveKontrak(
                $dataKapal,
                $this->tanggal_mulai_kontrak,
                $this->durasi_kontrak_hari !== null ? (int) $this->durasi_kontrak_hari : null
            );
            $service->saveWorkGroups($dataKapal, $this->workGroups);

            $warnings = [];
            if (abs($total - 100.0) > 0.01) {
                $warnings[] = 'Total bobot: '.number_format($total, 2).'% — belum 100%';
            }
            if (! empty($kurang)) {
                $warnings[] = 'Rencana belum penuh: '.implode(', ', $kurang);
            }
            $warning = $warnings ? ' ('.implode('; ', $warnings).')' : '';

            $this->notifySuccess('Kurva S berhasil disimpan!'.$warning);
            $this->closeModal();
            $this->dispatch('kurvas-saved');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk mengatur Kurva S.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    // ─── Export & Import ─────────────────────────────────────────────────────

    public function exportKurvaSTemplate(bool $withData, DataKapalService $service)
    {
        try {
            $dataKapal = DataKapal::findOrFail($this->dataKapalId);
            $this->authorize('exportKurvaSTemplate', $dataKapal);

            return $service->exportKurvaSTemplate($dataKapal, $withData);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk export template Kurva S.');
        } catch (\Exception $e) {
            \Log::error('Export Kurva S gagal', ['data_kapal_id' => $this->dataKapalId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem saat export. Silakan coba lagi.');
        }
    }

    public function openKurvaSImportModal()
    {
        try {
            $dataKapal = DataKapal::findOrFail($this->dataKapalId);
            $this->authorize('importKurvaSTemplate', $dataKapal);

            $this->showKurvaSImportModal = true;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk import template Kurva S.');
        }
    }

    public function closeKurvaSImportModal()
    {
        $this->showKurvaSImportModal = false;
        $this->reset(['kurvaS_file']);
        $this->resetValidation();
    }

    public function importKurvaSTemplate(DataKapalService $service)
    {
        try {
            $this->validate([
                'kurvaS_file' => 'required|file|mimes:xlsx,xls|max:5120',
            ], [
                'kurvaS_file.required' => 'File template wajib diupload',
                'kurvaS_file.file' => 'File tidak valid',
                'kurvaS_file.mimes' => 'File harus berformat Excel (.xlsx atau .xls)',
                'kurvaS_file.max' => 'Ukuran file maksimal 5MB',
            ], [
                'kurvaS_file' => 'file template',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $dataKapal = DataKapal::findOrFail($this->dataKapalId);
            $this->authorize('importKurvaSTemplate', $dataKapal);

            $result = $service->importKurvaSTemplate($dataKapal, $this->kurvaS_file);

            if ($result['success']) {
                $this->notifySuccess($result['message'].' Total work groups: '.$result['work_groups_count']);
                $this->closeKurvaSImportModal();
                $this->loadData(); // Reload data to show imported data
                $this->dispatch('kurvas-imported');
            } else {
                $errorMessage = 'Import gagal:';
                foreach ($result['errors'] as $error) {
                    $errorMessage .= "\n• ".$error;
                }
                $this->notifyError($errorMessage);
            }
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk import template Kurva S.');
        } catch (\Exception $e) {
            \Log::error('Import Kurva S gagal', ['data_kapal_id' => $this->dataKapalId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem saat import. Silakan coba lagi.');
        }
    }

    public function render()
    {
        return view('livewire.master-data.kurva-s-management');
    }
}
