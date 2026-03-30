<?php

namespace App\Livewire\MasterData;

use App\Enums\GalanganStatus;
use App\Exports\GalanganExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Galangan;
use App\Services\GalanganService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class GalanganManagement extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $statusFilter = '';
    public int $perPage = 10;
    public $showModal = false;
    public $editMode = false;

    public $galanganId;
    public $kode;
    public $nama;
    public $alamat;
    public $kota;
    public $provinsi;
    public $telepon;
    public $email;
    public $pic_name;
    public $pic_phone;
    public $keterangan;
    public $status = 'active';
    
    public $showDeleteModal = false;
    public $deletingGalanganId;
    public $deletingGalanganNama;

    public function mount()
    {
        $this->authorize('viewAny', Galangan::class);
    }

    public function rules()
    {
        $galanganId = $this->editMode ? $this->galanganId : null;
        
        return [
            'kode' => ['required', 'string', 'max:50', 'unique:galangan,kode,' . $galanganId],
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'pic_name' => 'nullable|string|max:255',
            'pic_phone' => 'nullable|string|max:20',
            'keterangan' => 'nullable|string',
            'status' => ['required', 'string', 'in:' . implode(',', GalanganStatus::values())],
        ];
    }

    public function validationAttributes()
    {
        return [
            'kode' => 'kode galangan',
            'nama' => 'nama galangan',
            'alamat' => 'alamat',
            'kota' => 'kota',
            'provinsi' => 'provinsi',
            'telepon' => 'telepon',
            'email' => 'email',
            'pic_name' => 'nama PIC',
            'pic_phone' => 'telepon PIC',
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
        $this->authorize('create', Galangan::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $galangan = Galangan::findOrFail($id);
        $this->authorize('update', $galangan);

        $this->galanganId = $galangan->id;
        $this->kode = $galangan->kode;
        $this->nama = $galangan->nama;
        $this->alamat = $galangan->alamat;
        $this->kota = $galangan->kota;
        $this->provinsi = $galangan->provinsi;
        $this->telepon = $galangan->telepon;
        $this->email = $galangan->email;
        $this->pic_name = $galangan->pic_name;
        $this->pic_phone = $galangan->pic_phone;
        $this->keterangan = $galangan->keterangan;
        $this->status = $galangan->status->value;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(GalanganService $service)
    {
        $this->validate();

        try {
            $data = [
                'kode' => $this->kode,
                'nama' => $this->nama,
                'alamat' => $this->alamat,
                'kota' => $this->kota,
                'provinsi' => $this->provinsi,
                'telepon' => $this->telepon,
                'email' => $this->email,
                'pic_name' => $this->pic_name,
                'pic_phone' => $this->pic_phone,
                'keterangan' => $this->keterangan,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $galangan = Galangan::findOrFail($this->galanganId);
                $this->authorize('update', $galangan);

                $service->update($galangan, $data);
                $message = 'Galangan berhasil diupdate!';
            } else {
                $this->authorize('create', Galangan::class);

                $service->create($data);
                $message = 'Galangan berhasil ditambahkan!';
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
        $galangan = Galangan::findOrFail($id);
        $this->deletingGalanganId = $galangan->id;
        $this->deletingGalanganNama = $galangan->nama;
        $this->showDeleteModal = true;
    }

    public function delete(GalanganService $service)
    {
        try {
            $galangan = Galangan::findOrFail($this->deletingGalanganId);
            $this->authorize('delete', $galangan);

            $service->delete($galangan);
            $this->notifySuccess('Galangan berhasil dihapus! Jenis kapal terkait akan di-set null.');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus galangan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus($id, GalanganService $service)
    {
        try {
            $galangan = Galangan::findOrFail($id);
            $this->authorize('toggleStatus', $galangan);

            $service->toggleStatus($galangan);

            $status = $galangan->fresh()->status->label();
            $this->notifySuccess("Status galangan berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status galangan.');
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
            'galanganId',
            'kode',
            'nama',
            'alamat',
            'kota',
            'provinsi',
            'telepon',
            'email',
            'pic_name',
            'pic_phone',
            'keterangan',
            'status',
        ]);

        $this->status = GalanganStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Galangan::class);

        return (new GalanganExport($this->search, $this->statusFilter))
            ->download('galangan-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(GalanganService $service)
    {
        $this->authorize('exportPdf', Galangan::class);

        $galanganList = Galangan::query()
            ->withCount('jenisKapal')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('kode', 'like', "%{$this->search}%")
                      ->orWhere('kota', 'like', "%{$this->search}%")
                      ->orWhere('provinsi', 'like', "%{$this->search}%")
                      ->orWhere('pic_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy('nama')
            ->get();

        $pdf = Pdf::loadView('exports.galangan-pdf', ['galanganList' => $galanganList]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'galangan-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function render(GalanganService $service)
    {
        return view('livewire.master-data.galangan-management', [
            'galanganList' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->perPage
            ),
            'statuses' => GalanganStatus::cases(),
        ]);
    }
}
