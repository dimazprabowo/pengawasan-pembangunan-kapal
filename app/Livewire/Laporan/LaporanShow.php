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

    public bool $isGenerating = false;

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

    public function generateWord(): void
    {
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
            $this->laporan->update([
                'doc_status' => 'pending',
                'doc_error'  => null,
            ]);

            GenerateLaporanHarianJob::dispatch($this->laporan);

            $this->isGenerating = true;
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
            $this->isGenerating = false;
            $this->notifySuccess('Dokumen Word berhasil digenerate! Silakan download.');
        } elseif ($this->laporan->isDocFailed()) {
            $this->isGenerating = false;
            $this->notifyError('Generate dokumen gagal: ' . ($this->laporan->doc_error ?? 'Unknown error'));
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Lampiran Preview Actions
    // ─────────────────────────────────────────────────────────────────

    public function openPreview(): void
    {
        if (!$this->laporan->file_path) {
            $this->notifyWarning('Laporan ini tidak memiliki file lampiran.');
            return;
        }

        $this->showPreviewModal = true;
    }

    public function closePreview(): void
    {
        $this->showPreviewModal = false;
    }

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

    public function getFileExtensionProperty(): ?string
    {
        if (!$this->laporan->file_name) {
            return null;
        }

        return strtolower(pathinfo($this->laporan->file_name, PATHINFO_EXTENSION));
    }

    public function getIsPdfProperty(): bool
    {
        return $this->fileExtension === 'pdf';
    }

    public function render(QueueStatusService $queueStatusService)
    {
        return view('livewire.laporan.laporan-show', [
            'tipeEnum'    => $this->laporan->tipe,
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
        ]);
    }
}
