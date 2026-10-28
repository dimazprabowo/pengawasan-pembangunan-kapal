<?php

namespace App\Livewire\LaporanHarian;

use App\Enums\NcStatus;
use App\Exports\LaporanNcExport;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcFile;
use App\Services\KontrakPeriodeService;
use App\Services\LaporanNcService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class LaporanNcList extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithFileUploads, WithPagination;

    protected $paginationTheme = 'tailwind';

    public DataKapal $dataKapal;

    public string $search = '';

    public string $statusFilter = '';

    public array $mingguFilter = [];

    public array $bulanFilter = [];

    public array $hariFilter = [];

    public int $perPage = 10;

    // Upload & link file ke beberapa NC
    public array $selectedNcIds = [];

    public bool $showUploadModal = false;

    public string $uploadKind = 'before';

    public array $uploadNcIds = [];

    public array $uploadFiles = [];

    // NC + file yang akan dilepas (dikonfirmasi via x-confirm-modal event)
    public ?int $detachNcId = null;

    public ?int $detachFileId = null;

    // NC yang akan di-reopen (dikonfirmasi via x-confirm-modal event)
    public ?int $reopenNcId = null;

    // Preview file
    public bool $showPreviewModal = false;

    public ?int $previewFileId = null;

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', LaporanNc::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMingguFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBulanFilter(): void
    {
        $this->resetPage();
    }

    public function updatingHariFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Bulan berubah → buang minggu & hari terpilih yang tidak valid lagi.
     */
    public function updatedBulanFilter(): void
    {
        $periodeService = app(KontrakPeriodeService::class);
        $allowed = $this->allowedMingguKe($periodeService);

        if ($allowed !== null && ! empty($this->mingguFilter)) {
            $this->mingguFilter = array_values(array_intersect(
                array_map('intval', $this->mingguFilter),
                $allowed
            ));
        }

        $this->pruneHariFilter($periodeService);
    }

    /**
     * Minggu berubah → buang hari terpilih yang tidak ada di minggu tsb.
     */
    public function updatedMingguFilter(): void
    {
        $this->pruneHariFilter(app(KontrakPeriodeService::class));
    }

    private function pruneHariFilter(KontrakPeriodeService $periodeService): void
    {
        $ranges = $this->allowedTanggalRanges($periodeService);
        if ($ranges === null || empty($this->hariFilter)) {
            return;
        }

        $this->hariFilter = array_values(array_filter($this->hariFilter, function ($tanggal) use ($ranges) {
            foreach ($ranges as [$mulai, $selesai]) {
                if ($tanggal >= $mulai->toDateString() && $tanggal <= $selesai->toDateString()) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Rentang tanggal valid dari bulan + minggu terpilih; null = semua.
     *
     * @return array<array{0: Carbon, 1: Carbon}>|null
     */
    private function allowedTanggalRanges(KontrakPeriodeService $periodeService): ?array
    {
        $ranges = [];
        foreach (array_filter(array_map('intval', $this->bulanFilter)) as $ke) {
            $p = $periodeService->getBulanPeriode($this->dataKapal, $ke);
            if ($p) {
                $ranges[] = [$p['mulai'], $p['selesai']];
            }
        }
        foreach (array_filter(array_map('intval', $this->mingguFilter)) as $ke) {
            $p = $periodeService->getMingguPeriode($this->dataKapal, $ke);
            if ($p) {
                $ranges[] = [$p['mulai'], $p['selesai']];
            }
        }

        return $ranges === [] ? null : $ranges;
    }

    private function allowedMingguKe(KontrakPeriodeService $periodeService): ?array
    {
        $bulanKe = array_filter(array_map('intval', $this->bulanFilter));
        if (empty($bulanKe)) {
            return null;
        }

        $allowed = [];
        foreach ($bulanKe as $ke) {
            $periode = $periodeService->getBulanPeriode($this->dataKapal, $ke);
            if ($periode) {
                $allowed = array_merge($allowed, range($periode['minggu_dari'], $periode['minggu_sampai']));
            }
        }

        return array_values(array_unique($allowed));
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'mingguFilter', 'bulanFilter', 'hariFilter', 'perPage']);
        $this->resetPage();
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', LaporanHarian::class);

        return (new LaporanNcExport(
            $this->dataKapal->id, $this->search, $this->statusFilter ?: null, $this->mingguFilter, $this->bulanFilter, $this->hariFilter
        ))->download('list-nc-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', LaporanHarian::class);

        $ncList = app(LaporanNcService::class)->filteredQuery(
            $this->dataKapal->id, $this->search, $this->statusFilter ?: null, $this->mingguFilter, $this->bulanFilter, $this->hariFilter
        )->get();

        $pdf = Pdf::loadView('exports.laporan-nc-pdf', [
            'ncList' => $ncList,
            'dataKapalNama' => $this->dataKapal->nama,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'list-nc-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function toggleStatus(int $ncId, LaporanNcService $service): void
    {
        try {
            $nc = LaporanNc::where('data_kapal_id', $this->dataKapal->id)->findOrFail($ncId);
            $this->authorize('update', $nc);

            if ($nc->isClosed()) {
                $this->reopenNcId = $ncId;
                $this->dispatch('confirm-modal',
                    title: 'Buka Kembali NC?',
                    message: "Status NC {$nc->nomor} akan kembali menjadi Open. Lanjutkan?",
                    confirmText: 'Ya, Buka Kembali',
                    cancelText: 'Batal',
                    type: 'warning',
                    action: 'confirmReopen',
                );

                return;
            }

            $service->toggleStatus($nc, auth()->id());
            $this->notifySuccess("NC {$nc->nomor} berhasil ditutup.");
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status NC.');
        } catch (ValidationException $e) {
            $this->notifyError(collect($e->errors())->flatten()->first() ?? 'Validasi gagal.');
        } catch (\Exception $e) {
            Log::error('LaporanNcList: toggle status gagal', ['nc_id' => $ncId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmReopen(LaporanNcService $service): void
    {
        try {
            $nc = LaporanNc::where('data_kapal_id', $this->dataKapal->id)->findOrFail($this->reopenNcId);
            $this->authorize('update', $nc);

            $service->toggleStatus($nc, auth()->id());
            $this->notifySuccess("NC {$nc->nomor} dibuka kembali.");
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        } finally {
            $this->reopenNcId = null;
        }
    }

    public function closeUploadModal(): void
    {
        $this->reset(['showUploadModal', 'uploadFiles', 'uploadNcIds']);
        $this->uploadKind = 'before';
    }

    /**
     * Hapus satu file dari daftar sebelum di-upload.
     */
    public function removeUploadFile(int $index): void
    {
        unset($this->uploadFiles[$index]);
        $this->uploadFiles = array_values($this->uploadFiles);
        $this->resetValidation(['uploadFiles', 'uploadFiles.*']);
    }

    /**
     * Centang/hapus centang semua NC pada halaman aktif.
     */
    public function toggleSelectAll(): void
    {
        $pageIds = app(LaporanNcService::class)->filteredQuery(
            $this->dataKapal->id, $this->search, $this->statusFilter ?: null,
            $this->mingguFilter, $this->bulanFilter, $this->hariFilter
        )->forPage($this->getPage(), $this->perPage)->pluck('id')->all();

        $selected = array_map('intval', $this->selectedNcIds);
        $allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];

        $this->selectedNcIds = $allSelected
            ? array_values(array_diff($selected, $pageIds))
            : array_values(array_unique([...$selected, ...$pageIds]));
    }

    /**
     * Centang/hapus centang satu NC — server sebagai single source of truth
     * (jangan wire:model agar sinkron dengan toggle grup/select-all).
     */
    public function toggleNcSelect(int $ncId): void
    {
        $selected = array_map('intval', $this->selectedNcIds);
        $this->selectedNcIds = in_array($ncId, $selected, true)
            ? array_values(array_diff($selected, [$ncId]))
            : [...$selected, $ncId];
    }

    /**
     * Centang/hapus centang semua NC dalam satu grup laporan harian.
     */
    public function toggleNcGroup(string $idsCsv): void
    {
        $ids = array_map('intval', array_filter(explode(',', $idsCsv)));
        $selected = array_map('intval', $this->selectedNcIds);
        $allSelected = $ids !== [] && array_diff($ids, $selected) === [];

        $this->selectedNcIds = $allSelected
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    /**
     * Konfirmasi tutup semua NC terpilih (wajib open + punya file after).
     */
    public function confirmCloseSelected(): void
    {
        $this->authorize('update', new LaporanNc);

        $openIds = LaporanNc::where('data_kapal_id', $this->dataKapal->id)
            ->whereIn('id', $this->selectedNcIds)
            ->where('status', 'open')
            ->pluck('id')->all();

        if ($openIds === []) {
            $this->notifyWarning('Tidak ada NC berstatus Open pada pilihan Anda.');

            return;
        }

        $this->dispatch('confirm-modal',
            title: 'Tutup '.count($openIds).' NC?',
            message: 'Semua NC terpilih yang berstatus Open akan ditandai Closed. NC yang belum punya file bukti After akan dilewati. Lanjutkan?',
            confirmText: 'Ya, Tutup',
            cancelText: 'Batal',
            type: 'warning',
            action: 'executeCloseSelected',
        );
    }

    public function executeCloseSelected(LaporanNcService $service): void
    {
        $this->authorize('update', new LaporanNc);

        $closed = $skipped = 0;
        $ncs = LaporanNc::where('data_kapal_id', $this->dataKapal->id)
            ->whereIn('id', $this->selectedNcIds)
            ->where('status', 'open')
            ->get();

        foreach ($ncs as $nc) {
            try {
                $service->toggleStatus($nc, auth()->id());
                $closed++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        if ($closed > 0) {
            $this->notifySuccess("{$closed} NC berhasil ditutup.".($skipped > 0 ? " {$skipped} dilewati (belum ada bukti After)." : ''));
        } else {
            $this->notifyError('Tidak ada NC yang bisa ditutup — pastikan bukti After sudah terunggah.');
        }

        $this->selectedNcIds = [];
    }

    public function openUploadModal(?int $ncId = null): void
    {
        $this->authorize('update', new LaporanNc);

        // NC tujuan = checklist di tabel (+ baris yang diklik bila ada)
        $this->uploadNcIds = array_values(array_unique(
            $ncId !== null ? [...array_map('intval', $this->selectedNcIds), $ncId] : array_map('intval', $this->selectedNcIds)
        ));
        $this->uploadKind = 'before';
        $this->uploadFiles = [];
        $this->resetValidation();
        $this->showUploadModal = true;
    }

    public function saveUploads(LaporanNcService $service): void
    {
        $this->authorize('update', new LaporanNc);

        $this->validate([
            'uploadNcIds' => 'required|array|min:1',
            'uploadNcIds.*' => 'integer|exists:laporan_nc,id',
            'uploadKind' => 'required|in:before,after',
            'uploadFiles' => 'required|array|min:1|max:10',
            'uploadFiles.*' => file_upload_validation_rule('laporan_nc'),
        ], [], [
            'uploadNcIds' => 'NC tujuan',
            'uploadKind' => 'jenis dokumen',
            'uploadFiles' => 'file bukti',
            'uploadFiles.*' => 'file bukti',
        ]);

        try {
            $count = $service->storeUploads($this->dataKapal->id, $this->uploadNcIds, $this->uploadFiles, $this->uploadKind, auth()->id());
            $this->notifySuccess("{$count} file berhasil diunggah dan sedang diproses.");
            $this->showUploadModal = false;
            $this->uploadFiles = [];
            $this->uploadNcIds = [];
            $this->selectedNcIds = [];
        } catch (\Exception $e) {
            Log::error('LaporanNcList: upload gagal', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan saat mengunggah file.');
        }
    }

    /**
     * Minta konfirmasi sebelum melepas file dari NC.
     */
    public function confirmDetach(int $ncId, int $fileId): void
    {
        try {
            $nc = LaporanNc::where('data_kapal_id', $this->dataKapal->id)->findOrFail($ncId);
            $this->authorize('update', $nc);
        } catch (\Exception) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah NC ini.');

            return;
        }

        $this->detachNcId = $ncId;
        $this->detachFileId = $fileId;
        $this->dispatch('confirm-modal',
            title: 'Lepas File Bukti?',
            message: "File akan dilepas dari NC {$nc->nomor}. File yang tidak tertaut ke NC lain akan dihapus permanen. Lanjutkan?",
            confirmText: 'Ya, Lepas',
            cancelText: 'Batal',
            type: 'danger',
            action: 'executeDetachFile',
        );
    }

    public function executeDetachFile(LaporanNcService $service): void
    {
        try {
            $nc = LaporanNc::where('data_kapal_id', $this->dataKapal->id)->findOrFail($this->detachNcId);
            $this->authorize('update', $nc);
            $file = LaporanNcFile::findOrFail($this->detachFileId);
            $service->detachFile($nc, $file);
            $this->notifySuccess('File dilepas dari NC.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        } finally {
            $this->detachNcId = null;
            $this->detachFileId = null;
        }
    }

    public function previewFile(int $fileId): void
    {
        $this->authorize('lampiranPreview', LaporanHarian::class);
        $this->previewFileId = $fileId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewFileId = null;
    }

    public function getPreviewFileProperty(): ?LaporanNcFile
    {
        return $this->previewFileId ? LaporanNcFile::with('ncs.laporanHarian')->find($this->previewFileId) : null;
    }

    public function getPreviewFileImageUrlProperty(): ?string
    {
        return $this->previewFile?->data_url;
    }

    /**
     * Alias yang dipanggil dari x-lampiran-preview-modal.
     */
    public function downloadLampiran(int $fileId)
    {
        return $this->downloadFile($fileId);
    }

    public function downloadFile(int $fileId)
    {
        $this->authorize('lampiranDownload', LaporanHarian::class);
        $file = LaporanNcFile::findOrFail($fileId);

        if (! $file->file_path || ! Storage::disk('local')->exists($file->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return null;
        }

        return Storage::disk('local')->download($file->file_path, $file->file_name);
    }

    public function render(LaporanNcService $service, KontrakPeriodeService $periodeService)
    {
        $allowedMinggu = $this->allowedMingguKe($periodeService);

        return view('livewire.laporan-harian.laporan-nc-list', [
            'ncList' => $service->getFiltered(
                $this->dataKapal->id, $this->search, $this->statusFilter ?: null, $this->perPage,
                $this->mingguFilter, $this->bulanFilter, $this->hariFilter
            ),
            'hariOptions' => $service->tanggalOptions(
                $this->dataKapal->id,
                $this->allowedTanggalRanges($periodeService) ?? []
            ),
            'mingguOptions' => collect($periodeService->getMingguList($this->dataKapal))
                ->when($allowedMinggu !== null, fn ($c) => $c->filter(fn ($m) => in_array($m['minggu_ke'], $allowedMinggu, true)))
                ->map(fn ($m) => ['value' => $m['minggu_ke'], 'label' => 'Minggu ke-'.$m['minggu_ke'].' ('.$m['label'].')'])->all(),
            'bulanOptions' => collect($periodeService->getBulanList($this->dataKapal))
                ->map(fn ($b) => ['value' => $b['bulan_ke'], 'label' => 'Bulan ke-'.$b['bulan_ke'].' ('.$b['label'].')'])->all(),
            'ncOptions' => LaporanNc::where('data_kapal_id', $this->dataKapal->id)
                ->orderByDesc('created_at')
                ->get(['id', 'nomor', 'kendala', 'status'])
                ->map(fn ($n) => ['id' => $n->id, 'label' => ($n->nomor ?? 'NC').' — '.Str::limit($n->kendala, 60)]),
            'selectedNcInfos' => LaporanNc::whereIn('id', $this->uploadNcIds ?: [0])
                ->orderByDesc('created_at')
                ->get(['id', 'nomor', 'kendala']),
            'statuses' => NcStatus::cases(),
        ]);
    }
}
