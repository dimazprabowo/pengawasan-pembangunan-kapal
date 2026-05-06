<?php

namespace App\Livewire\LaporanMingguan;

use App\Jobs\GenerateLaporanMingguanJob;
use App\Livewire\Traits\HasNotification;
use App\Models\LaporanLampiran;
use App\Models\LaporanMingguan;
use App\Services\LaporanMingguanService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Mingguan'])]
class LaporanMingguanShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public LaporanMingguan $laporan;

    // Lampiran Harian Modal
    public bool $showLampiranModal = false;
    public array $lampiranHarianList = [];
    public bool $loadingLampiran = false;
    public bool $showPreviewModal = false;
    public ?int $previewLampiranId = null;

    // Available Laporan Harian
    public \Illuminate\Database\Eloquent\Collection $availableLaporanHarian;

    // Word Document Status
    public bool $loadingDoc = false;
    public bool $showRegenerateConfirm = false;
    public bool $showDeleteDocConfirm = false;

    public function mount(LaporanMingguan $laporanMingguan): void
    {
        $this->authorize('view', $laporanMingguan);
        $this->loadLaporan($laporanMingguan);
    }

    private function loadLaporan(LaporanMingguan $laporanMingguan): void
    {
        $this->laporan = $laporanMingguan->load([
            'user',
            'jenisKapal.company',
            'jenisKapal.galangan',
            'laporanHarian' => function ($query) {
                $query->orderBy('tanggal_laporan', 'asc');
            },
            'laporanHarian.lampiran',
            'laporanHarian.personel',
            'laporanHarian.peralatan',
            'laporanHarian.consumable',
            'laporanHarian.aktivitas',
            'laporanHarian.cuacaPagi',
            'laporanHarian.kelembabanPagi',
            'laporanHarian.cuacaSiang',
            'laporanHarian.kelembabanSiang',
            'laporanHarian.cuacaSore',
            'laporanHarian.kelembabanSore',
            'lampiran',
        ]);

        // Force reload laporanHarian to ensure correct count
        $this->laporan->load('laporanHarian');

        // Load lampiran list if laporan harian exists
        if ($this->laporan->laporanHarian->count() > 0) {
            $this->loadLampiranHarian();
            $this->loadAvailableLaporanHarian();
        }
    }

    private function loadAvailableLaporanHarian(): void
    {
        $laporanHarianIds = $this->laporan->laporanHarian->pluck('id')->toArray();

        $this->availableLaporanHarian = \App\Models\LaporanHarian::with(['user', 'jenisKapal'])
            ->byUser($this->laporan->user_id)
            ->whereIn('id', $laporanHarianIds)
            ->orderByDesc('tanggal_laporan')
            ->get();
    }

    public function openLampiranModal(): void
    {
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

        $laporanHarianIds = $this->laporan->laporanHarian->pluck('id')->toArray();

        $query = LaporanLampiran::with(['laporanHarian'])
            ->whereHas('laporanHarian', function ($q) use ($laporanHarianIds) {
                $q->whereIn('id', $laporanHarianIds);
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
                'is_selected' => $this->laporan->lampiran->contains('id', $item->id),
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

        $fileContent = Storage::disk('local')->get($lampiran->file_path);
        $base64 = base64_encode($fileContent);
        $mime = $mimeTypes[$extension];

        return "data:{$mime};base64,{$base64}";
    }

    public function previewLampiranHarian(int $lampiranId): void
    {
        $this->authorize('lampiranPreview', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (!$lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');
            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian or be linked to this laporan mingguan
        $belongsToUser = $lampiran->laporanHarian && $lampiran->laporanHarian->user_id === $this->laporan->user_id;
        $isLinkedToReport = $this->laporan->lampiran->contains('id', $lampiran->id);

        if (!$belongsToUser && !$isLinkedToReport) {
            $this->notifyError('Anda tidak memiliki akses ke lampiran ini.');
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
        $this->authorize('lampiranDownload', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (!$lampiran || !$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian or be linked to this laporan mingguan
        $belongsToUser = $lampiran->laporanHarian && $lampiran->laporanHarian->user_id === $this->laporan->user_id;
        $isLinkedToReport = $this->laporan->lampiran->contains('id', $lampiran->id);

        if (!$belongsToUser && !$isLinkedToReport) {
            $this->notifyError('Anda tidak memiliki akses ke lampiran ini.');
            return;
        }

        return response()->download(Storage::disk('local')->path($lampiran->file_path), $lampiran->file_name);
    }

    public function previewLampiran(int $lampiranId)
    {
        $this->authorize('lampiranPreview', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (!$lampiran || !$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian or be linked to this laporan mingguan
        $belongsToUser = $lampiran->laporanHarian && $lampiran->laporanHarian->user_id === $this->laporan->user_id;
        $isLinkedToReport = $this->laporan->lampiran->contains('id', $lampiran->id);

        if (!$belongsToUser && !$isLinkedToReport) {
            $this->notifyError('Anda tidak memiliki akses ke lampiran ini.');
            return;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

    public function confirmRegenerate(): void
    {
        $this->authorize('generateWord', $this->laporan);

        if ($this->laporan->isDocCompleted()) {
            $this->showRegenerateConfirm = true;
            return;
        }

        $this->generateWord();
    }

    public function cancelRegenerate(): void
    {
        $this->showRegenerateConfirm = false;
    }

    public function generateWord(): void
    {
        $this->showRegenerateConfirm = false;
        $this->authorize('generateWord', $this->laporan);

        if ($this->laporan->isDocProcessing()) {
            $this->notifyWarning('Dokumen sedang dalam proses generate. Mohon tunggu.');
            return;
        }

        try {
            // Hapus file lama jika ada (untuk generate ulang)
            if ($this->laporan->doc_path) {
                $oldPath = storage_path('app/' . $this->laporan->doc_path);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $this->laporan->update([
                'doc_status' => 'pending',
                'doc_error' => null,
            ]);

            GenerateLaporanMingguanJob::dispatch($this->laporan);

            $this->laporan->refresh();

            $this->notifySuccess('Proses generate dokumen Word dimulai. Halaman akan otomatis diperbarui.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal memulai proses generate: ' . $e->getMessage());
        }
    }

    public function refreshDocStatus(): void
    {
        $this->laporan->refresh();

        if ($this->laporan->isDocCompleted()) {
            $this->notifySuccess('Dokumen Word berhasil digenerate! Silakan download.');
        } elseif ($this->laporan->isDocFailed()) {
            $this->notifyError('Generate dokumen gagal: ' . ($this->laporan->doc_error ?? 'Unknown error'));
        }
    }

    public function confirmDeleteDoc(): void
    {
        $this->authorize('generateWord', $this->laporan);
        $this->showDeleteDocConfirm = true;
    }

    public function cancelDeleteDoc(): void
    {
        $this->showDeleteDocConfirm = false;
    }

    public function deleteDoc(): void
    {
        $this->showDeleteDocConfirm = false;
        $this->authorize('generateWord', $this->laporan);

        try {
            if ($this->laporan->doc_path) {
                $path = storage_path('app/' . $this->laporan->doc_path);
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $this->laporan->update([
                'doc_path' => null,
                'doc_name' => null,
                'doc_status' => null,
                'doc_generated_at' => null,
                'doc_error' => null,
            ]);

            $this->laporan->refresh();
            $this->notifySuccess('Dokumen Word berhasil dihapus.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal menghapus dokumen: ' . $e->getMessage());
        }
    }

    public function downloadWord()
    {
        $this->authorize('downloadWord', $this->laporan);

        if (!$this->laporan->hasDoc() || !$this->laporan->isDocCompleted()) {
            $this->notifyError('Dokumen tidak tersedia.');
            return;
        }

        $filePath = storage_path('app/' . $this->laporan->doc_path);
        if (!file_exists($filePath)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        return response()->download($filePath, $this->laporan->doc_name);
    }

    public function removeDoc(LaporanMingguanService $service): void
    {
        $this->authorize('update', $this->laporan);

        $service->removeDoc($this->laporan);

        $this->notifySuccess('Dokumen berhasil dihapus.');
    }

    public function render(QueueStatusService $queueStatusService)
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-show', [
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
        ]);
    }
}
