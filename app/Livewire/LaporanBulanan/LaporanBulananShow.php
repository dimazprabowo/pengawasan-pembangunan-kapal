<?php

namespace App\Livewire\LaporanBulanan;

use App\Jobs\GenerateLaporanBulananJob;
use App\Jobs\ProcessLaporanBulananPengesahan;
use App\Jobs\ReleaseLaporanBulananPdfJob;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use App\Services\FilePathService;
use App\Services\FileStorageService;
use App\Services\KurvaSService;
use App\Services\LaporanBulananService;
use App\Services\LaporanDocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Detail Laporan Bulanan'])]
class LaporanBulananShow extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithFileUploads;

    public LaporanBulanan $laporan;

    public DataKapal $dataKapal;

    public bool $showRegenerateConfirm = false;

    public bool $showDeleteDocConfirm = false;

    public bool $showReleaseConfirm = false;

    public ?string $lastDocStatus = null;

    public ?string $lastPdfStatus = null;

    public $pengesahanFile = null;

    public bool $showDeletePengesahanConfirm = false;

    public function mount(DataKapal $dataKapal, LaporanBulanan $laporanBulanan): void
    {
        $this->authorize('view', $laporanBulanan);
        $this->authorizeDataKapalAccess($dataKapal);
        abort_unless($laporanBulanan->data_kapal_id === $dataKapal->id, 404);

        $this->dataKapal = $dataKapal;
        $this->laporan = $laporanBulanan->load([
            'user',
            'dataKapal.owner',
            'dataKapal.galangan',
            'laporanExternal',
        ]);

        $this->lastDocStatus = $this->laporan->doc_status;
        $this->lastPdfStatus = $this->laporan->pdf_status;
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
                app(FilePathService::class)->delete($this->laporan->doc_path);
            }

            $this->laporan->update([
                'doc_status' => 'pending',
                'doc_error' => null,
            ]);

            GenerateLaporanBulananJob::dispatch($this->laporan);

            $this->laporan->refresh();

            $this->notifySuccess('Proses generate dokumen Word dimulai. Halaman akan otomatis diperbarui.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal memulai proses generate. Silakan coba lagi.');
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
                $this->notifyError('Generate dokumen gagal. Silakan coba lagi.');
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
            $this->notifyError('Gagal menghapus dokumen. Silakan coba lagi.');
        }
    }

    public function downloadWord()
    {
        $this->authorize('downloadWord', $this->laporan);

        if (! $this->laporan->hasDoc() || ! $this->laporan->isDocCompleted()) {
            $this->notifyError('Dokumen tidak tersedia.');

            return;
        }

        $files = app(FilePathService::class);
        if (! $files->exists($this->laporan->doc_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return $files->download($this->laporan->doc_path, $this->laporan->doc_name);
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

            ReleaseLaporanBulananPdfJob::dispatch($this->laporan);

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

    public function savePengesahan(): void
    {
        $this->authorize('update', $this->laporan);

        $this->validate([
            'pengesahanFile' => ['required', 'file', 'mimes:pdf', 'max:'.get_max_upload_size()],
        ], [], ['pengesahanFile' => 'lembar pengesahan']);

        if ($this->laporan->isPengesahanProcessing()) {
            $this->notifyWarning('Lembar pengesahan sedang diproses. Tunggu hingga selesai.');

            return;
        }

        try {
            $tempPath = app(FileStorageService::class)->storeTemp($this->pengesahanFile, 'pengesahan');
            $oldPath = $this->laporan->pengesahan_path;
            $this->laporan->update([
                'pengesahan_name' => $this->pengesahanFile->getClientOriginalName(),
                'pengesahan_status' => 'pending',
                'pengesahan_error' => null,
            ]);
            ProcessLaporanBulananPengesahan::dispatch($this->laporan, $tempPath, $oldPath);
            $this->pengesahanFile = null;
            $this->notifySuccess('Lembar pengesahan diunggah. File sedang diproses.');
        } catch (\Throwable $e) {
            Log::error('LaporanBulananShow: gagal mengunggah lembar pengesahan', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDeletePengesahan(): void
    {
        $this->authorize('update', $this->laporan);
        $this->showDeletePengesahanConfirm = true;
    }

    public function cancelDeletePengesahan(): void
    {
        $this->showDeletePengesahanConfirm = false;
    }

    public function deletePengesahan(): void
    {
        $this->authorize('update', $this->laporan);
        $this->showDeletePengesahanConfirm = false;

        app(FileStorageService::class)->deleteFiles([$this->laporan->pengesahan_path]);
        $this->laporan->update([
            'pengesahan_path' => null,
            'pengesahan_name' => null,
            'pengesahan_size' => null,
            'pengesahan_status' => null,
            'pengesahan_processed_at' => null,
            'pengesahan_error' => null,
        ]);

        // PDF rilis yang sudah ada dirilis ulang agar kembali memakai
        // halaman pengesahan dari dokumen Word.
        if ($this->laporan->isPdfCompleted()) {
            ReleaseLaporanBulananPdfJob::dispatch($this->laporan);
        }

        $this->notifySuccess('Lembar pengesahan dihapus.');
    }

    public function downloadPengesahan()
    {
        $this->authorize('view', $this->laporan);

        $files = app(FilePathService::class);
        if (! $this->laporan->pengesahan_path || ! $files->exists($this->laporan->pengesahan_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return $files->download($this->laporan->pengesahan_path, $this->laporan->pengesahan_name);
    }

    public function refreshExternalFileStatus(): void
    {
        $this->laporan->load('laporanExternal');
    }

    public function hasProcessingExternalFiles(): bool
    {
        return $this->laporan->laporanExternal->contains(function ($external) {
            return $external->isFileProcessing();
        });
    }

    public function downloadExternalFile(int $externalId)
    {
        $this->authorize('view', $this->laporan);

        $external = $this->laporan->laporanExternal->find($externalId);

        if (! $external || ! $external->hasFile() || ! $external->isFileCompleted()) {
            $this->notifyError('File tidak tersedia.');

            return;
        }

        if (! Storage::disk()->exists($external->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        $originalFileName = $external->file_name;
        $title = $external->judul ?? '';
        $downloadFileName = ! empty($title)
            ? $title.'.'.pathinfo($originalFileName, PATHINFO_EXTENSION)
            : $originalFileName;

        return Storage::disk()->download($external->file_path, $downloadFileName);
    }

    public function render(LaporanBulananService $service, KurvaSService $kurvaSService)
    {
        $chartData = [];
        $totalRencana = null;
        $totalAktual = null;
        $progressHistory = [];
        $workGroupsForHistory = [];

        if ($this->laporan->dataKapal) {
            $chartData = $kurvaSService->getMonthlyChartData($this->laporan->dataKapal);
            $progressHistory = $kurvaSService->getMonthlyProgressHistory($this->laporan->dataKapal);

            $workGroupsForHistory = KurvaSWorkGroup::where('data_kapal_id', $this->laporan->data_kapal_id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($wg) => [
                    'work_group_id' => $wg->id,
                    'nama' => $wg->nama,
                    'bobot' => $wg->bobot,
                ])
                ->toArray();

            if (! empty($progressHistory) && ! empty($workGroupsForHistory)) {
                $totals = $kurvaSService->calculateTotalsFromHistory($progressHistory, $workGroupsForHistory);
                $totalRencana = $totals['total_rencana'];
                $totalAktual = $totals['total_aktual'];
            }
        }

        return view('livewire.laporan-bulanan.laporan-bulanan-show', [
            'laporanMingguanBulan' => $service->getMingguanBulan($this->laporan),
            'kurvaSChartData' => $chartData,
            'dataKapalNama' => $this->laporan->dataKapal?->nama,
            'totalRencana' => $totalRencana,
            'totalAktual' => $totalAktual,
            'progressHistory' => $progressHistory,
            'workGroupsForHistory' => $workGroupsForHistory,
        ]);
    }
}
