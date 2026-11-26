<?php

namespace App\Livewire\LaporanHarian;

use App\Jobs\GenerateLaporanHarianJob;
use App\Jobs\ReleaseLaporanHarianPdfJob;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNcFotoPreview;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Services\FilePathService;
use App\Services\KontrakPeriodeService;
use App\Services\LaporanDocumentService;
use App\Services\LaporanHarianService;
use App\Services\LaporanNcService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Harian'])]
class LaporanHarianShow extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNcFotoPreview, HasNotification;

    public LaporanHarian $laporan;

    public DataKapal $dataKapal;

    public bool $showPreviewModal = false;

    public ?int $previewLampiranId = null;

    public bool $showRegenerateConfirm = false;

    public bool $showDeleteDocConfirm = false;

    public bool $showReleaseConfirm = false;

    public ?string $lastDocStatus = null;

    public ?string $lastPdfStatus = null;

    public function mount(DataKapal $dataKapal, LaporanHarian $laporanHarian): void
    {
        $this->authorize('view', $laporanHarian);
        $this->authorizeDataKapalAccess($dataKapal);
        abort_unless($laporanHarian->data_kapal_id === $dataKapal->id, 404);

        $this->dataKapal = $dataKapal;
        $this->loadLaporan($laporanHarian);

        $this->lastDocStatus = $this->laporan->doc_status;
        $this->lastPdfStatus = $this->laporan->pdf_status;
    }

    private function loadLaporan(LaporanHarian $laporanHarian): void
    {
        $this->laporan = $laporanHarian->load([
            'user',
            'updatedBy',
            'dataKapal.owner',
            'dataKapal.galangan',
            'cuacaPagi',
            'kelembabanPagi',
            'cuacaSiang',
            'kelembabanSiang',
            'cuacaSore',
            'kelembabanSore',
            'lampiran',
            'laporanExternal',
            'personel',
            'peralatan',
            'consumable',
            'aktivitas.workGroup',
            'aktivitas.lampiran',
            'ncs.foto',
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
                app(FilePathService::class)->delete($this->laporan->doc_path);
            }

            $this->laporan->update([
                'doc_status' => 'pending',
                'doc_error' => null,
            ]);

            GenerateLaporanHarianJob::dispatch($this->laporan);

            $this->laporan->refresh();

            $this->notifySuccess('Proses generate dokumen Word dimulai. Halaman akan otomatis diperbarui.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal memulai proses generate: '.$e->getMessage());
        }
    }

    public function refreshDocStatus(): void
    {
        $this->laporan->refresh();

        // Notifikasi hanya saat status BERUBAH — polling terus berjalan
        // selama doc/pdf completed sehingga tanpa guard toast berulang tiap 4 detik.
        if ($this->laporan->doc_status !== $this->lastDocStatus) {
            $this->lastDocStatus = $this->laporan->doc_status;

            if ($this->laporan->isDocCompleted()) {
                $this->notifySuccess('Dokumen Word berhasil digenerate! Silakan download.');
            } elseif ($this->laporan->isDocFailed()) {
                $this->notifyError('Generate dokumen gagal: '.($this->laporan->doc_error ?? 'Unknown error'));
            }
        }

        if ($this->laporan->pdf_status !== $this->lastPdfStatus) {
            $this->lastPdfStatus = $this->laporan->pdf_status;

            if ($this->laporan->isPdfCompleted()) {
                $this->notifySuccess('PDF rilis berhasil digenerate! Silakan download.');
            } elseif ($this->laporan->isPdfFailed()) {
                $this->notifyError('Generate PDF rilis gagal. Silakan coba lagi.');
            }
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
            app(LaporanDocumentService::class)->clearDoc($this->laporan);

            $this->laporan->refresh();
            $this->notifySuccess('Dokumen Word berhasil dihapus.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal menghapus dokumen: '.$e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Lampiran Preview Actions
    // ─────────────────────────────────────────────────────────────────

    public function openLampiranPreview(int $lampiranId): void
    {
        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (! $lampiran || ! $lampiran->hasFile() || ! $lampiran->isFileCompleted()) {
            $this->notifyWarning('File lampiran tidak tersedia.');

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
        if (! $this->previewLampiranId) {
            return null;
        }

        return $this->laporan->lampiran->find($this->previewLampiranId);
    }

    public function render(QueueStatusService $queueStatusService, LaporanHarianService $service, KontrakPeriodeService $periodeService)
    {
        // Rehidrasi Livewire tidak membawa relasi — pastikan files NC tetap eager-loaded
        $this->laporan->loadMissing('ncs.foto');

        return view('livewire.laporan-harian.laporan-harian-show', [
            'hariKe' => $periodeService->getHariKeFromDate($this->dataKapal, $this->laporan->tanggal_laporan),
            'mingguKe' => $periodeService->getMingguKeFromDate($this->dataKapal, $this->laporan->tanggal_laporan),
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            // Uraian aktivitas per kategori (hanya kategori yang berisi) — dipakai section Aktivitas & Lampiran
            'aktivitasGroups' => $service->aktivitasPerKategori($this->laporan)
                ->map(fn ($group) => [
                    'nama' => $group['nama'],
                    'rows' => $group['aktivitas']->map(fn ($a) => $service->aktivitasRow($a))->all(),
                ])->all(),
            'lampiranByAktivitas' => $this->laporan->lampiran->keyBy('laporan_aktivitas_id'),
            // NC readonly — hanya kategori berisi (pola aktivitasGroups)
            'ncGroups' => array_values(array_filter(
                $service->groupRowsByKategori(
                    app(LaporanNcService::class)->ncRowsFrom($this->laporan),
                    $service->kategoriOptions($this->laporan->data_kapal_id)
                ),
                fn ($g) => count($g['rows'] ?? []) > 0
            )),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // File Download & Preview
    // ─────────────────────────────────────────────────────────────────

    public function downloadFile()
    {
        $this->authorize('download', $this->laporan);

        if (! $this->laporan->file_path || ! Storage::disk()->exists($this->laporan->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return Storage::disk()->download($this->laporan->file_path, $this->laporan->file_name);
    }

    public function previewFile()
    {
        $this->authorize('view', $this->laporan);

        if (! $this->laporan->file_path || ! Storage::disk()->exists($this->laporan->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        $extension = strtolower(pathinfo($this->laporan->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response(Storage::disk()->get($this->laporan->file_path))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="'.$this->laporan->file_name.'"');
    }

    public function downloadWord()
    {
        $this->authorize('generateWord', $this->laporan);

        if (! $this->laporan->isDocCompleted() || ! $this->laporan->doc_path) {
            $this->notifyError('Dokumen belum tersedia.');

            return;
        }

        $files = app(FilePathService::class);

        if (! $files->exists($this->laporan->doc_path)) {
            $this->notifyError('File dokumen tidak ditemukan di server.');

            return;
        }

        $downloadName = $this->laporan->doc_name ?? 'laporan-harian.docx';

        return $files->download($this->laporan->doc_path, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function confirmReleasePdf(): void
    {
        $this->authorize('generateWord', $this->laporan);

        if (! $this->laporan->hasDoc() || ! $this->laporan->isDocCompleted()) {
            $this->notifyWarning('Generate dokumen Word terlebih dahulu sebelum release PDF.');

            return;
        }

        $this->showReleaseConfirm = true;
    }

    public function cancelReleasePdf(): void
    {
        $this->showReleaseConfirm = false;
    }

    public function releasePdf(): void
    {
        $this->showReleaseConfirm = false;
        $this->authorize('generateWord', $this->laporan);

        if (! $this->laporan->hasDoc() || ! $this->laporan->isDocCompleted()) {
            $this->notifyWarning('Generate dokumen Word terlebih dahulu sebelum release PDF.');

            return;
        }

        if ($this->laporan->isPdfProcessing()) {
            $this->notifyWarning('PDF rilis sedang dalam proses generate. Mohon tunggu.');

            return;
        }

        try {
            $this->laporan->update([
                'pdf_status' => 'pending',
                'pdf_error' => null,
            ]);

            ReleaseLaporanHarianPdfJob::dispatch($this->laporan);

            $this->laporan->refresh();

            $this->notifySuccess('Proses release PDF dimulai. Halaman akan otomatis diperbarui.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal memulai proses release PDF. Silakan coba lagi.');
        }
    }

    public function downloadPdf()
    {
        $this->authorize('downloadWord', $this->laporan);

        if (! $this->laporan->hasPdf() || ! $this->laporan->isPdfCompleted()) {
            $this->notifyError('PDF rilis tidak tersedia. Klik Release terlebih dahulu.');

            return;
        }

        $files = app(FilePathService::class);
        if (! $files->exists($this->laporan->pdf_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return $files->download($this->laporan->pdf_path, $this->laporan->pdf_name);
    }

    public function downloadLampiran(int $lampiranId)
    {
        $this->authorize('lampiranDownload', LaporanHarian::class);

        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (! $lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
            $this->notifyError('Lampiran tidak ditemukan.');

            return;
        }

        if (! $lampiran->file_path || ! Storage::disk()->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return Storage::disk()->download($lampiran->file_path, $lampiran->file_name);
    }

    public function getPreviewLampiranImageUrlProperty(): ?string
    {
        return $this->previewLampiran?->isPreviewable()
            ? route('laporan-lampiran.file', $this->previewLampiran)
            : null;
    }

    /**
     * URL foto lampiran selesai (id => route file) — dimuat lazy dari Alpine.
     * File di-serve via route, bukan base64, agar tidak membengkakkan
     * response/payload Livewire dan bisa di-cache browser.
     *
     * @return array<int, string>
     */
    public function lampiranImageUrls(): array
    {
        if (! Gate::allows('lampiranPreview', LaporanHarian::class)) {
            return [];
        }

        return $this->laporan->lampiran
            ->filter(fn ($l) => $l->hasFile() && $l->isFileCompleted() && $l->isImage())
            ->mapWithKeys(fn ($l) => [$l->id => route('laporan-lampiran.file', $l)])
            ->all();
    }

    public function downloadExternalFile(int $id)
    {
        $this->authorize('view', $this->laporan);
        $this->authorize('laporan_lampiran_download');
        $this->authorizeDataKapalAccess($this->dataKapal);
        $external = $this->laporan->laporanExternal()->findOrFail($id);
        abort_unless($external->isFileCompleted() && $external->file_path, 404);

        return Storage::disk('local')->download($external->file_path, $external->file_name);
    }

    public function refreshLampiranStatus(): void
    {
        $this->authorize('view', $this->laporan);
        $this->laporan->load(['lampiran', 'ncs.foto', 'laporanExternal']);
        $this->dispatch('lh-reload-thumbs');
    }

    public function hasProcessingLampiran(): bool
    {
        return $this->laporan->laporanExternal->contains(fn ($row) => $row->isFileProcessing()) || $this->laporan->lampiran->contains(function ($lampiran) {
            return $lampiran->isFileProcessing();
        });
    }
}
