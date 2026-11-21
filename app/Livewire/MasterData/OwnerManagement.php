<?php

namespace App\Livewire\MasterData;

use App\Enums\OwnerStatus;
use App\Exports\OwnersExport;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\Owner;
use App\Services\OwnerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class OwnerManagement extends Component
{
    use AuthorizesRequests, HasNotification, WithPagination;
    use HasTableSorting;

    protected $paginationTheme = 'tailwind';

    public $search = '';

    public $statusFilter = '';

    public int $perPage = 10;

    public $showModal = false;

    public $editMode = false;

    // Form fields
    public $ownerId;

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

    public $deletingOwnerId;

    public $deletingOwnerName;

    public function mount()
    {
        $this->authorize('viewAny', Owner::class);
    }

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'max:50', $this->editMode ? 'unique:owners,code,'.$this->ownerId : 'unique:owners,code'],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'pic_name' => 'nullable|string|max:255',
            'pic_email' => 'nullable|email|max:255',
            'pic_phone' => 'nullable|string|max:20',
            'status' => ['required', 'string', 'in:'.implode(',', OwnerStatus::values())],
        ];
    }

    public function validationAttributes()
    {
        return [
            'code' => 'kode owner',
            'name' => 'nama owner',
            'email' => 'email owner',
            'phone' => 'telepon owner',
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

    public function resetFilters()
    {
        $this->reset(['search', 'statusFilter', 'perPage']);
        $this->resetPage();
    }

    public function create()
    {
        $this->authorize('create', Owner::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $owner = Owner::findOrFail($id);
        $this->authorize('update', $owner);

        $this->ownerId = $owner->id;
        $this->code = $owner->code;
        $this->name = $owner->name;
        $this->email = $owner->email;
        $this->phone = $owner->phone;
        $this->address = $owner->address;
        $this->pic_name = $owner->pic_name;
        $this->pic_email = $owner->pic_email;
        $this->pic_phone = $owner->pic_phone;
        $this->status = $owner->status->value;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(OwnerService $service)
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
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
                $owner = Owner::findOrFail($this->ownerId);
                $this->authorize('update', $owner);

                $service->update($owner, $data);
                $message = 'Owner berhasil diupdate!';
            } else {
                $this->authorize('create', Owner::class);

                $service->create($data);
                $message = 'Owner berhasil ditambahkan!';
            }

            $this->notifySuccess($message);
            $this->closeModal();
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $owner = Owner::findOrFail($id);
        $this->deletingOwnerId = $owner->id;
        $this->deletingOwnerName = $owner->name;
        $this->showDeleteModal = true;
    }

    public function delete(OwnerService $service)
    {
        try {
            $owner = Owner::findOrFail($this->deletingOwnerId);
            $this->authorize('delete', $owner);

            if ($owner->users()->exists()) {
                $this->notifyError('Owner tidak dapat dihapus karena masih memiliki user terkait.');
                $this->showDeleteModal = false;

                return;
            }

            $service->delete($owner);
            $this->notifySuccess('Owner berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus owner ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function toggleStatus($id, OwnerService $service)
    {
        try {
            $owner = Owner::findOrFail($id);
            $this->authorize('toggleStatus', $owner);

            $service->toggleStatus($owner);

            $status = $owner->fresh()->status->label();
            $this->notifySuccess("Status owner berhasil diubah menjadi {$status}!");
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status owner.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
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
            'ownerId',
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

        $this->status = OwnerStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Owner::class);

        return (new OwnersExport($this->search, $this->statusFilter))
            ->download('owner-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf(OwnerService $service)
    {
        $this->authorize('exportPdf', Owner::class);

        $owners = Owner::withCount('users')
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

        $pdf = Pdf::loadView('exports.owners-pdf', ['owners' => $owners]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'owner-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function render(OwnerService $service)
    {
        return view('livewire.master-data.owner-management', [
            'owners' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->perPage,
                $this->sortField,
                $this->sortDir
            ),
            'statuses' => OwnerStatus::cases(),
        ]);
    }
}
