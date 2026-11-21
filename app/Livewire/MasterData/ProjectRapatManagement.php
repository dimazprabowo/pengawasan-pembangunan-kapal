<?php

namespace App\Livewire\MasterData;

use App\Enums\RapatCatalog;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\Rapat;
use App\Models\RapatFile;
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

class ProjectRapatManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, HasTableSorting, WithFileUploads, WithPagination;

    #[Locked]
    public DataKapal $dataKapal;

    public string $search = '';

    public int $perPage = 10;

    public string $jenisFilter = '';

    public bool $filterChanged = false;

    public array $drafts = [];

    public bool $showCatalog = false;

    #[Locked]
    public string $catalogEntity = 'jenis';

    public string $catalogName = '';

    #[Locked]
    public ?int $editingCatalogId = null;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingId = null;

    #[Locked]
    public string $deletingEntity = 'rapat';

    // Lampiran rapat: upload pending per baris (key = row key drafts)
    public array $rapatUploads = [];

    public bool $showCropperModal = false;

    public ?string $croppingImageUrl = null;

    public ?string $croppingFileKey = null;

    public array $cropData = [];

    public bool $showPreviewModal = false;

    #[Locked]
    public ?int $previewId = null;

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', Rapat::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
        $this->sortField = 'tanggal';
        $this->sortDir = 'desc';
    }

    public function boot(): void
    {
        if (isset($this->dataKapal)) {
            $this->authorize('viewAny', Rapat::class);
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

    public function updatingPerPage(): void
    {
        $this->markFiltersChanged();
    }

    public function updatingJenisFilter(): void
    {
        $this->markFiltersChanged();
    }

    private function markFiltersChanged(): void
    {
        $this->resetPage('rapatPage');
        $this->filterChanged = true;
    }

    public function warnUnsaved(): void
    {
        $this->notifyWarning('Simpan atau batalkan perubahan rapat sebelum berpindah halaman/tab.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'perPage', 'jenisFilter']);
        $this->resetPage('rapatPage');
        $this->filterChanged = true;
    }

    public function addRow(DataProyekService $service): void
    {
        $this->authorize('create', Rapat::class);
        $this->drafts['new-'.Str::uuid()] = ['tanggal' => now()->format('Y-m-d'), 'rapat_jenis_id' => $service->defaultRapatJenisId() ?? '', 'lokasi' => '', 'topik' => '', 'dirty' => true];
    }

    public function cancelRow(string $key): void
    {
        $this->authorize('viewAny', Rapat::class);
        unset($this->drafts[$key], $this->rapatUploads[$key]);
        $this->dispatch('rapat-row-saved', key: $key);
        $this->resetValidation();
    }

    public function saveRow(string $key, DataProyekService $service): void
    {
        try {
            if (! isset($this->drafts[$key])) {
                return;
            }
            $row = $this->drafts[$key];
            $rapat = str_starts_with($key, 'row-') ? $service->findRapat($this->dataKapal, (int) substr($key, 4)) : null;
            $this->authorize($rapat ? 'update' : 'create', $rapat ?: Rapat::class);
            $rules = [];
            foreach ($this->rules() as $field => $rule) {
                if (str_starts_with($field, 'drafts.')) {
                    $rules[str_replace('.*.', '.'.$key.'.', $field)] = $rule;
                }
            }
            $this->validate($rules);
            $rapat = $service->saveRapat($this->dataKapal, array_intersect_key($row, array_flip(['tanggal', 'rapat_jenis_id', 'lokasi', 'topik'])), $rapat);
            // File lampiran yang diunggah saat baris masih draft ikut diproses
            // setelah rapat tersimpan (diproses async oleh ProcessRapatFile).
            $pendingFiles = $this->rapatUploads[$key] ?? [];
            if ($pendingFiles) {
                $prefix = 'rapatUploads.'.$key;
                $this->validate([$prefix.'.*.file' => file_upload_validation_rule('rapat_file', true)], [], [$prefix.'.*.file' => 'file rapat']);
                $service->addRapatFiles($rapat, $pendingFiles);
                unset($this->rapatUploads[$key]);
            }
            unset($this->drafts[$key]);
            $this->dispatch('rapat-row-saved', key: $key);
            $this->notifySuccess('Data rapat berhasil disimpan.');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'drafts.') ? $field : "drafts.$key.$field"] = $errors;
            }
            $error = ValidationException::withMessages($messages);
            $this->notifyValidationError($error);
            throw $error;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menyimpan rapat.');
        } catch (\Exception $e) {
            Log::error('Project rapat save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function manageCatalog(string $entity): void
    {
        $kind = RapatCatalog::tryFrom($entity);
        abort_unless($kind, 404);
        $this->authorize('viewAny', $kind->modelClass());
        $this->catalogEntity = $kind->value;
        $this->resetPage('catalogPage');
        $this->showCatalog = true;
        $this->dispatch('rapat-catalog-opened');
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
        $this->authorize('create', RapatCatalog::from($this->catalogEntity)->modelClass());
        $this->reset(['catalogName', 'editingCatalogId']);
        $this->resetValidation();
        $this->dispatch('rapat-catalog-focus');
    }

    public function editCatalog(int $id, DataProyekService $service): void
    {
        $model = $service->findRapatCatalog(RapatCatalog::from($this->catalogEntity), $id);
        $this->authorize('update', $model);
        $this->editingCatalogId = $id;
        $this->catalogName = $model->label();
        $this->resetValidation();
        $this->dispatch('rapat-catalog-focus');
    }

    public function updatedCatalogName(): void
    {
        $this->validateOnly('catalogName');
    }

    public function saveCatalog(DataProyekService $service): void
    {
        $kind = RapatCatalog::from($this->catalogEntity);
        $this->authorize($this->editingCatalogId ? 'update' : 'create', $this->editingCatalogId ? $service->findRapatCatalog($kind, $this->editingCatalogId) : $kind->modelClass());
        try {
            $this->validate(['catalogName' => 'required|string|max:100']);
            $service->saveRapatCatalog($kind, $this->catalogName, $this->editingCatalogId);
            $this->reset(['catalogName', 'editingCatalogId']);
            $this->resetValidation();
            $this->notifySuccess($kind->label().' berhasil disimpan.');
        } catch (ValidationException $e) {
            $error = ValidationException::withMessages(['catalogName' => $e->errors()['catalogName'] ?? $e->errors()['nama'] ?? ['Nama tidak valid.']]);
            $this->notifyValidationError($error);
            throw $error;
        } catch (\Exception $e) {
            Log::error('Rapat catalog save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    // ── Lampiran file (pola kolom file NC — satu kolom, banyak file) ─────

    /**
     * Simpan file yang sudah diupload pada baris rapat tersimpan.
     * Baris draft tidak memanggil ini — filenya ikut diproses di saveRow().
     */
    public function uploadRapatFiles(string $key, DataProyekService $service): void
    {
        try {
            abort_unless(str_starts_with($key, 'row-'), 404);
            $rapat = $service->findRapat($this->dataKapal, (int) substr($key, 4));
            $this->authorize('update', $rapat);
            $prefix = 'rapatUploads.'.$key;
            $this->validate([
                $prefix => ['required', 'array', 'min:1'],
                $prefix.'.*.file' => file_upload_validation_rule('rapat_file', true),
            ], [], [$prefix => 'file rapat', $prefix.'.*.file' => 'file rapat']);
            $service->addRapatFiles($rapat, $this->rapatUploads[$key] ?? []);
            unset($this->rapatUploads[$key]);
            $this->notifySuccess('File rapat ditambahkan dan sedang diproses.');
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk menambah file rapat.');
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Exception $e) {
            Log::error('ProjectRapatManagement: upload file gagal', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    /**
     * Hapus satu file dari antrian upload (sebelum disimpan).
     */
    public function removeRapatUpload(string $key, string $uploadKey): void
    {
        try {
            $this->authorize(str_starts_with($key, 'new-') ? 'proyek_data_create' : 'proyek_data_update');
            $entry = $this->rapatUploads[$key][$uploadKey] ?? null;
            if (is_object($entry['file'] ?? null)) {
                $entry['file']->delete();
            }
            unset($this->rapatUploads[$key][$uploadKey]);
        } catch (AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah file rapat.');
        }
    }

    public function openRapatFileCropper(string $key, string $uploadKey): void
    {
        $this->authorize('viewAny', Rapat::class);
        $path = $key.'.'.$uploadKey;
        $entry = data_get($this->rapatUploads, $path);
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
            data_set($this->rapatUploads, $this->croppingFileKey.'.cropData', $this->cropData);
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
        $this->authorize('viewAny', Rapat::class);
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

    public function getPreviewItemProperty(): ?RapatFile
    {
        if (! $this->previewId) {
            return null;
        }

        return RapatFile::whereHas('rapat', fn ($q) => $q->where('data_kapal_id', $this->dataKapal->id))
            ->with('rapat')
            ->find($this->previewId);
    }

    public function getPreviewImageUrlProperty(): ?string
    {
        $item = $this->previewItem;

        return $item?->isPreviewable() ? route('rapat-file.file', $item) : null;
    }

    /**
     * Dipanggil dari x-lampiran-preview-modal (downloadMethod).
     */
    public function downloadPreviewFile(int $id)
    {
        $this->authorize('viewAny', Rapat::class);
        $item = $this->previewItem;

        if (! $item || $item->id !== $id || ! $item->file_path || ! Storage::disk()->exists($item->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return null;
        }

        return Storage::disk()->download($item->file_path, $item->file_name);
    }

    public function confirmDelete(int $id, string $entity = 'rapat'): void
    {
        $service = app(DataProyekService::class);
        $kind = RapatCatalog::tryFrom($entity);
        abort_unless($kind || in_array($entity, ['rapat', 'file'], true), 404);
        $model = match (true) {
            (bool) $kind => $service->findRapatCatalog($kind, $id),
            $entity === 'file' => $service->findRapatFile($this->dataKapal, $id),
            default => $service->findRapat($this->dataKapal, $id),
        };
        $this->authorize($entity === 'file' ? 'update' : 'delete', $model instanceof RapatFile ? $model->rapat : $model);
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
            $kind = RapatCatalog::tryFrom($this->deletingEntity);
            if ($kind) {
                $model = $service->findRapatCatalog($kind, $this->deletingId);
                $this->authorize('delete', $model);
                $service->deleteRapatCatalog($kind, $model->id);
                if ($this->catalogEntity === $kind->value && $this->editingCatalogId === $model->id) {
                    $this->reset(['catalogName', 'editingCatalogId']);
                    $this->resetValidation();
                }
            } elseif ($this->deletingEntity === 'file') {
                $model = $service->findRapatFile($this->dataKapal, $this->deletingId);
                $this->authorize('update', $model->rapat);
                $service->deleteRapatFile($model);
            } else {
                $model = $service->findRapat($this->dataKapal, $this->deletingId);
                $this->authorize('delete', $model);
                $service->deleteRapat($model);
                unset($this->drafts['row-'.$model->id], $this->rapatUploads['row-'.$model->id]);
                $this->dispatch('rapat-row-saved', key: 'row-'.$model->id);
            }
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifySuccess('Data berhasil dihapus.');
        } catch (ValidationException $e) {
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifyWarning(collect($e->errors())->flatten()->first());
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus data.');
        } catch (\Exception $e) {
            Log::error('Project rapat delete failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    protected function rules(): array
    {
        return [
            'drafts.*.tanggal' => 'required|date',
            'drafts.*.rapat_jenis_id' => ['required', 'integer', Rule::exists('rapat_jenis', 'id')],
            'drafts.*.lokasi' => 'nullable|string|max:255',
            'drafts.*.topik' => 'nullable|string|max:255',
            'catalogName' => 'required|string|max:100',
        ];
    }

    protected function messages(): array
    {
        return DataProyekService::VALIDATION_MESSAGES;
    }

    public function validationAttributes(): array
    {
        return ['drafts.*.tanggal' => 'tanggal rapat', 'drafts.*.rapat_jenis_id' => 'jenis rapat', 'drafts.*.lokasi' => 'lokasi rapat', 'drafts.*.topik' => 'pokok pembahasan', 'catalogName' => mb_strtolower(RapatCatalog::from($this->catalogEntity)->label())];
    }

    public function render(DataProyekService $service)
    {
        $items = $service->getRapats($this->dataKapal, $this->search, $this->perPage, [
            'jenis' => $this->jenisFilter,
        ], $this->sortField, $this->sortDir);
        foreach ($items as $item) {
            if (! ($this->drafts['row-'.$item->id]['dirty'] ?? false)) {
                $this->drafts['row-'.$item->id] = [
                    'tanggal' => $item->tanggal?->format('Y-m-d'),
                    'rapat_jenis_id' => $item->rapat_jenis_id,
                    'lokasi' => $item->lokasi,
                    'topik' => $item->topik,
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
            $this->notifyInfo("Ditemukan {$items->total()} data rapat.");
            $this->filterChanged = false;
        }
        $jenis = $service->rapatCatalogs(RapatCatalog::Jenis);
        $kind = RapatCatalog::from($this->catalogEntity);
        $this->drafts = array_intersect_key($this->drafts, $rows);

        return view('livewire.master-data.project-rapat-management', [
            'items' => $items,
            'rows' => $rows,
            'catalogKind' => $kind,
            'catalogKinds' => RapatCatalog::cases(),
            'catalogItems' => $this->showCatalog ? $service->paginateRapatCatalog($kind) : null,
            'jenisOptions' => $jenis->map(fn ($j) => ['value' => $j->id, 'label' => $j->label()])->all(),
        ]);
    }
}
