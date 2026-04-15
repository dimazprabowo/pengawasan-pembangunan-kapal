<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\LaporanMingguan;
use App\Services\LaporanMingguanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Edit Laporan Mingguan'])]
class LaporanMingguanEdit extends Component
{
    use AuthorizesRequests, HasNotification;

    public LaporanMingguan $laporan;

    public ?int $jenis_kapal_id = null;
    public string $judul = '';
    public string $tanggal_laporan = '';
    public string $periode_mulai = '';
    public string $periode_selesai = '';
    public string $ringkasan = '';
    public array $laporan_harian_ids = [];
    public array $availableLaporanHarian = [];
    public array $lampiran_ids = [];
    
    // Lampiran Harian Modal
    public bool $showLampiranModal = false;
    public array $lampiranHarianList = [];
    public bool $loadingLampiran = false;
    public bool $showPreviewModal = false;
    public ?int $previewLampiranId = null;

    public function mount(LaporanMingguan $laporanMingguan): void
    {
        $this->authorize('update', $laporanMingguan);
        $this->laporan = $laporanMingguan;
        $this->jenis_kapal_id = $laporanMingguan->jenis_kapal_id;
        $this->judul = $laporanMingguan->judul;
        $this->tanggal_laporan = $laporanMingguan->tanggal_laporan?->format('Y-m-d') ?? '';
        $this->periode_mulai = $laporanMingguan->periode_mulai?->format('Y-m-d') ?? '';
        $this->periode_selesai = $laporanMingguan->periode_selesai?->format('Y-m-d') ?? '';
        $this->ringkasan = $laporanMingguan->ringkasan ?? '';
        $this->laporan_harian_ids = $laporanMingguan->laporanHarian->pluck('id')->toArray();
        $this->lampiran_ids = $laporanMingguan->lampiran->pluck('id')->toArray();
        $this->loadAvailableLaporanHarian();
        
        // Load lampiran list if periode exists
        if ($this->periode_mulai && $this->periode_selesai) {
            $this->loadLampiranHarian();
        }
    }

    public function updatedJenisKapalId(): void
    {
        $this->laporan_harian_ids = [];
        
        // Load lampiran list based on new jenis kapal if period exists
        if ($this->periode_mulai && $this->periode_selesai) {
            $this->loadLampiranHarian();
            
            // Filter lampiran_ids to only keep lampiran that are still available with new jenis kapal
            $availableLampiranIds = collect($this->lampiranHarianList)->pluck('id')->toArray();
            $this->lampiran_ids = array_intersect($this->lampiran_ids, $availableLampiranIds);
        } else {
            $this->lampiran_ids = [];
        }
        
        $this->loadAvailableLaporanHarian();
    }

    public function updatedPeriodeMulai(): void
    {
        // Load lampiran list first to get available lampiran in new period
        if ($this->periode_mulai && $this->periode_selesai) {
            $this->loadLampiranHarian();
            
            // Filter lampiran_ids to only keep lampiran that are still available in new period
            $availableLampiranIds = collect($this->lampiranHarianList)->pluck('id')->toArray();
            $this->lampiran_ids = array_intersect($this->lampiran_ids, $availableLampiranIds);
        } else {
            $this->lampiran_ids = [];
        }
        
        $this->loadAvailableLaporanHarian();
        $this->autoSelectLaporanHarian();
    }

    public function updatedPeriodeSelesai(): void
    {
        // Load lampiran list first to get available lampiran in new period
        if ($this->periode_mulai && $this->periode_selesai) {
            $this->loadLampiranHarian();
            
            // Filter lampiran_ids to only keep lampiran that are still available in new period
            $availableLampiranIds = collect($this->lampiranHarianList)->pluck('id')->toArray();
            $this->lampiran_ids = array_intersect($this->lampiran_ids, $availableLampiranIds);
        } else {
            $this->lampiran_ids = [];
        }
        
        $this->loadAvailableLaporanHarian();
        $this->autoSelectLaporanHarian();
    }

    private function loadAvailableLaporanHarian(): void
    {
        $query = LaporanHarian::with(['user', 'jenisKapal'])
            ->byUser(auth()->id())
            ->orderByDesc('tanggal_laporan');

        if ($this->jenis_kapal_id) {
            $query->where('jenis_kapal_id', $this->jenis_kapal_id);
        }

        // Filter by period if both dates are set
        if ($this->periode_mulai && $this->periode_selesai) {
            $query->whereBetween('tanggal_laporan', [$this->periode_mulai, $this->periode_selesai]);
        }

        $this->availableLaporanHarian = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'tanggal' => $item->tanggal_laporan->format('d M Y'),
            ];
        })->toArray();
    }

    private function autoSelectLaporanHarian(): void
    {
        // Auto-select all available laporan harian
        $this->laporan_harian_ids = collect($this->availableLaporanHarian)->pluck('id')->toArray();
    }

    protected function rules(): array
    {
        return [
            'jenis_kapal_id' => 'nullable|exists:jenis_kapal,id',
            'judul' => 'required|string|max:255',
            'tanggal_laporan' => 'required|date',
            'periode_mulai' => 'nullable|date|before_or_equal:periode_selesai',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'ringkasan' => 'nullable|string',
            'laporan_harian_ids' => 'required|array|min:1',
            'laporan_harian_ids.*' => 'exists:laporan_harian,id',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'jenis_kapal_id' => 'jenis kapal',
            'judul' => 'judul laporan',
            'tanggal_laporan' => 'tanggal laporan',
            'periode_mulai' => 'periode mulai',
            'periode_selesai' => 'periode selesai',
            'ringkasan' => 'ringkasan',
            'laporan_harian_ids' => 'laporan harian',
        ];
    }

    public function openLampiranModal(): void
    {
        if (!$this->periode_mulai || !$this->periode_selesai) {
            $this->notifyWarning('Silakan pilih periode terlebih dahulu.');
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
        $this->loadingLampiran = true;

        $query = LaporanLampiran::with(['laporanHarian'])
            ->whereHas('laporanHarian', function ($q) {
                $q->byUser(auth()->id())
                    ->whereBetween('tanggal_laporan', [$this->periode_mulai, $this->periode_selesai]);
                
                if ($this->jenis_kapal_id) {
                    $q->where('jenis_kapal_id', $this->jenis_kapal_id);
                }
            })
            ->where('file_status', 'completed')
            ->orderBy('created_at', 'desc');

        $lampiran = $query->get();
        
        $this->lampiranHarianList = $lampiran->map(function ($item) {
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

    public function toggleLampiran(int $lampiranId): void
    {
        if (in_array($lampiranId, $this->lampiran_ids)) {
            $this->lampiran_ids = array_values(array_diff($this->lampiran_ids, [$lampiranId]));
        } else {
            $this->lampiran_ids[] = $lampiranId;
        }
        
        // Reload list to update is_selected flag
        $this->loadLampiranHarian();
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
        $lampiran = LaporanLampiran::find($lampiranId);

        if (!$lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');
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
        $lampiran = LaporanLampiran::find($lampiranId);

        if (!$lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');
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
        $this->authorize('update', $this->laporan);
        
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'jenis_kapal_id' => $this->jenis_kapal_id,
                'judul' => $this->judul,
                'tanggal_laporan' => $this->tanggal_laporan,
                'periode_mulai' => $this->periode_mulai ?: null,
                'periode_selesai' => $this->periode_selesai ?: null,
                'ringkasan' => $this->ringkasan ?: null,
                'laporan_harian_ids' => $this->laporan_harian_ids,
                'lampiran_ids' => $this->lampiran_ids,
            ];

            $service->update($this->laporan, $data);

            session()->flash('notify', [
                'type' => 'success',
                'message' => 'Laporan mingguan berhasil diupdate!',
            ]);

            $this->redirect(route('laporan-mingguan.index'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengupdate laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-edit', [
            'jenisKapalList' => JenisKapal::with(['company', 'galangan'])->get(),
        ]);
    }
}
