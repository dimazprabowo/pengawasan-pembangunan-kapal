<?php

namespace App\Livewire\MasterData;

use App\Enums\DrawingCatalog;
use App\Enums\DrawingStatus;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use App\Services\DrawingRegisterService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class DrawingRegisterManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, HasTableSorting, WithPagination;

    #[Locked]
    public DataKapal $dataKapal;

    public string $contractNumber = '';

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $cutoffFilter = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    public array $drafts = [];

    public bool $showCatalog = false;

    #[Locked]
    public string $catalogEntity = 'type';

    public string $catalogName = '';

    #[Locked]
    public ?int $editingCatalogId = null;

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingId = null;

    #[Locked]
    public string $deletingEntity = 'revision';

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', DrawingRegister::class);
        $this->authorizeDataKapalAccess($dataKapal);
        $this->dataKapal = $dataKapal;
        $this->contractNumber = $dataKapal->nomor_kontrak_gambar ?? '';
        $this->sortField = 'nama';
    }

    public function boot(): void
    {
        if (isset($this->dataKapal)) {
            $this->authorize('viewAny', DrawingRegister::class);
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

    public function updatingTypeFilter(): void
    {
        $this->markFiltersChanged();
    }

    public function updatingCutoffFilter(): void
    {
        $this->markFiltersChanged();
    }

    public function updatingPerPage(): void
    {
        $this->markFiltersChanged();
    }

    private function markFiltersChanged(): void
    {
        $this->resetPage('drawingPage');
        $this->filterChanged = true;
    }

    public function warnUnsaved(): void
    {
        $this->notifyWarning('Simpan atau batalkan perubahan gambar sebelum berpindah halaman/tab.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'cutoffFilter', 'perPage']);
        $this->resetPage('drawingPage');
        $this->filterChanged = true;
    }

    public function addRow(DrawingRegisterService $service): void
    {
        $this->authorize('create', DrawingRegister::class);
        $this->drafts['new-'.Str::uuid()] = ['drawing_id' => null, 'nama' => '', 'drawing_type_id' => '', 'revisi' => 0, 'tanggal' => now()->toDateString(), 'drawing_status_id' => $service->defaultStatusId() ?? '', 'dirty' => true];
    }

    public function addRevision(int $revisionId, DrawingRegisterService $service): void
    {
        $revision = $service->findRevision($this->dataKapal, $revisionId);
        if ($revision->status && in_array($revision->status->nama, [DrawingStatus::InProgress->value, DrawingStatus::InProgress->label()], true)) {
            $this->notifyWarning('Gambar berstatus In Progress tidak dapat dibuat revisi baru.');

            return;
        }
        $drawing = $revision->drawing;
        $this->authorize('update', $drawing);
        $this->drafts['new-'.Str::uuid()] = ['drawing_id' => $drawing->id, 'nama' => $drawing->nama, 'drawing_type_id' => $drawing->drawing_type_id, 'revisi' => $service->nextRevisionNumber($drawing), 'tanggal' => now()->toDateString(), 'drawing_status_id' => $service->defaultStatusId() ?? '', 'dirty' => true];
    }

    public function cancelRow(string $key): void
    {
        $this->authorize('viewAny', DrawingRegister::class);
        unset($this->drafts[$key]);
        $this->dispatch('drawing-row-saved', key: $key);
        $this->resetValidation();
    }

    public function saveRow(string $key, DrawingRegisterService $service): void
    {
        try {
            if (! isset($this->drafts[$key])) {
                return;
            }
            $row = $this->drafts[$key];
            $revision = str_starts_with($key, 'row-') ? $service->findRevision($this->dataKapal, (int) substr($key, 4)) : null;
            $drawingId = $revision?->drawing_register_id ?? ($row['drawing_id'] ?? null);
            $this->authorize($drawingId ? 'update' : 'create', $drawingId ? $service->findDrawing($this->dataKapal, (int) $drawingId) : DrawingRegister::class);
            $rules = [];
            foreach ($this->rules() as $field => $rule) {
                if (str_starts_with($field, 'drafts.')) {
                    $rules[str_replace('.*.', '.'.$key.'.', $field)] = $rule;
                }
            }
            $this->validate($rules);
            $service->saveRevision($this->dataKapal, $row, $drawingId ? (int) $drawingId : null, $revision?->id);
            unset($this->drafts[$key]);
            $this->dispatch('drawing-row-saved', key: $key);
            $this->notifySuccess('Riwayat gambar berhasil disimpan.');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'drafts.') ? $field : "drafts.$key.$field"] = $errors;
            }
            $error = ValidationException::withMessages($messages);
            $this->notifyValidationError($error);
            throw $error;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menyimpan gambar.');
        } catch (\Exception $e) {
            Log::error('Drawing register save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function saveContract(DrawingRegisterService $service): void
    {
        $this->authorize('update', new DrawingRegister(['data_kapal_id' => $this->dataKapal->id]));
        try {
            $this->validate(['contractNumber' => 'nullable|string|max:150']);
            $service->saveContract($this->dataKapal, $this->contractNumber);
            $this->dispatch('drawing-row-saved', key: 'contract');
            $this->notifySuccess('Nomor kontrak gambar berhasil disimpan.');
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Drawing contract save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function manageCatalog(string $entity): void
    {
        $kind = DrawingCatalog::tryFrom($entity);
        abort_unless($kind, 404);
        $this->authorize('viewAny', $kind->modelClass());
        $this->catalogEntity = $kind->value;
        $this->resetPage('catalogPage');
        $this->showCatalog = true;
        $this->dispatch('drawing-catalog-opened');
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
        $this->authorize('create', DrawingCatalog::from($this->catalogEntity)->modelClass());
        $this->reset(['catalogName', 'editingCatalogId']);
        $this->resetValidation();
        $this->dispatch('drawing-catalog-focus');
    }

    public function editCatalog(int $id, DrawingRegisterService $service): void
    {
        $model = $service->findCatalog(DrawingCatalog::from($this->catalogEntity), $id);
        $this->authorize('update', $model);
        $this->editingCatalogId = $id;
        $this->catalogName = $model->label();
        $this->resetValidation();
        $this->dispatch('drawing-catalog-focus');
    }

    public function updatedCatalogName(): void
    {
        $this->validateOnly('catalogName');
    }

    public function saveCatalog(DrawingRegisterService $service): void
    {
        $kind = DrawingCatalog::from($this->catalogEntity);
        $this->authorize($this->editingCatalogId ? 'update' : 'create', $this->editingCatalogId ? $service->findCatalog($kind, $this->editingCatalogId) : $kind->modelClass());
        try {
            $this->validate(['catalogName' => 'required|string|max:100']);
            $service->saveCatalog($kind, $this->catalogName, $this->editingCatalogId);
            $this->reset(['catalogName', 'editingCatalogId']);
            $this->resetValidation();
            $this->notifySuccess($kind->label().' berhasil disimpan.');
        } catch (ValidationException $e) {
            $error = ValidationException::withMessages(['catalogName' => $e->errors()['catalogName'] ?? $e->errors()['nama'] ?? ['Nama tidak valid.']]);
            $this->notifyValidationError($error);
            throw $error;
        } catch (\Exception $e) {
            Log::error('Drawing catalog save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDelete(int $id, string $entity = 'revision'): void
    {
        $service = app(DrawingRegisterService::class);
        $kind = DrawingCatalog::tryFrom($entity);
        abort_unless($kind || $entity === 'revision', 404);
        $model = $kind ? $service->findCatalog($kind, $id) : $service->findRevision($this->dataKapal, $id);
        $this->authorize('delete', $kind ? $model : $model->drawing);
        $this->deletingId = $id;
        $this->deletingEntity = $entity;
        $this->showDeleteModal = true;
    }

    public function delete(DrawingRegisterService $service): void
    {
        if (! $this->deletingId) {
            $this->notifyWarning('Pilih data yang akan dihapus terlebih dahulu.');

            return;
        }

        try {
            $kind = DrawingCatalog::tryFrom($this->deletingEntity);
            $model = $kind ? $service->findCatalog($kind, $this->deletingId) : $service->findRevision($this->dataKapal, $this->deletingId);
            $this->authorize('delete', $kind ? $model : $model->drawing);
            if ($kind) {
                $service->deleteCatalog($kind, $model->id);
                if ($this->catalogEntity === $kind->value && $this->editingCatalogId === $model->id) {
                    $this->reset(['catalogName', 'editingCatalogId']);
                    $this->resetValidation();
                }
            } else {
                $service->deleteRevision($model);
                unset($this->drafts['row-'.$model->id]);
                $this->dispatch('drawing-row-saved', key: 'row-'.$model->id);
            }
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifySuccess('Data berhasil dihapus.');
        } catch (ValidationException $e) {
            $this->reset(['showDeleteModal', 'deletingId']);
            $this->notifyWarning(collect($e->errors())->flatten()->first());
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus data.');
        } catch (\Exception $e) {
            Log::error('Drawing delete failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    protected function rules(): array
    {
        return [
            'drafts.*.nama' => 'required|string|max:255',
            'drafts.*.drawing_type_id' => ['required', 'integer', Rule::exists('drawing_types', 'id')],
            'drafts.*.revisi' => 'required|integer|min:0|max:65535',
            'drafts.*.tanggal' => 'required|date_format:Y-m-d',
            'drafts.*.drawing_status_id' => ['required', 'integer', Rule::exists('drawing_statuses', 'id')],
            'contractNumber' => 'nullable|string|max:150',
            'catalogName' => 'required|string|max:100',
        ];
    }

    protected function messages(): array
    {
        return DrawingRegisterService::VALIDATION_MESSAGES;
    }

    public function validationAttributes(): array
    {
        return ['drafts.*.nama' => 'nama gambar', 'drafts.*.drawing_type_id' => 'jenis gambar', 'drafts.*.revisi' => 'revisi gambar', 'drafts.*.tanggal' => 'tanggal revisi', 'drafts.*.drawing_status_id' => 'status', 'contractNumber' => 'nomor kontrak gambar', 'catalogName' => mb_strtolower(DrawingCatalog::from($this->catalogEntity)->label())];
    }

    public function render(DrawingRegisterService $service)
    {
        $items = $service->getFiltered($this->dataKapal, $this->search, ['status' => $this->statusFilter, 'type' => $this->typeFilter, 'tanggal' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->cutoffFilter) ? $this->cutoffFilter : null], $this->perPage, $this->sortField, $this->sortDir);
        foreach ($items as $item) {
            if (! ($this->drafts['row-'.$item->id]['dirty'] ?? false)) {
                $this->drafts['row-'.$item->id] = ['drawing_id' => $item->drawing_register_id, 'nama' => $item->drawing->nama, 'drawing_type_id' => $item->drawing->drawing_type_id, 'revisi' => $item->revisi, 'tanggal' => $item->tanggal->toDateString(), 'drawing_status_id' => $item->drawing_status_id, 'dirty' => false];
            }
        }
        $visibleKeys = $items->map(fn ($item) => 'row-'.$item->id)->all();
        $rows = collect($this->drafts)->filter(fn ($row, $key) => str_starts_with($key, 'new-') || ($row['dirty'] ?? false) && ! in_array($key, $visibleKeys, true))->all();
        foreach ($items as $item) {
            $rows['row-'.$item->id] = $this->drafts['row-'.$item->id];
        }
        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$items->total()} riwayat gambar.");
            $this->filterChanged = false;
        }

        $types = $service->catalogs(DrawingCatalog::Type);
        $statuses = $service->catalogs(DrawingCatalog::Status);
        $kind = DrawingCatalog::from($this->catalogEntity);
        $this->drafts = array_intersect_key($this->drafts, $rows);

        return view('livewire.master-data.drawing-register-management', [
            'items' => $items, 'rows' => $rows,
            'catalogKind' => $kind,
            'catalogKinds' => DrawingCatalog::cases(),
            'catalogItems' => $this->showCatalog ? $service->paginateCatalog($kind) : null,
            'typeOptions' => $types->map(fn ($type) => ['value' => $type->id, 'label' => $type->nama])->all(),
            'statusOptions' => $statuses->map(fn ($status) => ['value' => $status->id, 'label' => $status->label()])->all(),
            'inProgressStatusId' => $statuses->first(fn ($status) => in_array($status->nama, [DrawingStatus::InProgress->value, DrawingStatus::InProgress->label()], true))?->id,
        ]);
    }
}
