<?php

namespace App\Livewire\Laporan;

use App\Jobs\GenerateLaporanHarianJob;
use App\Livewire\Traits\HasNotification;
use App\Models\Laporan;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan'])]
class LaporanShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public Laporan $laporan;

    public bool $showPreviewModal   = false;
    public ?int  $previewLampiranId = null;

    public bool $showRegenerateConfirm = false;
    public bool $showDeleteDocConfirm = false;

    public function mount(Laporan $laporan): void
    {
        $this->authorize('view', $laporan);
        $this->loadLaporan($laporan);
    }

    private function loadLaporan(Laporan $laporan): void
    {
        $this->laporan = $laporan->load([
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

        if ($this->laporan->tipe->value !== 'harian') {
            $this->notifyWarning('Generate dokumen Word hanya tersedia untuk Laporan Harian.');
            return;
        }

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
        return view('livewire.laporan.laporan-show', [
            'tipeEnum'    => $this->laporan->tipe,
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
        ]);
    }
}
