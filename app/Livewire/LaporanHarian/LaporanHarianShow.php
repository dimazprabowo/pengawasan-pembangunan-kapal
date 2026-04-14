<?php

namespace App\Livewire\LaporanHarian;

use App\Jobs\GenerateLaporanHarianJob;
use App\Livewire\Traits\HasNotification;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Harian'])]
class LaporanHarianShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public LaporanHarian $laporan;

    public bool $showPreviewModal   = false;
    public ?int  $previewLampiranId = null;

    public bool $showRegenerateConfirm = false;
    public bool $showDeleteDocConfirm = false;

    public function mount(LaporanHarian $laporanHarian): void
    {
        $this->authorize('view', $laporanHarian);
        $this->loadLaporan($laporanHarian);
    }

    private function loadLaporan(LaporanHarian $laporanHarian): void
    {
        $this->laporan = $laporanHarian->load([
            'user',
            'jenisKapal.company',
            'jenisKapal.galangan',
            'cuacaPagi',
            'kelembabanPagi',
            'cuacaSiang',
            'kelembabanSiang',
            'cuacaSore',
            'kelembabanSore',
            'lampiran',
            'personel',
            'peralatan',
            'consumable',
            'aktivitas',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Word Document Actions
    // ─────────────────────────────────────────────────────────────────

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
                'doc_error'  => null,
            ]);

            GenerateLaporanHarianJob::dispatch($this->laporan);

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
                'doc_path'         => null,
                'doc_name'         => null,
                'doc_status'       => null,
                'doc_generated_at' => null,
                'doc_error'        => null,
            ]);

            $this->laporan->refresh();
            $this->notifySuccess('Dokumen Word berhasil dihapus.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal menghapus dokumen: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Lampiran Preview Actions
    // ─────────────────────────────────────────────────────────────────

    public function openLampiranPreview(int $lampiranId): void
    {
        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (!$lampiran || !$lampiran->hasFile() || !$lampiran->isFileCompleted()) {
            $this->notifyWarning('File lampiran tidak tersedia.');
            return;
        }

        $this->previewLampiranId = $lampiranId;
        $this->showPreviewModal  = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal  = false;
        $this->previewLampiranId = null;
    }

    public function getPreviewLampiranProperty()
    {
        if (!$this->previewLampiranId) {
            return null;
        }

        return $this->laporan->lampiran->find($this->previewLampiranId);
    }

    public function render(QueueStatusService $queueStatusService)
    {
        return view('livewire.laporan-harian.laporan-harian-show', [
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // File Download & Preview
    // ─────────────────────────────────────────────────────────────────

    public function downloadFile()
    {
        $this->authorize('download', $this->laporan);

        if (!$this->laporan->file_path || !Storage::disk('local')->exists($this->laporan->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        return Storage::disk('local')->download($this->laporan->file_path, $this->laporan->file_name);
    }

    public function previewFile()
    {
        $this->authorize('view', $this->laporan);

        if (!$this->laporan->file_path || !Storage::disk('local')->exists($this->laporan->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        $extension = strtolower(pathinfo($this->laporan->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response(Storage::disk('local')->get($this->laporan->file_path))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $this->laporan->file_name . '"');
    }

    public function downloadWord()
    {
        $this->authorize('generateWord', $this->laporan);

        if (!$this->laporan->isDocCompleted() || !$this->laporan->doc_path) {
            $this->notifyError('Dokumen belum tersedia.');
            return;
        }

        $fullPath = storage_path('app/' . $this->laporan->doc_path);

        if (!file_exists($fullPath)) {
            $this->notifyError('File dokumen tidak ditemukan di server.');
            return;
        }

        $downloadName = $this->laporan->doc_name ?? 'laporan-harian.docx';

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function downloadLampiran(int $lampiranId)
    {
        $this->authorize('view', $this->laporan);

        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (!$lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
            $this->notifyError('Lampiran tidak ditemukan.');
            return;
        }

        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        return Storage::disk('local')->download($lampiran->file_path, $lampiran->file_name);
    }

    public function previewLampiran(int $lampiranId)
    {
        $this->authorize('view', $this->laporan);

        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (!$lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
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

    public function getLampiranImageDataUrl(int $lampiranId): ?string
    {
        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (!$lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
            return null;
        }

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

    public function getPreviewLampiranImageUrlProperty(): ?string
    {
        if (!$this->previewLampiran) {
            return null;
        }

        return $this->getLampiranImageDataUrl($this->previewLampiran->id);
    }
}
