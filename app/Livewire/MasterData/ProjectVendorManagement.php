<?php

namespace App\Livewire\MasterData;

use App\Enums\VendorCatalog;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\ProjectVendor;
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

class ProjectVendorManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, HasTableSorting, WithPagination;

    #[Locked]
    public DataKapal $dataKapal;

    public string $search = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    public array $drafts = [];

    public bool $showCatalog = false;

    #[Locked]
    public string $catalogEntity = 'lingkup';

    public string $catalogName = '';

    #[Locked]
    public ?int $editingCatalogId = null;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingId = null;

    #[Locked]
    public string $deletingEntity = 'vendor';

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', ProjectVendor::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
        $this->sortField = 'nama';
    }

    public function boot(): void
    {
        if (isset($this->dataKapal)) {
            $this->authorize('viewAny', ProjectVendor::class);
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

    private function markFiltersChanged(): void
    {
        $this->resetPage('vendorPage');
        $this->filterChanged = true;
    }

    public function warnUnsaved(): void
    {
        $this->notifyWarning('Simpan atau batalkan perubahan vendor sebelum berpindah halaman/tab.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'perPage']);
        $this->resetPage('vendorPage');
        $this->filterChanged = true;
    }

    public function addRow(DataProyekService $service): void
    {
        $this->authorize('create', ProjectVendor::class);
        $this->drafts['new-'.Str::uuid()] = ['nama' => '', 'lingkup_id' => $service->defaultVendorLingkupId() ?? '', 'bulan_ke' => '', 'kontak' => '', 'keterangan' => '', 'dirty' => true];
    }

    public function cancelRow(string $key): void
    {
        $this->authorize('viewAny', ProjectVendor::class);
        unset($this->drafts[$key]);
        $this->dispatch('vendor-row-saved', key: $key);
        $this->resetValidation();
    }

    public function saveRow(string $key, DataProyekService $service): void
    {
        try {
            if (! isset($this->drafts[$key])) {
                return;
            }
            $row = $this->drafts[$key];
            $vendor = str_starts_with($key, 'row-') ? $service->findVendor($this->dataKapal, (int) substr($key, 4)) : null;
            $this->authorize($vendor ? 'update' : 'create', $vendor ?: ProjectVendor::class);
            $rules = [];
            foreach ($this->rules() as $field => $rule) {
                if (str_starts_with($field, 'drafts.')) {
                    $rules[str_replace('.*.', '.'.$key.'.', $field)] = $rule;
                }
            }
            $this->validate($rules);
            $service->saveVendor($this->dataKapal, array_intersect_key($row, array_flip(['nama', 'lingkup_id', 'bulan_ke', 'kontak', 'keterangan'])), $vendor);
            unset($this->drafts[$key]);
            $this->dispatch('vendor-row-saved', key: $key);
            $this->notifySuccess('Data vendor berhasil disimpan.');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'drafts.') ? $field : "drafts.$key.$field"] = $errors;
            }
            $error = ValidationException::withMessages($messages);
            $this->notifyValidationError($error);
            throw $error;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menyimpan vendor.');
        } catch (\Exception $e) {
            Log::error('Project vendor save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function manageCatalog(string $entity): void
    {
        $kind = VendorCatalog::tryFrom($entity);
        abort_unless($kind, 404);
        $this->authorize('viewAny', $kind->modelClass());
        $this->catalogEntity = $kind->value;
        $this->resetPage('catalogPage');
        $this->showCatalog = true;
        $this->dispatch('vendor-catalog-opened');
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
        $this->authorize('create', VendorCatalog::from($this->catalogEntity)->modelClass());
        $this->reset(['catalogName', 'editingCatalogId']);
        $this->resetValidation();
        $this->dispatch('vendor-catalog-focus');
    }

    public function editCatalog(int $id, DataProyekService $service): void
    {
        $model = $service->findVendorLingkup(VendorCatalog::from($this->catalogEntity), $id);
        $this->authorize('update', $model);
        $this->editingCatalogId = $id;
        $this->catalogName = $model->nama;
        $this->resetValidation();
        $this->dispatch('vendor-catalog-focus');
    }

    public function updatedCatalogName(): void
    {
        $this->validateOnly('catalogName');
    }

    public function saveCatalog(DataProyekService $service): void
    {
        $kind = VendorCatalog::from($this->catalogEntity);
        $this->authorize($this->editingCatalogId ? 'update' : 'create', $this->editingCatalogId ? $service->findVendorLingkup($kind, $this->editingCatalogId) : $kind->modelClass());
        try {
            $this->validate(['catalogName' => 'required|string|max:100']);
            $service->saveVendorLingkup($kind, $this->catalogName, $this->editingCatalogId);
            $this->reset(['catalogName', 'editingCatalogId']);
            $this->resetValidation();
            $this->notifySuccess($kind->label().' berhasil disimpan.');
        } catch (ValidationException $e) {
            $error = ValidationException::withMessages(['catalogName' => $e->errors()['catalogName'] ?? $e->errors()['nama'] ?? ['Nama tidak valid.']]);
            $this->notifyValidationError($error);
            throw $error;
        } catch (\Exception $e) {
            Log::error('Vendor catalog save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDelete(int $id, string $entity = 'vendor'): void
    {
        $service = app(DataProyekService::class);
        $kind = VendorCatalog::tryFrom($entity);
        abort_unless($kind || $entity === 'vendor', 404);
        $model = $kind ? $service->findVendorLingkup($kind, $id) : $service->findVendor($this->dataKapal, $id);
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
            $kind = VendorCatalog::tryFrom($this->deletingEntity);
            $model = $kind ? $service->findVendorLingkup($kind, $this->deletingId) : $service->findVendor($this->dataKapal, $this->deletingId);
            $this->authorize('delete', $model);
            if ($kind) {
                $service->deleteVendorLingkup($kind, $model->id);
                if ($this->catalogEntity === $kind->value && $this->editingCatalogId === $model->id) {
                    $this->reset(['catalogName', 'editingCatalogId']);
                    $this->resetValidation();
                }
            } else {
                $service->delete($model);
                unset($this->drafts['row-'.$model->id]);
                $this->dispatch('vendor-row-saved', key: 'row-'.$model->id);
            }
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifySuccess('Data berhasil dihapus.');
        } catch (ValidationException $e) {
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifyWarning(collect($e->errors())->flatten()->first());
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus data.');
        } catch (\Exception $e) {
            Log::error('Project vendor delete failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    protected function rules(): array
    {
        return [
            'drafts.*.nama' => 'required|string|max:255',
            'drafts.*.lingkup_id' => ['required', 'integer', Rule::exists('vendor_lingkups', 'id')],
            'drafts.*.bulan_ke' => ['required', 'integer', Rule::in(app(DataProyekService::class)->getBulanKeIds($this->dataKapal))],
            'drafts.*.kontak' => 'nullable|string|max:255',
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
            'drafts.*.nama' => 'nama vendor',
            'drafts.*.lingkup_id' => 'lingkup pekerjaan',
            'drafts.*.bulan_ke' => 'bulan ke-',
            'drafts.*.kontak' => 'kontak',
            'drafts.*.keterangan' => 'keterangan',
            'catalogName' => mb_strtolower(VendorCatalog::from($this->catalogEntity)->label()),
        ];
    }

    public function render(DataProyekService $service)
    {
        $items = $service->getVendors($this->dataKapal, $this->search, $this->perPage, $this->sortField, $this->sortDir);
        foreach ($items as $item) {
            if (! ($this->drafts['row-'.$item->id]['dirty'] ?? false)) {
                $this->drafts['row-'.$item->id] = ['nama' => $item->nama, 'lingkup_id' => $item->lingkup_id, 'bulan_ke' => $item->bulan_ke, 'kontak' => $item->kontak, 'keterangan' => $item->keterangan, 'dirty' => false];
            }
        }
        $visibleKeys = $items->map(fn ($item) => 'row-'.$item->id)->all();
        $rows = collect($this->drafts)->filter(fn ($row, $key) => str_starts_with($key, 'new-') || ($row['dirty'] ?? false) && ! in_array($key, $visibleKeys, true))->all();
        foreach ($items as $item) {
            $rows['row-'.$item->id] = $this->drafts['row-'.$item->id];
        }
        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$items->total()} data vendor.");
            $this->filterChanged = false;
        }
        $lingkups = $service->vendorLingkups(VendorCatalog::Lingkup);
        $kind = VendorCatalog::from($this->catalogEntity);
        $this->drafts = array_intersect_key($this->drafts, $rows);

        return view('livewire.master-data.project-vendor-management', [
            'items' => $items,
            'rows' => $rows,
            'bulanOptions' => $service->getBulanKeOptions($this->dataKapal),
            'catalogKind' => $kind,
            'catalogKinds' => VendorCatalog::cases(),
            'catalogItems' => $this->showCatalog ? $service->paginateVendorLingkup($kind) : null,
            'lingkupOptions' => $lingkups->map(fn ($l) => ['value' => $l->id, 'label' => $l->label()])->all(),
        ]);
    }
}
