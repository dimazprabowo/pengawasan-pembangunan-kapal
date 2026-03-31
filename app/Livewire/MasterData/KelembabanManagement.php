<?php

namespace App\Livewire\MasterData;

use App\Enums\KelembabanStatus;
use App\Exports\KelembabanExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Kelembaban;
use App\Services\KelembabanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class KelembabanManagement extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $statusFilter = '';
    public int $perPage = 10;
    public $showModal = false;
    public $editMode = false;

    public $kelembabanId;
    public $nama;
    public $nilai;
    public $keterangan;
    public $status = 'active';
    
    public $showDeleteModal = false;
    public $deletingKelembabanId;
    public $deletingKelembabanNama;

    public function mount()
    {
        $this->authorize('viewAny', Kelembaban::class);
    }

    public function rules()
    {
        return [
            'nama' => 'required|string|max:255',
            'nilai' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'status' => ['required', 'string', 'in:' . implode(',', KelembabanStatus::values())],
        ];
    }

    public function validationAttributes()
    {
        return [
            'nama' => 'nama kelembaban',
            'nilai' => 'nilai',
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
        $this->authorize('create', Kelembaban::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $kelembaban = Kelembaban::findOrFail($id);
        $this->authorize('update', $kelembaban);

        $this->kelembabanId = $kelembaban->id;
        $this->nama = $kelembaban->nama;
        $this->nilai = $kelembaban->nilai;
        $this->keterangan = $kelembaban->keterangan;
        $this->status = $kelembaban->status->value;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(KelembabanService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'nama' => $this->nama,
                'nilai' => $this->nilai,
                'keterangan' => $this->keterangan,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $kelembaban = Kelembaban::findOrFail($this->kelembabanId);
                $this->authorize('update', $kelembaban);

                $service->update($kelembaban, $data);
                $message = 'Kelembaban berhasil diupdate!';
            } else {
                $this->authorize('create', Kelembaban::class);

                $service->create($data);
                $message = 'Kelembaban berhasil ditambahkan!';
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
        $kelembaban = Kelembaban::findOrFail($id);
        $this->deletingKelembabanId = $kelembaban->id;
        $this->deletingKelembabanNama = $kelembaban->nama;
        $this->showDeleteModal = true;
    }

    public function delete(KelembabanService $service)
    {
        try {
            $kelembaban = Kelembaban::findOrFail($this->deletingKelembabanId);
            $this->authorize('delete', $kelembaban);

            $service->delete($kelembaban);
            $this->notifySuccess('Kelembaban berhasil dihapus! Laporan terkait akan di-set null.');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus kelembaban ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus($id, KelembabanService $service)
    {
        try {
            $kelembaban = Kelembaban::findOrFail($id);
            $this->authorize('toggleStatus', $kelembaban);

            $service->toggleStatus($kelembaban);

            $status = $kelembaban->fresh()->status->label();
            $this->notifySuccess("Status kelembaban berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status kelembaban.');
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
            'kelembabanId',
            'nama',
            'nilai',
            'keterangan',
            'status',
        ]);

        $this->status = KelembabanStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Kelembaban::class);

        return (new KelembabanExport($this->search, $this->statusFilter))
            ->download('kelembaban-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(KelembabanService $service)
    {
        $this->authorize('exportPdf', Kelembaban::class);

        $kelembabanList = Kelembaban::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('nilai', 'like', "%{$this->search}%")
                      ->orWhere('keterangan', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy('nama')
            ->get();

        $pdf = Pdf::loadView('exports.kelembaban-pdf', ['kelembabanList' => $kelembabanList]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'kelembaban-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function render(KelembabanService $service)
    {
        return view('livewire.master-data.kelembaban-management', [
            'kelembabanList' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->perPage
            ),
            'statuses' => KelembabanStatus::cases(),
        ]);
    }
}
