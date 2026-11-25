<?php

namespace App\Livewire\LaporanMingguan;

use App\Jobs\GenerateLaporanMingguanJob;
use App\Jobs\ReleaseLaporanMingguanPdfJob;
use App\Livewire\Traits\ChecksLaporanCompleteness;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\LaporanMingguan;
use App\Services\FilePathService;
use App\Services\KurvaSService;
use App\Services\LaporanDocumentService;
use App\Services\LaporanNcService;
use App\Services\QueueStatusService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Mingguan'])]
class LaporanMingguanShow extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification;
    use ChecksLaporanCompleteness;

    public LaporanMingguan $laporan;

    public DataKapal $dataKapal;

    // Lampiran Harian Modal
    public bool $showLampiranModal = false;

    public array $lampiranHarianList = [];

    public bool $loadingLampiran = false;

    public bool $showPreviewModal = false;

    public ?int $previewLampiranId = null;

    // Available Laporan Harian
    public ?Collection $availableLaporanHarian = null;

    // Word Document Status
    public bool $loadingDoc = false;

    public bool $showRegenerateConfirm = false;

    public bool $showDeleteDocConfirm = false;

    public bool $showReleaseConfirm = false;

    public ?string $lastDocStatus = null;

    public ?string $lastPdfStatus = null;

    // Progress per group (for compatibility with progress history component)
    public array $progressPerGroup = [];

    public function mount(DataKapal $dataKapal, LaporanMingguan $laporanMingguan): void
    {
        $this->authorize('view', $laporanMingguan);
        $this->authorizeDataKapalAccess($dataKapal);
        abort_unless($laporanMingguan->data_kapal_id === $dataKapal->id, 404);

        $this->dataKapal = $dataKapal;
        $this->loadLaporan($laporanMingguan);

        $this->lastDocStatus = $this->laporan->doc_status;
        $this->lastPdfStatus = $this->laporan->pdf_status;
    }

    private function loadLaporan(LaporanMingguan $laporanMingguan): void
    {
        $this->laporan = $laporanMingguan->load([
            'user',
            'dataKapal.owner',
            'dataKapal.galangan',
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
            'laporanExternal',
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

        $this->availableLaporanHarian = LaporanHarian::with(['user', 'dataKapal'])
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

        $query = LaporanLampiran::with(['laporanHarian', 'aktivitas'])
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
                'file_size_formatted' => number_format($item->file_size / 1024, 1).' KB',
                'keterangan' => $item->keterangan,
                'extension' => $extension,
                'is_image' => $isImage,
                'laporan_tanggal' => $item->laporanHarian->tanggal_laporan->format('d M Y'),
                'is_selected' => $this->laporan->lampiran->contains('id', $item->id),
            ];
        })->toArray();

        $this->loadingLampiran = false;
    }

    public function previewLampiranHarian(int $lampiranId): void
    {
        $this->authorize('lampiranPreview', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (! $lampiran) {
            $this->notifyError('Lampiran tidak ditemukan.');

            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian or be linked to this laporan mingguan
        $belongsToUser = $lampiran->laporanHarian && $lampiran->laporanHarian->user_id === $this->laporan->user_id;
        $isLinkedToReport = $this->laporan->lampiran->contains('id', $lampiran->id);

        if (! $belongsToUser && ! $isLinkedToReport) {
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
        if (! $this->previewLampiranId) {
            return null;
        }

        return LaporanLampiran::find($this->previewLampiranId);
    }

    public function getPreviewLampiranImageUrlProperty(): ?string
    {
        return $this->previewLampiran?->isPreviewable()
            ? route('laporan-lampiran.file', $this->previewLampiran)
            : null;
    }

    public function downloadLampiran(int $lampiranId)
    {
        $this->authorize('lampiranDownload', LaporanMingguan::class);

        $lampiran = LaporanLampiran::with('laporanHarian')->find($lampiranId);

        if (! $lampiran || ! $lampiran->file_path || ! Storage::disk()->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        // Ownership check: lampiran must belong to user's laporan harian or be linked to this laporan mingguan
        $belongsToUser = $lampiran->laporanHarian && $lampiran->laporanHarian->user_id === $this->laporan->user_id;
        $isLinkedToReport = $this->laporan->lampiran->contains('id', $lampiran->id);

        if (! $belongsToUser && ! $isLinkedToReport) {
            $this->notifyError('Anda tidak memiliki akses ke lampiran ini.');

            return;
        }

        return Storage::disk()->download($lampiran->file_path, $lampiran->file_name);
    }

    public function confirmRegenerate(): void
    {
        $this->authorize('generateWord', $this->laporan);
        if (! $this->checkSourceCompleteness()) {
            return;
        }

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
        if (! $this->checkSourceCompleteness()) {
            return;
        }

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

            GenerateLaporanMingguanJob::dispatch($this->laporan);

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

        if (! $external) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        if (! $external->hasFile() || ! $external->isFileCompleted()) {
            $this->notifyError('File tidak tersedia.');

            return;
        }

        if (! Storage::disk()->exists($external->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        // Determine filename: use title if available, otherwise use original filename
        $originalFileName = $external->file_name;
        $title = $external->judul ?? '';

        if (! empty($title)) {
            // Get the original file extension
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            // Create new filename from title with original extension
            $downloadFileName = $title.'.'.$extension;
        } else {
            // Use original filename if no title
            $downloadFileName = $originalFileName;
        }

        return Storage::disk()->download($external->file_path, $downloadFileName);
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

            ReleaseLaporanMingguanPdfJob::dispatch($this->laporan);

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

    public function render(QueueStatusService $queueStatusService, KurvaSService $kurvaSService)
    {
        $chartData = [];
        $totalRencana = null;
        $totalAktual = null;
        $progressHistory = [];
        $workGroupsForHistory = [];

        if ($this->laporan->dataKapal) {
            $chartData = $kurvaSService->getChartData($this->laporan->dataKapal);
            $progressHistory = $kurvaSService->getProgressHistory($this->laporan->dataKapal);

            // Get work groups for history table
            $workGroupsForHistory = KurvaSWorkGroup::where('data_kapal_id', $this->laporan->data_kapal_id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($wg) => [
                    'work_group_id' => $wg->id,
                    'nama' => $wg->nama,
                    'bobot' => $wg->bobot,
                ])
                ->toArray();

            // Calculate totals from progress history using service
            if (! empty($progressHistory)) {
                $workGroups = KurvaSWorkGroup::where('data_kapal_id', $this->laporan->data_kapal_id)
                    ->orderBy('sort_order')
                    ->get();

                $totals = $kurvaSService->calculateTotalsFromHistory($progressHistory, $workGroups);
                $totalRencana = $totals['total_rencana'];
                $totalAktual = $totals['total_aktual'];
            }
        }

        return view('livewire.laporan-mingguan.laporan-mingguan-show', [
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            'kurvaSChartData' => $chartData,
            'dataKapalNama' => $this->laporan->dataKapal?->nama,
            'totalRencana' => $totalRencana,
            'totalAktual' => $totalAktual,
            'progressHistory' => $progressHistory,
            'workGroupsForHistory' => $workGroupsForHistory,
            // Rekap NC periode ini: open selama periode + closed di periode/minggu sebelumnya
            'ncRekap' => $this->laporan->periode_mulai && $this->laporan->periode_selesai
                ? app(LaporanNcService::class)->rekapMingguan(
                    $this->laporan->data_kapal_id,
                    $this->laporan->periode_mulai,
                    $this->laporan->periode_selesai,
                )
                : collect(),
        ]);
    }
}
