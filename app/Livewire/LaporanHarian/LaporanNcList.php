<?php

namespace App\Livewire\LaporanHarian;

use App\Enums\NcFormJenis;
use App\Enums\NcFormStatus;
use App\Enums\NcStatus;
use App\Exports\LaporanNcExport;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Models\LaporanNcFoto;
use App\Services\KontrakPeriodeService;
use App\Services\LaporanNcFormService;
use App\Services\LaporanNcService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public bool $filterChanged = false;

    // NC terpilih (checklist) untuk Form Penutupan
    public array $selectedNcIds = [];

    // Upload scan Form NC bertanda tangan
    public bool $showUploadModal = false;

    public ?int $uploadFormId = null;

    public $signedFile = null;

    // Reopen NC: modal dengan alasan wajib (audit trail)
    public bool $showReopenModal = false;

    public ?int $reopenNcId = null;

    public string $reopenNote = '';

    // Aksi yang menunggu konfirmasi x-confirm-modal (event-driven)
    public ?int $deleteFormId = null;

    // Preview foto bukti / scan form: type 'foto' | 'form'
    public bool $showPreviewModal = false;

    public ?string $previewType = null;

    public ?int $previewId = null;

    // Panel Form NC: search, filter, sorting & paginasi sendiri (paginator 'formPage')
    public string $formSearch = '';

    public string $formJenisFilter = '';

    public string $formStatusFilter = '';

    public int $formPerPage = 10;

    public string $formSortField = 'created';

    public string $formSortDir = 'desc';

    public bool $formFilterChanged = false;

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', LaporanNc::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
    }

    // ── Filter ───────────────────────────────────────────────────────────
    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingMingguFilter(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingBulanFilter(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingHariFilter(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
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
        $this->filterChanged = true;
    }

    // ── Panel Form NC: filter & sorting (paginator 'formPage') ────────────
    public function updatingFormSearch(): void
    {
        $this->resetPage('formPage');
        $this->formFilterChanged = true;
    }

    public function updatingFormJenisFilter(): void
    {
        $this->resetPage('formPage');
        $this->formFilterChanged = true;
    }

    public function updatingFormStatusFilter(): void
    {
        $this->resetPage('formPage');
        $this->formFilterChanged = true;
    }

    public function updatingFormPerPage(): void
    {
        $this->resetPage('formPage');
    }

    public function resetFormFilters(): void
    {
        $this->reset(['formSearch', 'formJenisFilter', 'formStatusFilter', 'formPerPage']);
        $this->resetPage('formPage');
        $this->formFilterChanged = true;
    }

    public function formSortBy(string $field): void
    {
        if ($this->formSortField === $field) {
            $this->formSortDir = $this->formSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->formSortField = $field;
            $this->formSortDir = 'asc';
        }

        $this->resetPage('formPage');
    }

    // ── Export ───────────────────────────────────────────────────────────
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

    // ── Checklist NC (untuk Form Penutupan) ──────────────────────────────
    /**
     * Centang/hapus centang semua NC Open pada halaman aktif.
     */
    public function toggleSelectAll(): void
    {
        $pageIds = app(LaporanNcService::class)->filteredQuery(
            $this->dataKapal->id, $this->search, $this->statusFilter ?: null,
            $this->mingguFilter, $this->bulanFilter, $this->hariFilter
        )->forPage($this->getPage(), $this->perPage)->where('status', NcStatus::Open)->pluck('id')->all();

        $this->toggleIds($pageIds);
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
     * Centang/hapus centang semua NC Open dalam satu grup laporan harian.
     */
    public function toggleNcGroup(string $idsCsv): void
    {
        $this->toggleIds(array_map('intval', array_filter(explode(',', $idsCsv))));
    }

    private function toggleIds(array $ids): void
    {
        $selected = array_map('intval', $this->selectedNcIds);
        $allSelected = $ids !== [] && array_diff($ids, $selected) === [];

        $this->selectedNcIds = $allSelected
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    public function clearSelection(): void
    {
        $this->selectedNcIds = [];
    }

    // ── Form NC: generate ────────────────────────────────────────────────
    /**
     * Generate (atau segarkan draft) Form Temuan untuk satu laporan harian, lalu unduh docx.
     */
    public function generateTemuan(int $laporanId, LaporanNcFormService $service)
    {
        try {
            $this->authorize('create', LaporanNcForm::class);
            $laporan = LaporanHarian::where('data_kapal_id', $this->dataKapal->id)->findOrFail($laporanId);

            $form = $service->createTemuan($laporan);
            $this->notifySuccess("{$form->nomor} siap — cetak, tanda tangani, lalu unggah scan-nya di panel Form NC.");

            return $this->downloadDocument($form, $service);
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk membuat Form NC.');
        } catch (ValidationException $e) {
            $this->notifyWarning(collect($e->errors())->flatten()->first() ?? 'Validasi gagal.');
        } catch (\Exception $e) {
            Log::error('LaporanNcList: generate form temuan gagal', ['laporan_id' => $laporanId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }

        return null;
    }

    /**
     * Buat draft Form Penutupan dari NC terpilih (lintas hari), lalu unduh docx.
     */
    public function generatePenutupan(LaporanNcFormService $service)
    {
        try {
            $this->authorize('create', LaporanNcForm::class);

            [$form, $skipped] = $service->createPenutupan($this->dataKapal->id, array_map('intval', $this->selectedNcIds));
            $this->selectedNcIds = [];
            $this->notifySuccess("{$form->nomor} berisi {$form->ncs()->count()} NC."
                .($skipped > 0 ? " {$skipped} NC dilewati (closed / sudah di draft penutupan lain)." : '')
                .' Unggah scan bertanda tangan untuk menutup NC.');

            return $this->downloadDocument($form, $service);
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk membuat Form NC.');
        } catch (ValidationException $e) {
            $this->notifyWarning(collect($e->errors())->flatten()->first() ?? 'Validasi gagal.');
        } catch (\Exception $e) {
            Log::error('LaporanNcList: generate form penutupan gagal', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }

        return null;
    }

    /**
     * Unduh (cetak ulang) dokumen Word sebuah form.
     */
    public function downloadForm(int $formId, LaporanNcFormService $service)
    {
        try {
            $this->authorize('create', LaporanNcForm::class);

            return $this->downloadDocument($this->findForm($formId), $service);
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk mengunduh dokumen Form NC.');
        } catch (\Exception $e) {
            Log::error('LaporanNcList: download form gagal', ['form_id' => $formId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }

        return null;
    }

    private function downloadDocument(LaporanNcForm $form, LaporanNcFormService $service)
    {
        return response()->download($service->generateDocument($form), $service->downloadName($form))
            ->deleteFileAfterSend();
    }

    private function findForm(int $formId): LaporanNcForm
    {
        return LaporanNcForm::where('data_kapal_id', $this->dataKapal->id)->findOrFail($formId);
    }

    // ── Form NC: upload scan TTD ─────────────────────────────────────────
    public function openUploadModal(int $formId): void
    {
        try {
            $form = $this->findForm($formId);
            $this->authorize('upload', $form);
        } catch (\Exception) {
            $this->notifyError('Anda tidak memiliki izin untuk mengunggah Form NC.');

            return;
        }

        $this->uploadFormId = $form->id;
        $this->signedFile = null;
        $this->resetValidation();
        $this->showUploadModal = true;
    }

    public function closeUploadModal(): void
    {
        $this->reset(['showUploadModal', 'uploadFormId', 'signedFile']);
        $this->resetValidation();
    }

    public function saveUpload(LaporanNcFormService $service): void
    {
        $form = $this->findForm((int) $this->uploadFormId);
        $this->authorize('upload', $form);

        $this->validate(
            ['signedFile' => file_upload_validation_rule('laporan_nc_form', true)],
            [],
            ['signedFile' => 'scan form bertanda tangan']
        );

        try {
            $closed = $service->uploadSigned($form, $this->signedFile, auth()->id());
            $this->notifySuccess("Scan {$form->nomor} berhasil diunggah dan sedang diproses."
                .($closed > 0 ? " {$closed} NC ditutup." : ''));
            $this->closeUploadModal();
        } catch (\Exception $e) {
            Log::error('LaporanNcList: upload form gagal', ['form_id' => $form->id, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan saat mengunggah file.');
        }
    }

    public function getUploadFormProperty(): ?LaporanNcForm
    {
        return $this->uploadFormId
            ? LaporanNcForm::with(['ncs:id,nomor,kendala,status'])->where('data_kapal_id', $this->dataKapal->id)->find($this->uploadFormId)
            : null;
    }

    // ── Form NC: hapus draft ─────────────────────────────────────────────
    public function confirmDeleteForm(int $formId): void
    {
        try {
            $form = $this->findForm($formId);
            $this->authorize('delete', $form);
        } catch (\Exception) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus form ini.');

            return;
        }

        // Super admin mem-bypass Policy (Gate::before) — cek state tetap wajib
        if (! $form->isDraft()) {
            $this->notifyWarning('Form yang sudah ditandatangani tidak bisa dihapus.');

            return;
        }

        $this->deleteFormId = $form->id;
        $this->dispatch('confirm-modal',
            title: 'Hapus Draft Form NC?',
            message: "Draft {$form->nomor} akan dihapus. NC di dalamnya bisa dimasukkan ke form lain. Lanjutkan?",
            confirmText: 'Ya, Hapus',
            cancelText: 'Batal',
            type: 'danger',
            action: 'executeDeleteForm',
        );
    }

    public function executeDeleteForm(LaporanNcFormService $service): void
    {
        try {
            $form = $this->findForm((int) $this->deleteFormId);
            $this->authorize('delete', $form);
            $service->deleteDraft($form);
            $this->notifySuccess("Draft {$form->nomor} dihapus.");
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus form ini.');
        } catch (ValidationException $e) {
            $this->notifyWarning(collect($e->errors())->flatten()->first() ?? 'Validasi gagal.');
        } catch (\Exception $e) {
            Log::error('LaporanNcList: hapus draft gagal', ['form_id' => $this->deleteFormId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        } finally {
            $this->deleteFormId = null;
        }
    }

    // ── Reopen NC ────────────────────────────────────────────────────────
    public function requestReopen(int $ncId): void
    {
        try {
            $nc = LaporanNc::where('data_kapal_id', $this->dataKapal->id)->findOrFail($ncId);
            $this->authorize('update', $nc);
        } catch (\Exception) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status NC.');

            return;
        }

        $this->reopenNcId = $nc->id;
        $this->reopenNote = '';
        $this->showReopenModal = true;
    }

    public function closeReopenModal(): void
    {
        $this->showReopenModal = false;
        $this->reopenNcId = null;
        $this->reopenNote = '';
    }

    public function confirmReopen(LaporanNcService $service): void
    {
        try {
            $nc = LaporanNc::where('data_kapal_id', $this->dataKapal->id)->findOrFail($this->reopenNcId);
            $this->authorize('update', $nc);

            $this->validate(
                ['reopenNote' => 'required|string|min:5|max:500'],
                ['reopenNote.required' => 'Alasan buka kembali wajib diisi.', 'reopenNote.min' => 'Alasan minimal 5 karakter.', 'reopenNote.max' => 'Alasan maksimal 500 karakter.']
            );

            $service->reopen($nc, auth()->id(), $this->reopenNote);
            $this->notifySuccess("NC {$nc->nomor} dibuka kembali.");
            $this->closeReopenModal();
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status NC.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('LaporanNcList: reopen gagal', ['nc_id' => $this->reopenNcId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    // ── Preview & download file (foto bukti / scan form) ─────────────────
    public function previewFoto(int $fotoId): void
    {
        $this->openPreview('foto', $fotoId);
    }

    public function previewForm(int $formId): void
    {
        $this->openPreview('form', $formId);
    }

    private function openPreview(string $type, int $id): void
    {
        $this->authorize('lampiranPreview', LaporanHarian::class);
        $this->previewType = $type;
        $this->previewId = $id;

        if (! $this->previewItem?->isFileCompleted()) {
            $this->notifyWarning('File belum tersedia.');
            $this->closePreviewModal();

            return;
        }

        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->reset(['showPreviewModal', 'previewType', 'previewId']);
    }

    public function getPreviewItemProperty(): LaporanNcFoto|LaporanNcForm|null
    {
        if (! $this->previewId) {
            return null;
        }

        return $this->previewType === 'form'
            ? LaporanNcForm::where('data_kapal_id', $this->dataKapal->id)->find($this->previewId)
            : LaporanNcFoto::whereHas('nc', fn ($q) => $q->where('data_kapal_id', $this->dataKapal->id))->with('nc')->find($this->previewId);
    }

    public function getPreviewImageUrlProperty(): ?string
    {
        $item = $this->previewItem;
        if (! $item?->isPreviewable()) {
            return null;
        }

        return $item instanceof LaporanNcForm
            ? route('laporan-nc-form.file', $item)
            : route('laporan-nc-foto.file', $item);
    }

    /**
     * Dipanggil dari x-lampiran-preview-modal (downloadMethod).
     */
    public function downloadPreviewFile(int $id)
    {
        $this->authorize('lampiranDownload', LaporanHarian::class);
        $item = $this->previewItem;

        if (! $item || $item->id !== $id || ! $item->file_path || ! Storage::disk('local')->exists($item->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return null;
        }

        $name = $item instanceof LaporanNcForm
            ? app(LaporanNcFormService::class)->downloadName($item, true)
            : $item->file_name;

        return Storage::disk('local')->download($item->file_path, $name);
    }

    public function render(LaporanNcService $service, LaporanNcFormService $formService, KontrakPeriodeService $periodeService)
    {
        $allowedMinggu = $this->allowedMingguKe($periodeService);

        $ncList = $service->getFiltered(
            $this->dataKapal->id, $this->search, $this->statusFilter ?: null, $this->perPage,
            $this->mingguFilter, $this->bulanFilter, $this->hariFilter
        );

        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$ncList->total()} data NC.");
            $this->filterChanged = false;
        }

        $formList = $formService->getForKapal(
            $this->dataKapal->id, $this->formPerPage, 'formPage',
            $this->formSearch, $this->formJenisFilter, $this->formStatusFilter,
            $this->formSortField, $this->formSortDir
        );

        if ($this->formFilterChanged) {
            $this->notifyInfo("Ditemukan {$formList->total()} form NC.");
            $this->formFilterChanged = false;
        }

        return view('livewire.laporan-harian.laporan-nc-list', [
            'ncList' => $ncList,
            'formList' => $formList,
            'temuanByLaporan' => $formService->temuanByLaporan($ncList->getCollection()->pluck('laporan_harian_id')->unique()->all()),
            'hasProcessing' => $ncList->getCollection()->contains(fn ($nc) => $nc->foto?->isFileProcessing())
                || $formList->getCollection()->contains->isFileProcessing(),
            'hariOptions' => $service->tanggalOptions(
                $this->dataKapal->id,
                $this->allowedTanggalRanges($periodeService) ?? []
            ),
            'mingguOptions' => collect($periodeService->getMingguList($this->dataKapal))
                ->when($allowedMinggu !== null, fn ($c) => $c->filter(fn ($m) => in_array($m['minggu_ke'], $allowedMinggu, true)))
                ->map(fn ($m) => ['value' => $m['minggu_ke'], 'label' => 'Minggu ke-'.$m['minggu_ke'].' ('.$m['label'].')'])->all(),
            'bulanOptions' => collect($periodeService->getBulanList($this->dataKapal))
                ->map(fn ($b) => ['value' => $b['bulan_ke'], 'label' => 'Bulan ke-'.$b['bulan_ke'].' ('.$b['label'].')'])->all(),
            'statusOptions' => collect(NcStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'formJenisOptions' => collect(NcFormJenis::cases())->map(fn ($j) => ['value' => $j->value, 'label' => $j->label()])->all(),
            'formStatusOptions' => collect(NcFormStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
        ]);
    }
}
