<?php

namespace App\Livewire\MasterData;

use App\Enums\CompanyStatus;
use App\Exports\CompaniesExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use App\Services\CompanyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class CompanyManagement extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $statusFilter = '';
    public int $perPage = 10;
    public $showModal = false;
    public $editMode = false;

    // Form fields
    public $companyId;
    public $code;
    public $name;
    public $email;
    public $phone;
    public $address;
    public $pic_name;
    public $pic_email;
    public $pic_phone;
    public $status = 'active';
    
    // Delete Modal
    public $showDeleteModal = false;
    public $deletingCompanyId;
    public $deletingCompanyName;

    public function mount()
    {
        $this->authorize('viewAny', Company::class);
    }

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'max:50', $this->editMode ? 'unique:companies,code,' . $this->companyId : 'unique:companies,code'],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'pic_name' => 'nullable|string|max:255',
            'pic_email' => 'nullable|email|max:255',
            'pic_phone' => 'nullable|string|max:20',
            'status' => ['required', 'string', 'in:' . implode(',', CompanyStatus::values())],
        ];
    }

    public function validationAttributes()
    {
        return [
            'code' => 'kode perusahaan',
            'name' => 'nama perusahaan',
            'email' => 'email perusahaan',
            'phone' => 'telepon perusahaan',
            'address' => 'alamat',
            'pic_name' => 'nama PIC',
            'pic_email' => 'email PIC',
            'pic_phone' => 'telepon PIC',
            'status' => 'status',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->authorize('create', Company::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $this->companyId = $company->id;
        $this->code = $company->code;
        $this->name = $company->name;
        $this->email = $company->email;
        $this->phone = $company->phone;
        $this->address = $company->address;
        $this->pic_name = $company->pic_name;
        $this->pic_email = $company->pic_email;
        $this->pic_phone = $company->pic_phone;
        $this->status = $company->status->value;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(CompanyService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'code' => strtoupper($this->code),
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'pic_name' => $this->pic_name,
                'pic_email' => $this->pic_email,
                'pic_phone' => $this->pic_phone,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $company = Company::findOrFail($this->companyId);
                $this->authorize('update', $company);

                $service->update($company, $data);
                $message = 'Perusahaan berhasil diupdate!';
            } else {
                $this->authorize('create', Company::class);

                $service->create($data);
                $message = 'Perusahaan berhasil ditambahkan!';
            }

            $this->notifySuccess($message);
            $this->closeModal();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $company = Company::findOrFail($id);
        $this->deletingCompanyId = $company->id;
        $this->deletingCompanyName = $company->name;
        $this->showDeleteModal = true;
    }

    public function delete(CompanyService $service)
    {
        try {
            $company = Company::findOrFail($this->deletingCompanyId);
            $this->authorize('delete', $company);

            if ($company->users()->exists()) {
                $this->notifyError('Perusahaan tidak dapat dihapus karena masih memiliki user terkait.');
                $this->showDeleteModal = false;
                return;
            }

            $service->delete($company);
            $this->notifySuccess('Perusahaan berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus perusahaan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus($id, CompanyService $service)
    {
        try {
            $company = Company::findOrFail($id);
            $this->authorize('toggleStatus', $company);

            $service->toggleStatus($company);

            $status = $company->fresh()->status->label();
            $this->notifySuccess("Status perusahaan berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status perusahaan.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm()
    {
        $this->reset([
            'companyId',
            'code',
            'name',
            'email',
            'phone',
            'address',
            'pic_name',
            'pic_email',
            'pic_phone',
            'status',
        ]);

        $this->status = CompanyStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Company::class);

        return (new CompaniesExport($this->search, $this->statusFilter))
            ->download('perusahaan-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(CompanyService $service)
    {
        $this->authorize('exportPdf', Company::class);

        $companies = Company::withCount('users')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('code', 'like', "%{$this->search}%")
                      ->orWhere('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                      ->orWhere('pic_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy('name')
            ->get();

        $pdf = Pdf::loadView('exports.companies-pdf', ['companies' => $companies]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'perusahaan-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function render(CompanyService $service)
    {
        return view('livewire.master-data.company-management', [
            'companies' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->perPage
            ),
            'statuses' => CompanyStatus::cases(),
        ]);
    }
}
