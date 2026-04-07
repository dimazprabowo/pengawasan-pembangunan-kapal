<?php

namespace App\Livewire\MasterData;

use App\Enums\JenisKapalStatus;
use App\Exports\JenisKapalExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use App\Models\Galangan;
use App\Models\JenisKapal;
use App\Services\JenisKapalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class JenisKapalManagement extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification, WithFileUploads;

    protected $paginationTheme = 'tailwind';

    public $search = '';
    public $statusFilter = '';
    public $companyFilter = '';
    public $galanganFilter = '';
    public int $perPage = 10;
    public $showModal = false;
    public $editMode = false;

    public $jenisKapalId;
    public $company_id;
    public $galangan_id;
    public $nama;
    public $deskripsi;
    public $status = 'active';
    
    public $showDeleteModal = false;
    public $deletingJenisKapalId;
    public $deletingJenisKapalNama;

    public $template_file;
    public $showDeleteTemplateModal = false;
    public $deletingTemplateJenisKapalId;

    public function mount()
    {
        $this->authorize('viewAny', JenisKapal::class);
    }

    public function rules()
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'galangan_id' => 'nullable|exists:galangan,id',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'status' => ['required', 'string', 'in:' . implode(',', JenisKapalStatus::values())],
            'template_file' => file_upload_validation_rule('template_laporan_jenis_kapal'),
        ];
    }

    public function validationAttributes()
    {
        return [
            'company_id' => 'perusahaan',
            'galangan_id' => 'galangan',
            'nama' => 'jenis kapal',
            'deskripsi' => 'deskripsi',
            'status' => 'status',
            'template_file' => 'template laporan',
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

    public function updatingCompanyFilter()
    {
        $this->resetPage();
    }

    public function updatingGalanganFilter()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->authorize('create', JenisKapal::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $jenisKapal = JenisKapal::findOrFail($id);
        $this->authorize('update', $jenisKapal);

        $this->jenisKapalId = $jenisKapal->id;
        $this->company_id = $jenisKapal->company_id;
        $this->galangan_id = $jenisKapal->galangan_id;
        $this->nama = $jenisKapal->nama;
        $this->deskripsi = $jenisKapal->deskripsi;
        $this->status = $jenisKapal->status->value;
        $this->template_file = null;

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(JenisKapalService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'company_id' => $this->company_id ?: null,
                'galangan_id' => $this->galangan_id ?: null,
                'nama' => $this->nama,
                'deskripsi' => $this->deskripsi,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $jenisKapal = JenisKapal::findOrFail($this->jenisKapalId);
                $this->authorize('update', $jenisKapal);

                $service->update($jenisKapal, $data);

                if ($this->template_file) {
                    $this->authorize('uploadTemplate', $jenisKapal);
                    $service->uploadTemplate($jenisKapal, $this->template_file);
                }

                $message = 'Jenis kapal berhasil diupdate!';
            } else {
                $this->authorize('create', JenisKapal::class);

                $jenisKapal = $service->create($data);

                if ($this->template_file) {
                    $this->authorize('uploadTemplate', $jenisKapal);
                    $service->uploadTemplate($jenisKapal, $this->template_file);
                }

                $message = 'Jenis kapal berhasil ditambahkan!';
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
        $jenisKapal = JenisKapal::findOrFail($id);
        $this->deletingJenisKapalId = $jenisKapal->id;
        $this->deletingJenisKapalNama = $jenisKapal->nama;
        $this->showDeleteModal = true;
    }

    public function delete(JenisKapalService $service)
    {
        try {
            $jenisKapal = JenisKapal::findOrFail($this->deletingJenisKapalId);
            $this->authorize('delete', $jenisKapal);

            $service->delete($jenisKapal);
            $this->notifySuccess('Jenis kapal berhasil dihapus! Laporan terkait akan di-set null.');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus jenis kapal ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function toggleStatus($id, JenisKapalService $service)
    {
        try {
            $jenisKapal = JenisKapal::findOrFail($id);
            $this->authorize('toggleStatus', $jenisKapal);

            $service->toggleStatus($jenisKapal);

            $status = $jenisKapal->fresh()->status->label();
            $this->notifySuccess("Status jenis kapal berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status jenis kapal.');
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
            'jenisKapalId',
            'company_id',
            'galangan_id',
            'nama',
            'deskripsi',
            'status',
            'template_file',
        ]);

        $this->status = JenisKapalStatus::Active->value;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', JenisKapal::class);

        return (new JenisKapalExport($this->search, $this->statusFilter, $this->companyFilter))
            ->download('jenis-kapal-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportPdf(JenisKapalService $service)
    {
        $this->authorize('exportPdf', JenisKapal::class);

        $jenisKapalList = JenisKapal::with('company')
            ->withCount('laporan')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('deskripsi', 'like', "%{$this->search}%")
                      ->orWhereHas('company', function ($q) {
                          $q->where('name', 'like', "%{$this->search}%")
                            ->orWhere('code', 'like', "%{$this->search}%");
                      });
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->companyFilter, function ($q) {
                $q->where('company_id', $this->companyFilter);
            })
            ->orderBy('nama')
            ->get();

        $pdf = Pdf::loadView('exports.jenis-kapal-pdf', ['jenisKapalList' => $jenisKapalList]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'jenis-kapal-' . now()->format('Y-m-d-His') . '.pdf'
        );
    }

    public function downloadTemplateHarian()
    {
        $this->authorize('downloadTemplate', JenisKapal::class);

        $templatePath = storage_path('app/templates/laporan-harian/template-laporan-harian.docx');

        if (!file_exists($templatePath)) {
            $this->notifyError('Template laporan harian tidak ditemukan.');
            return;
        }

        return response()->download($templatePath, 'template-laporan-harian.docx');
    }

    public function confirmDeleteTemplate($id)
    {
        $jenisKapal = JenisKapal::findOrFail($id);
        $this->authorize('uploadTemplate', $jenisKapal);

        if (!$jenisKapal->hasTemplate()) {
            $this->notifyWarning('Tidak ada template untuk dihapus.');
            return;
        }

        $this->deletingTemplateJenisKapalId = $jenisKapal->id;
        $this->showDeleteTemplateModal = true;
    }

    public function deleteTemplate(JenisKapalService $service)
    {
        try {
            $jenisKapal = JenisKapal::findOrFail($this->deletingTemplateJenisKapalId);
            $this->authorize('uploadTemplate', $jenisKapal);

            $service->deleteTemplate($jenisKapal);
            $this->notifySuccess('Template berhasil dihapus!');
            $this->showDeleteTemplateModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function downloadTemplate($id, JenisKapalService $service)
    {
        try {
            $jenisKapal = JenisKapal::findOrFail($id);
            $this->authorize('uploadTemplate', $jenisKapal);

            $templatePath = $service->downloadTemplate($jenisKapal);

            if (!$templatePath || !file_exists($templatePath)) {
                $this->notifyError('Template tidak ditemukan.');
                return;
            }

            $filename = 'template-' . \Str::slug($jenisKapal->nama) . '.docx';

            return response()->download($templatePath, $filename);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengunduh template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render(JenisKapalService $service)
    {
        return view('livewire.master-data.jenis-kapal-management', [
            'jenisKapalList' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->companyFilter ? (int)$this->companyFilter : null,
                $this->galanganFilter ? (int)$this->galanganFilter : null,
                $this->perPage
            ),
            'statuses' => JenisKapalStatus::cases(),
            'companies' => Company::active()->orderBy('name')->get(),
            'galangans' => Galangan::active()->orderBy('nama')->get(),
        ]);
    }
}
