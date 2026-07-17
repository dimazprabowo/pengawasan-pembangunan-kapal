<?php

namespace App\Livewire\Settings;

use App\Enums\ConfigCategory;
use App\Enums\ConfigDataType;
use App\Exports\SystemConfigurationsExport;
use App\Livewire\Traits\HasNotification;
use App\Models\SystemConfiguration as SystemConfigModel;
use App\Services\SystemConfigurationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class SystemConfiguration extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $isActiveFilter = '';
    public $showModal = false;
    public $editMode = false;
    public bool $filterChanged = false;
    
    // Form fields
    public $configId;
    public $key;
    public $category = 'general';
    public $value;
    public $data_type = 'string';
    public $description;
    public $is_editable = true;
    public $is_active = true;

    public function mount()
    {
        $this->authorize('viewAny', SystemConfigModel::class);
    }

    public function rules()
    {
        $rules = [
            'key' => ['required', 'string', 'max:100', $this->editMode ? 'unique:system_configurations,key,' . $this->configId : 'unique:system_configurations,key'],
            'category' => ['required', 'string', Rule::in(ConfigCategory::values())],
            'data_type' => ['required', 'string', Rule::in(ConfigDataType::values())],
            'description' => 'nullable|string',
            'is_editable' => 'boolean',
            'is_active' => 'boolean',
        ];

        if ($this->data_type !== 'datetime') {
            $rules['value'] = 'required';
        } else {
            $rules['value'] = 'nullable';
        }

        return $rules;
    }

    public function updatingSearch()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingIsActiveFilter()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'isActiveFilter']);
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function getIsActiveOptionsProperty(): array
    {
        return [
            ['value' => '1', 'label' => 'Aktif'],
            ['value' => '0', 'label' => 'Nonaktif'],
        ];
    }

    public function edit($id)
    {
        $config = SystemConfigModel::findOrFail($id);
        $this->authorize('update', $config);
        
        $this->configId = $config->id;
        $this->key = $config->key;
        $this->category = $config->category instanceof ConfigCategory ? $config->category->value : $config->category;
        $this->value = $config->value;
        $this->data_type = $config->data_type instanceof ConfigDataType ? $config->data_type->value : $config->data_type;
        $this->description = $config->description;
        $this->is_editable = $config->is_editable;
        $this->is_active = $config->is_active;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(SystemConfigurationService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $value = $this->value;
            if ($this->data_type === 'datetime' && empty($value)) {
                $value = null;
            }

            $data = [
                'key' => $this->key,
                'category' => $this->category,
                'value' => $value,
                'data_type' => $this->data_type,
                'description' => $this->description,
                'is_editable' => $this->is_editable,
                'is_active' => $this->is_active,
            ];

            $config = SystemConfigModel::findOrFail($this->configId);
            $this->authorize('update', $config);

            $service->update($config, $data);
            $message = 'Konfigurasi berhasil diupdate!';

            $this->notifySuccess($message);
            $this->closeModal();
            $this->resetForm();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleActive($id, SystemConfigurationService $service)
    {
        try {
            $config = SystemConfigModel::findOrFail($id);
            $this->authorize('toggleActive', $config);

            $service->toggleActive($config);

            $status = $config->is_active ? 'diaktifkan' : 'dinonaktifkan';
            $this->notifySuccess("Konfigurasi berhasil {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status konfigurasi.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset([
            'configId',
            'key',
            'category',
            'value',
            'data_type',
            'description',
            'is_editable',
            'is_active',
        ]);
        
        $this->category = ConfigCategory::General->value;
        $this->data_type = ConfigDataType::String->value;
        $this->is_editable = true;
        $this->is_active = true;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', SystemConfigModel::class);

        return (new SystemConfigurationsExport($this->search, $this->isActiveFilter))
            ->download('konfigurasi-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(SystemConfigurationService $service)
    {
        $this->authorize('exportPdf', SystemConfigModel::class);

        $query = SystemConfigModel::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
                  ->orWhere('value', 'like', "%{$this->search}%");
            });
        }

        if ($this->isActiveFilter !== null && $this->isActiveFilter !== '') {
            $query->where('is_active', $this->isActiveFilter === '1');
        }

        $configurations = $query->orderBy('category')->orderBy('key')->get();

        $pdf = Pdf::loadView('exports.configurations-pdf', ['configurations' => $configurations]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'konfigurasi-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function render(SystemConfigurationService $service)
    {
        $configurations = $service->getFiltered($this->search, $this->isActiveFilter);

        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$configurations->total()} konfigurasi.");
            $this->filterChanged = false;
        }

        return view('livewire.settings.system-configuration', [
            'configurations' => $configurations,
            'categories' => ConfigCategory::options(),
            'dataTypes' => ConfigDataType::options(),
        ]);
    }
}
