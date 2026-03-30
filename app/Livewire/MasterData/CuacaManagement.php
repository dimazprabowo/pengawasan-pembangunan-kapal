<?php

namespace App\Livewire\MasterData;

use App\Enums\CuacaStatus;
use App\Exports\CuacaExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Services\CuacaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class CuacaManagement extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $statusFilter = '';
    public int $perPage = 10;
    public $showModal = false;
    public $editMode = false;

    public $cuacaId;
    public $nama;
    public $keterangan;
    public $status = 'active';
    
    public $showDeleteModal = false;
    public $deletingCuacaId;
    public $deletingCuacaNama;

    public function mount()
    {
        $this->authorize('viewAny', Cuaca::class);
    }

    public function rules()
    {
        return [
            'nama' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
            'status' => ['required', 'string', 'in:' . implode(',', CuacaStatus::values())],
        ];
    }

    public function validationAttributes()
    {
        return [
            'nama' => 'nama cuaca',
            'keterangan' => 'keterangan',
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
        $this->authorize('create', Cuaca::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $cuaca = Cuaca::findOrFail($id);
        $this->authorize('update', $cuaca);

        $this->cuacaId = $cuaca->id;
        $this->nama = $cuaca->nama;
        $this->keterangan = $cuaca->keterangan;
        $this->status = $cuaca->status->value;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(CuacaService $service)
    {
        $this->validate();

        try {
            $data = [
                'nama' => $this->nama,
                'keterangan' => $this->keterangan,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $cuaca = Cuaca::findOrFail($this->cuacaId);
                $this->authorize('update', $cuaca);

                $service->update($cuaca, $data);
                $message = 'Cuaca berhasil diupdate!';
            } else {
                $this->authorize('create', Cuaca::class);

                $service->create($data);
                $message = 'Cuaca berhasil ditambahkan!';
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
        $cuaca = Cuaca::findOrFail($id);
        $this->deletingCuacaId = $cuaca->id;
        $this->deletingCuacaNama = $cuaca->nama;
        $this->showDeleteModal = true;
    }

    public function delete(CuacaService $service)
    {
        try {
            $cuaca = Cuaca::findOrFail($this->deletingCuacaId);
            $this->authorize('delete', $cuaca);

            $service->delete($cuaca);
            $this->notifySuccess('Cuaca berhasil dihapus! Laporan terkait akan di-set null.');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus cuaca ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus($id, CuacaService $service)
    {
        try {
            $cuaca = Cuaca::findOrFail($id);
            $this->authorize('toggleStatus', $cuaca);

            $service->toggleStatus($cuaca);

            $status = $cuaca->fresh()->status->label();
            $this->notifySuccess("Status cuaca berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status cuaca.');
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
            'cuacaId',
            'nama',
            'keterangan',
            'status',
        ]);

        $this->status = CuacaStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Cuaca::class);

        return (new CuacaExport($this->search, $this->statusFilter))
            ->download('cuaca-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(CuacaService $service)
    {
        $this->authorize('exportPdf', Cuaca::class);

        $cuacaList = Cuaca::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('keterangan', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy('nama')
            ->get();

        $pdf = Pdf::loadView('exports.cuaca-pdf', ['cuacaList' => $cuacaList]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'cuaca-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function render(CuacaService $service)
    {
        return view('livewire.master-data.cuaca-management', [
            'cuacaList' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->perPage
            ),
            'statuses' => CuacaStatus::cases(),
        ]);
    }
}
