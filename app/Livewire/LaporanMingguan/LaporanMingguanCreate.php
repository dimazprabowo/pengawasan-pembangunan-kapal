<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\LaporanMingguan;
use App\Services\KurvaSService;
use App\Services\LaporanMingguanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Tambah Laporan Mingguan'])]
class LaporanMingguanCreate extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?int $jenis_kapal_id = null;
    public string $judul = '';
    public string $tanggal_laporan = '';
    public string $periode_mulai = '';
    public string $periode_selesai = '';
    public string $ringkasan = '';
    public array $laporan_harian_ids = [];
    public array $availableLaporanHarian = [];
    public array $lampiran_ids = [];

    // Kurva S
    public ?int $minggu_ke = null;
    public array $progressPerGroup = [];
    public array $workGroupsForInput = [];
    public array $mingguOptions = [];
    public bool $hasKurvaS = false;
    public array $progressHistory = [];

    // Track previous laporan_harian_ids for filtering lampiran
    public array $previousLaporanHarianIds = [];

    // Lampiran Harian Modal
    public bool $showLampiranModal = false;
    public array $lampiranHarianList = [];
    public bool $loadingLampiran = false;
    public bool $showPreviewModal = false;
    public ?int $previewLampiranId = null;

    public function mount(): void
    {
        $this->authorize('create', LaporanMingguan::class);
        $this->jenis_kapal_id = session('laporan_harian_jenis_kapal_id');
        $this->tanggal_laporan = now()->format('Y-m-d');
        $this->loadAvailableLaporanHarian();
        $this->loadKurvaSOptions();
    }

    public function updatedJenisKapalId(): void
    {
        $this->laporan_harian_ids = [];
        $this->lampiran_ids = [];
        $this->minggu_ke = null;
        $this->progressPerGroup = [];
        $this->loadAvailableLaporanHarian();
        $this->loadKurvaSOptions();
    }

    private function loadKurvaSOptions(): void
    {
        if (!$this->jenis_kapal_id) {
            $this->mingguOptions       = [];
            $this->hasKurvaS           = false;
            $this->workGroupsForInput  = [];
            $this->progressPerGroup    = [];
            return;
        }

        $jenisKapal = JenisKapal::find($this->jenis_kapal_id);
        $service    = app(KurvaSService::class);

        $this->hasKurvaS = $jenisKapal && $service->hasRencana($jenisKapal);

        if ($jenisKapal && $this->hasKurvaS) {
            $this->mingguOptions      = $service->getMingguOptions($jenisKapal, null);
            $this->workGroupsForInput = $service->getProgressInputData(
                new \App\Models\LaporanMingguan(['jenis_kapal_id' => $this->jenis_kapal_id])
            );
            $this->progressPerGroup   = array_column($this->workGroupsForInput, 'pct_realisasi', 'work_group_id');
            $this->progressHistory    = $service->getProgressHistory($jenisKapal);
        } else {
            $this->mingguOptions      = [];
            $this->workGroupsForInput = [];
            $this->progressPerGroup   = [];
        }
    }

    public function updatedPeriodeMulai(): void
    {
        $this->laporan_harian_ids = [];
        $this->lampiran_ids = [];
        $this->loadAvailableLaporanHarian();
    }

    public function updatedPeriodeSelesai(): void
    {
        $this->laporan_harian_ids = [];
        $this->lampiran_ids = [];
        $this->loadAvailableLaporanHarian();
    }

    private function loadAvailableLaporanHarian(): void
    {
        // Return empty if period is not selected
        if (!$this->periode_mulai || !$this->periode_selesai) {
            $this->availableLaporanHarian = [];
            $this->laporan_harian_ids = [];
            return;
        }

        // Return empty if jenis kapal is not selected
        if (!$this->jenis_kapal_id) {
            $this->availableLaporanHarian = [];
            $this->laporan_harian_ids = [];
            return;
        }

        $query = LaporanHarian::with(['user', 'jenisKapal'])
            ->byUser(auth()->id())
            ->orderByDesc('tanggal_laporan');

        $query->where('jenis_kapal_id', $this->jenis_kapal_id);

        // Filter by period
        $query->whereBetween('tanggal_laporan', [$this->periode_mulai, $this->periode_selesai]);

        $this->availableLaporanHarian = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'tanggal' => $item->tanggal_laporan->format('d M Y'),
            ];
        })->toArray();

        // Auto-select all available laporan harian
        $this->laporan_harian_ids = collect($this->availableLaporanHarian)->pluck('id')->toArray();
        $this->previousLaporanHarianIds = $this->laporan_harian_ids;
        $this->loadLampiranHarian();
    }

    public function updatedLaporanHarianIds(): void
    {
        // Get lampiran IDs that belong to the unchecked laporan harian
        $removedLaporanHarianIds = array_diff(
            $this->previousLaporanHarianIds,
            $this->laporan_harian_ids
        );

        if (count($removedLaporanHarianIds) > 0) {
            // Get lampiran IDs that belong to the removed laporan harian
            $lampiranToRemove = LaporanLampiran::whereHas('laporanHarian', function ($q) use ($removedLaporanHarianIds) {
                $q->whereIn('id', $removedLaporanHarianIds);
            })->pluck('id')->toArray();

            // Remove only the lampiran that belong to the unchecked laporan harian
            $this->lampiran_ids = array_values(array_diff($this->lampiran_ids, $lampiranToRemove));
        }

        // Update previous state for next change
        $this->previousLaporanHarianIds = $this->laporan_harian_ids;

        // Reload lampiran list to update the UI
        $this->loadLampiranHarian();
    }
    protected function rules(): array
    {
        return [
            'jenis_kapal_id'      => 'required|exists:jenis_kapal,id',
            'judul'               => 'required|string|max:255',
            'tanggal_laporan'     => 'required|date',
            'periode_mulai'       => 'required|date|before_or_equal:periode_selesai',
            'periode_selesai'     => 'required|date|after_or_equal:periode_mulai',
            'ringkasan'           => 'nullable|string',
            'laporan_harian_ids'  => 'required|array|min:1',
            'laporan_harian_ids.*'=> 'exists:laporan_harian,id',
            'lampiran_ids'        => 'array',
            'lampiran_ids.*'      => 'exists:laporan_lampiran,id',
            'minggu_ke'              => 'nullable|integer|min:1',
            'progressPerGroup'       => 'array',
            'progressPerGroup.*'     => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'jenis_kapal_id'     => 'jenis kapal',
            'judul'              => 'judul laporan',
            'tanggal_laporan'    => 'tanggal laporan',
            'periode_mulai'      => 'periode mulai',
            'periode_selesai'    => 'periode selesai',
            'ringkasan'          => 'ringkasan',
            'laporan_harian_ids' => 'laporan harian',
            'minggu_ke'          => 'minggu ke',
        ];
    }

    public function openLampiranModal(): void
    {
        if (count($this->laporan_harian_ids) === 0) {
            $this->notifyWarning('Silakan pilih laporan harian terlebih dahulu.');
            return;
        }

        $this->showLampiranModal = true;
        $this->loadLampiranHarian();
    }

    public function closeLampiranModal(): void
    {
        $this->showLampiranModal = false;
    }

    private function loadLampiranHarian(): void
    {
        // Return empty if no laporan harian selected
        if (count($this->laporan_harian_ids) === 0) {
            $this->lampiranHarianList = [];
            $this->loadingLampiran = false;
            return;
        }

        $this->loadingLampiran = true;

        $query = LaporanLampiran::with(['laporanHarian'])
            ->whereHas('laporanHarian', function ($q) {
                $q->byUser(auth()->id())
                    ->whereIn('id', $this->laporan_harian_ids);
            })
            ->where('file_status', 'completed')
            ->orderBy('created_at', 'desc');

        $this->lampiranHarianList = $query->get()->map(function ($item) {
            $extension = strtolower(pathinfo($item->file_name, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

            return [
                'id' => $item->id,
                'file_name' => $item->file_name,
                'file_size_formatted' => number_format($item->file_size / 1024, 1) . ' KB',
                'keterangan' => $item->keterangan,
                'extension' => $extension,
                'is_image' => $isImage,
                'laporan_tanggal' => $item->laporanHarian->tanggal_laporan->format('d M Y'),
                'is_selected' => in_array($item->id, $this->lampiran_ids),
            ];
        })->toArray();

        $this->loadingLampiran = false;
    }

    public function getLampiranPreview(int $lampiranId): ?string
    {
        $lampiran = LaporanLampiran::find($lampiranId);
        
        if (!$lampiran || !$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            return null;
        }

        if (!$lampiran->isImage()) {
            return null;
        }

        return $this->getLampiranImageDataUrl($lampiran);
    }

    private function getLampiranImageDataUrl(LaporanLampiran $lampiran): ?string
    {
        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            return null;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];

        if (!isset($mimeTypes[$extension])) {
            return null;
        }

        try {
            $fileContent = Storage::disk('local')->get($lampiran->file_path);
            $base64 = base64_encode($fileContent);
            $mime = $mimeTypes[$extension];
            return "data:{$mime};base64,{$base64}";
        } catch (\Exception $e) {
            return null;
        }
    }

    public function previewLampiranHarian(int $lampiranId): void
    {
        $lampiran = LaporanLampiran::find($lampiranId);
        
        if (!$lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');
            return;
        }

        $this->previewLampiranId = $lampiranId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewLampiranId = null;
    }

    public function toggleLampiran(int $lampiranId): void
    {
        if (in_array($lampiranId, $this->lampiran_ids)) {
            $this->lampiran_ids = array_diff($this->lampiran_ids, [$lampiranId]);
        } else {
            $this->lampiran_ids[] = $lampiranId;
        }

        // Reload lampiran list to update is_selected state
        $this->loadLampiranHarian();
    }

    public function getPreviewLampiranProperty()
    {
        if (!$this->previewLampiranId) {
            return null;
        }
        return LaporanLampiran::find($this->previewLampiranId);
    }

    public function getPreviewLampiranImageUrlProperty(): ?string
    {
        if (!$this->previewLampiran) {
            return null;
        }
        return $this->getLampiranImageDataUrl($this->previewLampiran);
    }

    public function downloadLampiran(int $lampiranId)
    {
        $this->authorize('lampiranDownload', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (!$lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');
            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian
        if (!$lampiran->laporanHarian || $lampiran->laporanHarian->user_id !== auth()->id()) {
            $this->notifyError('Anda tidak memiliki akses ke lampiran ini.');
            return;
        }

        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        return response()->download(
            Storage::disk('local')->path($lampiran->file_path),
            $lampiran->file_name
        );
    }

    public function previewLampiran(int $lampiranId)
    {
        $this->authorize('lampiranPreview', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (!$lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');
            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian
        if (!$lampiran->laporanHarian || $lampiran->laporanHarian->user_id !== auth()->id()) {
            $this->notifyError('Anda tidak memiliki akses ke lampiran ini.');
            return;
        }

        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response(Storage::disk('local')->get($lampiran->file_path))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $lampiran->file_name . '"');
    }

    public function save(LaporanMingguanService $service): void
    {
        $this->authorize('create', LaporanMingguan::class);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        // Server-side validation for lampiran_ids ownership
        if (count($this->lampiran_ids) > 0) {
            $validLampiranIds = LaporanLampiran::whereHas('laporanHarian', function ($q) {
                $q->byUser(auth()->id())
                    ->whereIn('id', $this->laporan_harian_ids);
            })->whereIn('id', $this->lampiran_ids)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($this->lampiran_ids, $validLampiranIds);
            if (count($invalidIds) > 0) {
                $this->notifyError('Beberapa lampiran tidak valid atau tidak termasuk dalam laporan harian yang dipilih.');
                return;
            }
        }

        try {
            $data = [
                'user_id'            => auth()->id(),
                'jenis_kapal_id'     => $this->jenis_kapal_id,
                'judul'              => $this->judul,
                'tanggal_laporan'    => $this->tanggal_laporan,
                'periode_mulai'      => $this->periode_mulai ?: null,
                'periode_selesai'    => $this->periode_selesai ?: null,
                'ringkasan'          => $this->ringkasan ?: null,
                'minggu_ke'          => $this->minggu_ke ?: null,
                'laporan_harian_ids' => $this->laporan_harian_ids,
                'lampiran_ids'       => $this->lampiran_ids,
            ];

            $laporan = $service->create($data);

            if ($this->hasKurvaS && !empty($this->progressPerGroup)) {
                app(KurvaSService::class)->saveProgress($laporan, $this->progressPerGroup);
            }

            session()->flash('notify', [
                'type' => 'success',
                'message' => 'Laporan mingguan berhasil ditambahkan!',
            ]);

            $this->redirect(route('laporan-mingguan.index'), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function getKontribusiPerGroupProperty(): array
    {
        $kontribusi = [];
        foreach ($this->workGroupsForInput as $wg) {
            $wgId = $wg['work_group_id'];
            $pct = (float)($this->progressPerGroup[$wgId] ?? 0);
            $bobot = (float)$wg['bobot'];
            $kontribusi[$wgId] = round($pct * $bobot / 100, 2);
        }
        return $kontribusi;
    }

    public function getTotalKontribusiHistoryProperty(): array
    {
        $totalKontribusi = [];

        // Calculate history totals (excluding current week)
        $historyTotals = [];
        foreach ($this->workGroupsForInput as $wg) {
            $wgId = $wg['work_group_id'];
            $total = 0;
            foreach ($this->progressHistory as $hist) {
                if (isset($hist['progress'][$wgId]) && $hist['minggu_ke'] != $this->minggu_ke) {
                    $total += (float)$hist['progress'][$wgId] * (float)$wg['bobot'] / 100;
                }
            }
            $historyTotals[$wgId] = $total;
        }

        // Add current week contribution
        $kontribusiPerGroup = $this->kontribusiPerGroup;
        foreach ($this->workGroupsForInput as $wg) {
            $wgId = $wg['work_group_id'];
            $totalKontribusi[$wgId] = round(($historyTotals[$wgId] ?? 0) + ($kontribusiPerGroup[$wgId] ?? 0), 2);
        }

        return $totalKontribusi;
    }

    public function getAnyExceedsBobotProperty(): bool
    {
        $totalKontribusiHistory = $this->totalKontribusiHistory;
        foreach ($this->workGroupsForInput as $wg) {
            $wgId = $wg['work_group_id'];
            if (($totalKontribusiHistory[$wgId] ?? 0) > $wg['bobot']) {
                return true;
            }
        }
        return false;
    }

    public function getTotalKontribusiProperty(): float
    {
        return round(array_sum($this->kontribusiPerGroup), 2);
    }

    public function getTotalAllKontribusiHistoryProperty(): float
    {
        return round(array_sum($this->totalKontribusiHistory), 2);
    }

    public function getFullProgressHistoryProperty(): array
    {
        $fullHistory = $this->progressHistory;
        
        // Add current week if minggu_ke is set
        if ($this->minggu_ke && !empty($this->progressPerGroup)) {
            $currentWeekIndex = array_search($this->minggu_ke, array_column($fullHistory, 'minggu_ke'));
            
            $currentProgress = [];
            foreach ($this->workGroupsForInput as $wg) {
                $wgId = $wg['work_group_id'];
                $currentProgress[$wgId] = (float)($this->progressPerGroup[$wgId] ?? 0);
            }
            
            // Preserve plans from existing entry
            $existingPlans = $currentWeekIndex !== false ? ($fullHistory[$currentWeekIndex]['plans'] ?? []) : [];
            
            $currentEntry = [
                'minggu_ke' => $this->minggu_ke,
                'progress' => $currentProgress,
                'plans' => $existingPlans,
                'created_at' => now()->format('d M Y')
            ];
            
            if ($currentWeekIndex !== false) {
                $fullHistory[$currentWeekIndex] = $currentEntry;
            } else {
                $fullHistory[] = $currentEntry;
            }
        }
        
        // Sort by minggu_ke
        usort($fullHistory, function($a, $b) {
            return $a['minggu_ke'] <=> $b['minggu_ke'];
        });
        
        return $fullHistory;
    }

    public function render(KurvaSService $kurvaSService)
    {
        $kurvaSChartData = [];
        $totalRencana = null;
        $totalAktual = null;

        if ($this->jenis_kapal_id && $this->hasKurvaS) {
            $jenisKapal = JenisKapal::find($this->jenis_kapal_id);
            if ($jenisKapal) {
                $kurvaSChartData = $kurvaSService->getChartData($jenisKapal);

                // Calculate totals from progress history
                if (!empty($this->progressHistory)) {
                    $totalRencana = 0;
                    $totalAktual = 0;

                    foreach ($this->progressHistory as $hist) {
                        // Calculate total rencana for this week
                        if (!empty($hist['plans'])) {
                            foreach ($this->workGroupsForInput as $wg) {
                                $plan = $hist['plans'][$wg['work_group_id']] ?? 0;
                                $totalRencana += $plan * $wg['bobot'] / 100;
                            }
                        }

                        // Calculate total aktual for this week
                        if (!empty($hist['progress'])) {
                            foreach ($this->workGroupsForInput as $wg) {
                                $actual = $hist['progress'][$wg['work_group_id']] ?? 0;
                                $totalAktual += $actual * $wg['bobot'] / 100;
                            }
                        }
                    }

                    $totalRencana = round($totalRencana, 2);
                    $totalAktual = round($totalAktual, 2);
                }
            }
        }

        return view('livewire.laporan-mingguan.laporan-mingguan-create', [
            'jenisKapalList'  => JenisKapal::with(['company', 'galangan'])->get(),
            'kurvaSChartData' => $kurvaSChartData,
            'totalRencana'   => $totalRencana,
            'totalAktual'    => $totalAktual,
        ]);
    }
}
