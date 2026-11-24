<?php

namespace App\Livewire\MasterData;

use App\Enums\PengujianCatalog;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\Pengujian;
use App\Models\PengujianFile;
use App\Services\DataProyekService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProjectPengujianManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, HasTableSorting, WithFileUploads, WithPagination;

    #[Locked]
    public DataKapal $dataKapal;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    public array $drafts = [];

    public bool $showCatalog = false;

    #[Locked]
    public string $catalogEntity = 'status';

    public string $catalogName = '';

    #[Locked]
    public ?int $editingCatalogId = null;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingId = null;

    #[Locked]
    public string $deletingEntity = 'pengujian';

    // Modal daftar file tersimpan (ikon folder di sel File)
    public ?int $filesModalId = null;

    // Lampiran pengujian: upload pending per baris (key = row key drafts)
    public array $pengujianUploads = [];

    public bool $showCropperModal = false;

    public ?string $croppingImageUrl = null;

    public ?string $croppingFileKey = null;

    public array $cropData = [];

    public bool $showPreviewModal = false;

    #[Locked]
    public ?int $previewId = null;

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', Pengujian::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
        $this->sortField = 'tanggal';
        $this->sortDir = 'desc';
    }

    public function boot(): void
    {
        if (isset($this->dataKapal)) {
            $this->authorize('viewAny', Pengujian::class);
            $this->authorizeDataKapalAccess($this->dataKapal);
        }
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'drafts.')) {
            $parts = explode('.', $property);
            if (isset($parts[1])) {
                $this->drafts[$parts[1]]['dirty'] = true;
                $this->validateOnly($property);
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->markFiltersChanged();
    }

    public function updatingStatusFilter(): void
    {
        $this->markFiltersChanged();
    }

    public function updatingPerPage(): void
    {
        $this->markFiltersChanged();
    }

    private function markFiltersChanged(): void
    {
        $this->resetPage('pengujianPage');
        $this->filterChanged = true;
    }

    public function warnUnsaved(): void
    {
        $this->notifyWarning('Simpan atau batalkan perubahan pengujian sebelum berpindah halaman/tab.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'perPage']);
        $this->resetPage('pengujianPage');
        $this->filterChanged = true;
    }

    public function addRow(DataProyekService $service): void
    {
        $this->authorize('create', Pengujian::class);
        $this->drafts['new-'.Str::uuid()] = [
            'nama_pengujian' => '',
            'tanggal' => '',
            'pengujian_status_id' => $service->defaultPengujianStatusId() ?? '',
            'hasil' => '',
            'keterangan' => '',
            'dirty' => true,
        ];
    }

    public function cancelRow(string $key): void
    {
        $this->authorize('viewAny', Pengujian::class);
        unset($this->drafts[$key], $this->pengujianUploads[$key]);
        $this->dispatch('pengujian-row-saved', key: $key);
        $this->resetValidation();
    }

    public function saveRow(string $key, DataProyekService $service): void
    {
        try {
            if (! isset($this->drafts[$key])) {
                return;
            }
            $row = $this->drafts[$key];
            $pengujian = str_starts_with($key, 'row-') ? $service->findPengujian($this->dataKapal, (int) substr($key, 4)) : null;
            $this->authorize($pengujian ? 'update' : 'create', $pengujian ?: Pengujian::class);
            $rules = [];
            foreach ($this->rules() as $field => $rule) {
                if (str_starts_with($field, 'drafts.')) {
                    $rules[str_replace('.*.', '.'.$key.'.', $field)] = $rule;
                }
            }
            $this->validate($rules);
            $pengujian = $service->savePengujian($this->dataKapal, array_intersect_key($row, array_flip(['nama_pengujian', 'tanggal', 'pengujian_status_id', 'hasil', 'keterangan'])), $pengujian);
            // File lampiran yang diunggah saat baris masih draft ikut diproses
            // setelah pengujian tersimpan (diproses async oleh ProcessPengujianFile).
            $pendingFiles = $this->pengujianUploads[$key] ?? [];
            if ($pendingFiles) {
                $prefix = 'pengujianUploads.'.$key;
                $this->validate([$prefix.'.*.file' => file_upload_validation_rule('pengujian_file', true)], [], [$prefix.'.*.file' => 'file pengujian']);
                $service->addPengujianFiles($pengujian, $pendingFiles);
                unset($this->pengujianUploads[$key]);
            }
            unset($this->drafts[$key]);
            $this->dispatch('pengujian-row-saved', key: $key);
            $this->notifySuccess('Data pengujian berhasil disimpan.');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'drafts.') ? $field : "drafts.$key.$field"] = $errors;
            }
            $error = ValidationException::withMessages($messages);
            $this->notifyValidationError($error);
            throw $error;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menyimpan pengujian.');
        } catch (\Exception $e) {
            Log::error('Project pengujian save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function manageCatalog(string $entity): void
    {
        $kind = PengujianCatalog::tryFrom($entity);
        abort_unless($kind, 404);
        $this->authorize('viewAny', $kind->modelClass());
        $this->catalogEntity = $kind->value;
        $this->resetPage('catalogPage');
        $this->showCatalog = true;
        $this->dispatch('pengujian-catalog-opened');
        $this->reset(['catalogName', 'editingCatalogId']);
        $this->resetValidation();
    }

    public function closeCatalog(): void
    {
        $this->reset(['showCatalog', 'catalogName', 'editingCatalogId']);
        $this->resetValidation();
    }

    public function newCatalog(): void
    {
        $this->authorize('create', PengujianCatalog::from($this->catalogEntity)->modelClass());
        $this->reset(['catalogName', 'editingCatalogId']);
        $this->resetValidation();
        $this->dispatch('pengujian-catalog-focus');
    }

    public function editCatalog(int $id, DataProyekService $service): void
    {
        $model = $service->findPengujianCatalog(PengujianCatalog::from($this->catalogEntity), $id);
        $this->authorize('update', $model);
        $this->editingCatalogId = $id;
        $this->catalogName = $model->label();
        $this->resetValidation();
        $this->dispatch('pengujian-catalog-focus');
    }

    public function updatedCatalogName(): void
    {
        $this->validateOnly('catalogName');
    }

    public function saveCatalog(DataProyekService $service): void
    {
        $kind = PengujianCatalog::from($this->catalogEntity);
        $this->authorize($this->editingCatalogId ? 'update' : 'create', $this->editingCatalogId ? $service->findPengujianCatalog($kind, $this->editingCatalogId) : $kind->modelClass());
        try {
            $this->validate(['catalogName' => 'required|string|max:100']);
            $service->savePengujianCatalog($kind, $this->catalogName, $this->editingCatalogId);
            $this->reset(['catalogName', 'editingCatalogId']);
            $this->resetValidation();
            $this->notifySuccess($kind->label().' berhasil disimpan.');
        } catch (ValidationException $e) {
            $error = ValidationException::withMessages(['catalogName' => $e->errors()['catalogName'] ?? $e->errors()['nama'] ?? ['Nama tidak valid.']]);
            $this->notifyValidationError($error);
            throw $error;
        } catch (\Exception $e) {
            Log::error('Pengujian catalog save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    // ── Lampiran file (pola kolom file rapat — satu kolom, banyak file) ──

    public function openFiles(int $id): void
    {
        $this->filesModalId = $id;
    }

    public function closeFiles(): void
    {
        $this->reset('filesModalId');
    }

    /**
     * Simpan file yang sudah diupload pada baris pengujian tersimpan.
     * Baris draft tidak memanggil ini — filenya ikut diproses di saveRow().
     */
    public function uploadPengujianFiles(string $key, DataProyekService $service): void
    {
        try {
            abort_unless(str_starts_with($key, 'row-'), 404);
            $pengujian = $service->findPengujian($this->dataKapal, (int) substr($key, 4));
            $this->authorize('update', $pengujian);
            $prefix = 'pengujianUploads.'.$key;
            $this->validate([
                $prefix => ['required', 'array', 'min:1'],
                $prefix.'.*.file' => file_upload_validation_rule('pengujian_file', true),
            ], [], [$prefix => 'file pengujian', $prefix.'.*.file' => 'file pengujian']);
            $service->addPengujianFiles($pengujian, $this->pengujianUploads[$key] ?? []);
            unset($this->pengujianUploads[$key]);
            $this->notifySuccess('File pengujian ditambahkan dan sedang diproses.');
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk menambah file pengujian.');
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Exception $e) {
            Log::error('ProjectPengujianManagement: upload file gagal', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    /**
     * Hapus satu file dari antrian upload (sebelum disimpan).
     */
    public function removePengujianUpload(string $key, string $uploadKey): void
    {
        try {
            $this->authorize(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update');
            $entry = $this->pengujianUploads[$key][$uploadKey] ?? null;
            if (is_object($entry['file'] ?? null)) {
                $entry['file']->delete();
            }
            unset($this->pengujianUploads[$key][$uploadKey]);
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah file pengujian.');
        }
    }

    public function openPengujianFileCropper(string $key, string $uploadKey): void
    {
        $this->authorize('viewAny', Pengujian::class);
        $path = $key.'.'.$uploadKey;
        $entry = data_get($this->pengujianUploads, $path);
        $file = $entry['file'] ?? null;
        validator(['file' => $file], ['file' => ['required', 'image', 'max:20480']])->validate();
        $this->croppingFileKey = $path;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $entry['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function saveCrop(): void
    {
        if ($this->croppingFileKey !== null) {
            data_set($this->pengujianUploads, $this->croppingFileKey.'.cropData', $this->cropData);
        }
        $this->closeCropper();
    }

    public function closeCropper(): void
    {
        $this->reset(['showCropperModal', 'croppingImageUrl', 'croppingFileKey', 'cropData']);
    }

    // ── Preview & download lampiran ──────────────────────────────────────

    public function previewFile(int $fileId): void
    {
        $this->authorize('viewAny', Pengujian::class);
        $this->previewId = $fileId;

        if (! $this->previewItem?->isFileCompleted()) {
            $this->notifyWarning('File belum tersedia.');
            $this->closePreviewModal();

            return;
        }

        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->reset(['showPreviewModal', 'previewId']);
    }

    public function getPreviewItemProperty(): ?PengujianFile
    {
        if (! $this->previewId) {
            return null;
        }

        return PengujianFile::whereHas('pengujian', fn ($q) => $q->where('data_kapal_id', $this->dataKapal->id))
            ->with('pengujian')
            ->find($this->previewId);
    }

    public function getPreviewImageUrlProperty(): ?string
    {
        $item = $this->previewItem;

        return $item?->isPreviewable() ? route('pengujian-file.file', $item) : null;
    }

    /**
     * Dipanggil dari x-lampiran-preview-modal (downloadMethod).
     */
    public function downloadPreviewFile(int $id)
    {
        $this->authorize('viewAny', Pengujian::class);
        $item = $this->previewItem;

        if (! $item || $item->id !== $id || ! $item->file_path || ! Storage::disk()->exists($item->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return null;
        }

        return Storage::disk()->download($item->file_path, $item->file_name);
    }

    public function confirmDelete(int $id, string $entity = 'pengujian'): void
    {
        $service = app(DataProyekService::class);
        $kind = PengujianCatalog::tryFrom($entity);
        abort_unless($kind || in_array($entity, ['pengujian', 'file'], true), 404);
        $model = match (true) {
            (bool) $kind => $service->findPengujianCatalog($kind, $id),
            $entity === 'file' => $service->findPengujianFile($this->dataKapal, $id),
            default => $service->findPengujian($this->dataKapal, $id),
        };
        $this->authorize($entity === 'file' ? 'update' : 'delete', $model instanceof PengujianFile ? $model->pengujian : $model);
        $this->deletingId = $id;
        $this->deletingEntity = $entity;
        $this->showDeleteModal = true;
    }

    public function delete(DataProyekService $service): void
    {
        if (! $this->deletingId) {
            $this->notifyWarning('Pilih data yang akan dihapus terlebih dahulu.');

            return;
        }

        try {
            $kind = PengujianCatalog::tryFrom($this->deletingEntity);
            if ($kind) {
                $model = $service->findPengujianCatalog($kind, $this->deletingId);
                $this->authorize('delete', $model);
                $service->deletePengujianCatalog($kind, $model->id);
                if ($this->catalogEntity === $kind->value && $this->editingCatalogId === $model->id) {
                    $this->reset(['catalogName', 'editingCatalogId']);
                    $this->resetValidation();
                }
            } elseif ($this->deletingEntity === 'file') {
                $model = $service->findPengujianFile($this->dataKapal, $this->deletingId);
                $this->authorize('update', $model->pengujian);
                $service->deletePengujianFile($model);
            } else {
                $model = $service->findPengujian($this->dataKapal, $this->deletingId);
                $this->authorize('delete', $model);
                $service->deletePengujian($model);
                unset($this->drafts['row-'.$model->id], $this->pengujianUploads['row-'.$model->id]);
                $this->dispatch('pengujian-row-saved', key: 'row-'.$model->id);
            }
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifySuccess('Data berhasil dihapus.');
        } catch (ValidationException $e) {
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifyWarning(collect($e->errors())->flatten()->first());
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus data.');
        } catch (\Exception $e) {
            Log::error('Project pengujian delete failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    protected function rules(): array
    {
        return [
            'drafts.*.nama_pengujian' => 'required|string|max:255',
            'drafts.*.tanggal' => 'nullable|date',
            'drafts.*.pengujian_status_id' => ['required', 'integer', Rule::exists('pengujian_statuses', 'id')],
            'drafts.*.hasil' => 'nullable|string|max:255',
            'drafts.*.keterangan' => 'nullable|string|max:1000',
            'catalogName' => 'required|string|max:100',
        ];
    }

    protected function messages(): array
    {
        return DataProyekService::VALIDATION_MESSAGES;
    }

    public function validationAttributes(): array
    {
        return [
            'drafts.*.nama_pengujian' => 'nama pengujian',
            'drafts.*.tanggal' => 'tanggal',
            'drafts.*.pengujian_status_id' => 'status',
            'drafts.*.hasil' => 'hasil',
            'drafts.*.keterangan' => 'keterangan',
            'catalogName' => mb_strtolower(PengujianCatalog::from($this->catalogEntity)->label()),
        ];
    }

    public function render(DataProyekService $service)
    {
        $items = $service->getPengujians($this->dataKapal, $this->search, $this->perPage, [
            'status' => $this->statusFilter,
        ], $this->sortField, $this->sortDir);
        foreach ($items as $item) {
            if (! ($this->drafts['row-'.$item->id]['dirty'] ?? false)) {
                $this->drafts['row-'.$item->id] = [
                    'nama_pengujian' => $item->nama_pengujian,
                    'tanggal' => $item->tanggal?->toDateString(),
                    'pengujian_status_id' => $item->pengujian_status_id,
                    'hasil' => $item->hasil,
                    'keterangan' => $item->keterangan,
                    'dirty' => false,
                ];
            }
        }
        $visibleKeys = $items->map(fn ($item) => 'row-'.$item->id)->all();
        $rows = collect($this->drafts)->filter(fn ($row, $key) => str_starts_with($key, 'new-') || ($row['dirty'] ?? false) && ! in_array($key, $visibleKeys, true))->all();
        foreach ($items as $item) {
            $rows['row-'.$item->id] = $this->drafts['row-'.$item->id];
        }
        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$items->total()} data pengujian.");
            $this->filterChanged = false;
        }
        $statuses = $service->pengujianCatalogs(PengujianCatalog::Status);
        $kind = PengujianCatalog::from($this->catalogEntity);
        $this->drafts = array_intersect_key($this->drafts, $rows);

        return view('livewire.master-data.project-pengujian-management', [
            'items' => $items,
            'rows' => $rows,
            'hasProcessing' => $items->getCollection()->contains(fn ($item) => $item->files->contains(fn ($file) => $file->isFileProcessing())),
            'catalogKind' => $kind,
            'catalogKinds' => PengujianCatalog::cases(),
            'catalogItems' => $this->showCatalog ? $service->paginatePengujianCatalog($kind) : null,
            'statusOptions' => $statuses->map(fn ($s) => ['value' => $s->id, 'label' => $s->label()])->all(),
        ]);
    }
}
