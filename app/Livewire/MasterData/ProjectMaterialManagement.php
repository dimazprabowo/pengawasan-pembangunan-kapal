<?php

namespace App\Livewire\MasterData;

use App\Enums\MaterialCatalog;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\ProjectMaterial;
use App\Services\DataProyekService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectMaterialManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, HasTableSorting, WithPagination;

    #[Locked]
    public DataKapal $dataKapal;

    public string $search = '';

    public string $kategoriFilter = '';

    public string $statusFilter = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    public array $drafts = [];

    public bool $showCatalog = false;

    #[Locked]
    public string $catalogEntity = 'category';

    public string $catalogName = '';

    #[Locked]
    public ?int $editingCatalogId = null;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingId = null;

    #[Locked]
    public string $deletingEntity = 'material';

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', ProjectMaterial::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
        $this->sortField = 'nama_item';
    }

    public function boot(): void
    {
        if (isset($this->dataKapal)) {
            $this->authorize('viewAny', ProjectMaterial::class);
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

    public function updatingKategoriFilter(): void
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
        $this->resetPage('materialPage');
        $this->filterChanged = true;
    }

    public function warnUnsaved(): void
    {
        $this->notifyWarning('Simpan atau batalkan perubahan material sebelum berpindah halaman/tab.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'kategoriFilter', 'statusFilter', 'perPage']);
        $this->resetPage('materialPage');
        $this->filterChanged = true;
    }

    public function addRow(DataProyekService $service): void
    {
        $this->authorize('create', ProjectMaterial::class);
        $this->drafts['new-'.Str::uuid()] = [
            'material_category_id' => '',
            'nama_item' => '',
            'spesifikasi' => '',
            'jumlah' => '',
            'satuan' => '',
            'material_status_id' => $service->defaultMaterialStatusId() ?? '',
            'tanggal' => '',
            'keterangan' => '',
            'dirty' => true,
        ];
    }

    public function cancelRow(string $key): void
    {
        $this->authorize('viewAny', ProjectMaterial::class);
        unset($this->drafts[$key]);
        $this->dispatch('material-row-saved', key: $key);
        $this->resetValidation();
    }

    public function saveRow(string $key, DataProyekService $service): void
    {
        try {
            if (! isset($this->drafts[$key])) {
                return;
            }
            $row = $this->drafts[$key];
            $material = str_starts_with($key, 'row-') ? $service->findMaterial($this->dataKapal, (int) substr($key, 4)) : null;
            $this->authorize($material ? 'update' : 'create', $material ?: ProjectMaterial::class);
            $rules = [];
            foreach ($this->rules() as $field => $rule) {
                if (str_starts_with($field, 'drafts.')) {
                    $rules[str_replace('.*.', '.'.$key.'.', $field)] = $rule;
                }
            }
            $this->validate($rules);
            $service->saveMaterial($this->dataKapal, array_intersect_key($row, array_flip(['material_category_id', 'nama_item', 'spesifikasi', 'jumlah', 'satuan', 'material_status_id', 'tanggal', 'keterangan'])), $material);
            unset($this->drafts[$key]);
            $this->dispatch('material-row-saved', key: $key);
            $this->notifySuccess('Data material berhasil disimpan.');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'drafts.') ? $field : "drafts.$key.$field"] = $errors;
            }
            $error = ValidationException::withMessages($messages);
            $this->notifyValidationError($error);
            throw $error;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menyimpan material.');
        } catch (\Exception $e) {
            Log::error('Project material save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function manageCatalog(string $entity): void
    {
        $kind = MaterialCatalog::tryFrom($entity);
        abort_unless($kind, 404);
        $this->authorize('viewAny', $kind->modelClass());
        $this->catalogEntity = $kind->value;
        $this->resetPage('catalogPage');
        $this->showCatalog = true;
        $this->dispatch('material-catalog-opened');
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
        $this->authorize('create', MaterialCatalog::from($this->catalogEntity)->modelClass());
        $this->reset(['catalogName', 'editingCatalogId']);
        $this->resetValidation();
        $this->dispatch('material-catalog-focus');
    }

    public function editCatalog(int $id, DataProyekService $service): void
    {
        $model = $service->findMaterialCatalog(MaterialCatalog::from($this->catalogEntity), $id);
        $this->authorize('update', $model);
        $this->editingCatalogId = $id;
        $this->catalogName = $model->label();
        $this->resetValidation();
        $this->dispatch('material-catalog-focus');
    }

    public function updatedCatalogName(): void
    {
        $this->validateOnly('catalogName');
    }

    public function saveCatalog(DataProyekService $service): void
    {
        $kind = MaterialCatalog::from($this->catalogEntity);
        $this->authorize($this->editingCatalogId ? 'update' : 'create', $this->editingCatalogId ? $service->findMaterialCatalog($kind, $this->editingCatalogId) : $kind->modelClass());
        try {
            $this->validate(['catalogName' => 'required|string|max:100']);
            $service->saveMaterialCatalog($kind, $this->catalogName, $this->editingCatalogId);
            $this->reset(['catalogName', 'editingCatalogId']);
            $this->resetValidation();
            $this->notifySuccess($kind->label().' berhasil disimpan.');
        } catch (ValidationException $e) {
            $error = ValidationException::withMessages(['catalogName' => $e->errors()['catalogName'] ?? $e->errors()['nama'] ?? ['Nama tidak valid.']]);
            $this->notifyValidationError($error);
            throw $error;
        } catch (\Exception $e) {
            Log::error('Material catalog save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDelete(int $id, string $entity = 'material'): void
    {
        $service = app(DataProyekService::class);
        $kind = MaterialCatalog::tryFrom($entity);
        abort_unless($kind || $entity === 'material', 404);
        $model = $kind ? $service->findMaterialCatalog($kind, $id) : $service->findMaterial($this->dataKapal, $id);
        $this->authorize('delete', $model);
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
            $kind = MaterialCatalog::tryFrom($this->deletingEntity);
            $model = $kind ? $service->findMaterialCatalog($kind, $this->deletingId) : $service->findMaterial($this->dataKapal, $this->deletingId);
            $this->authorize('delete', $model);
            if ($kind) {
                $service->deleteMaterialCatalog($kind, $model->id);
                if ($this->catalogEntity === $kind->value && $this->editingCatalogId === $model->id) {
                    $this->reset(['catalogName', 'editingCatalogId']);
                    $this->resetValidation();
                }
            } else {
                $service->delete($model);
                unset($this->drafts['row-'.$model->id]);
                $this->dispatch('material-row-saved', key: 'row-'.$model->id);
            }
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifySuccess('Data berhasil dihapus.');
        } catch (ValidationException $e) {
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifyWarning(collect($e->errors())->flatten()->first());
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus data.');
        } catch (\Exception $e) {
            Log::error('Project material delete failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    protected function rules(): array
    {
        return [
            'drafts.*.material_category_id' => ['required', 'integer', Rule::exists('material_categories', 'id')],
            'drafts.*.nama_item' => 'required|string|max:255',
            'drafts.*.spesifikasi' => 'nullable|string|max:255',
            'drafts.*.jumlah' => 'nullable|numeric|min:0',
            'drafts.*.satuan' => 'nullable|string|max:50',
            'drafts.*.material_status_id' => ['required', 'integer', Rule::exists('material_statuses', 'id')],
            'drafts.*.tanggal' => 'nullable|date',
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
            'drafts.*.material_category_id' => 'kategori',
            'drafts.*.nama_item' => 'nama item',
            'drafts.*.spesifikasi' => 'spesifikasi',
            'drafts.*.jumlah' => 'jumlah',
            'drafts.*.satuan' => 'satuan',
            'drafts.*.material_status_id' => 'status',
            'drafts.*.tanggal' => 'tanggal',
            'drafts.*.keterangan' => 'keterangan',
            'catalogName' => mb_strtolower(MaterialCatalog::from($this->catalogEntity)->label()),
        ];
    }

    public function render(DataProyekService $service)
    {
        $items = $service->getMaterials($this->dataKapal, $this->search, $this->perPage, [
            'kategori' => $this->kategoriFilter,
            'status' => $this->statusFilter,
        ], $this->sortField, $this->sortDir);
        foreach ($items as $item) {
            if (! ($this->drafts['row-'.$item->id]['dirty'] ?? false)) {
                $this->drafts['row-'.$item->id] = [
                    'material_category_id' => $item->material_category_id,
                    'nama_item' => $item->nama_item,
                    'spesifikasi' => $item->spesifikasi,
                    'jumlah' => $item->jumlah,
                    'satuan' => $item->satuan,
                    'material_status_id' => $item->material_status_id,
                    'tanggal' => $item->tanggal?->toDateString(),
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
            $this->notifyInfo("Ditemukan {$items->total()} data material.");
            $this->filterChanged = false;
        }
        $categories = $service->materialCatalogs(MaterialCatalog::Category);
        $statuses = $service->materialCatalogs(MaterialCatalog::Status);
        $kind = MaterialCatalog::from($this->catalogEntity);
        $this->drafts = array_intersect_key($this->drafts, $rows);

        return view('livewire.master-data.project-material-management', [
            'items' => $items,
            'rows' => $rows,
            'catalogKind' => $kind,
            'catalogKinds' => MaterialCatalog::cases(),
            'catalogItems' => $this->showCatalog ? $service->paginateMaterialCatalog($kind) : null,
            'categoryOptions' => $categories->map(fn ($c) => ['value' => $c->id, 'label' => $c->label()])->all(),
            'statusOptions' => $statuses->map(fn ($s) => ['value' => $s->id, 'label' => $s->label()])->all(),
        ]);
    }
}
