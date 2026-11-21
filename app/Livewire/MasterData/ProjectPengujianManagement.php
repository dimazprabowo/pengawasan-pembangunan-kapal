<?php

namespace App\Livewire\MasterData;

use App\Enums\PengujianCatalog;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\Pengujian;
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

class ProjectPengujianManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, HasTableSorting, WithPagination;

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
        unset($this->drafts[$key]);
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
            $service->savePengujian($this->dataKapal, array_intersect_key($row, array_flip(['nama_pengujian', 'tanggal', 'pengujian_status_id', 'hasil', 'keterangan'])), $pengujian);
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

    public function confirmDelete(int $id, string $entity = 'pengujian'): void
    {
        $service = app(DataProyekService::class);
        $kind = PengujianCatalog::tryFrom($entity);
        abort_unless($kind || $entity === 'pengujian', 404);
        $model = $kind ? $service->findPengujianCatalog($kind, $id) : $service->findPengujian($this->dataKapal, $id);
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
            $kind = PengujianCatalog::tryFrom($this->deletingEntity);
            $model = $kind ? $service->findPengujianCatalog($kind, $this->deletingId) : $service->findPengujian($this->dataKapal, $this->deletingId);
            $this->authorize('delete', $model);
            if ($kind) {
                $service->deletePengujianCatalog($kind, $model->id);
                if ($this->catalogEntity === $kind->value && $this->editingCatalogId === $model->id) {
                    $this->reset(['catalogName', 'editingCatalogId']);
                    $this->resetValidation();
                }
            } else {
                $service->delete($model);
                unset($this->drafts['row-'.$model->id]);
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
            'catalogKind' => $kind,
            'catalogKinds' => PengujianCatalog::cases(),
            'catalogItems' => $this->showCatalog ? $service->paginatePengujianCatalog($kind) : null,
            'statusOptions' => $statuses->map(fn ($s) => ['value' => $s->id, 'label' => $s->label()])->all(),
        ]);
    }
}
