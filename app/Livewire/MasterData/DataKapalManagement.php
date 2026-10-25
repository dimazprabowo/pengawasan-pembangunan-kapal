<?php

namespace App\Livewire\MasterData;

use App\Enums\DataKapalStatus;
use App\Exports\DataKapalExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use App\Models\DataKapal;
use App\Models\Galangan;
use App\Services\DataKapalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class DataKapalManagement extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads, WithPagination;

    protected $paginationTheme = 'tailwind';

    public $search = '';

    public $statusFilter = '';

    public $companyFilter = '';

    public $galanganFilter = '';

    public int $perPage = 10;

    public $showDeleteModal = false;

    public $deletingDataKapalId;

    public $deletingDataKapalNama;

    // Template upload modal states
    public $showTemplateUploadModal = false;

    public $uploadingDataKapalId;

    public $uploadingTemplateTipe;

    public $template_file;

    // Template download modal state
    public $showDownloadTemplateModal = false;

    // Template delete modal states
    public $showDeleteTemplateModal = false;

    public $deletingTemplateDataKapalId;

    public $deletingTemplateTipe;

    // Kurva S import/export modal states
    public $showKurvaSImportModal = false;

    public $kurvaSDataKapalId;

    public $kurvaS_file;

    public function mount()
    {
        $this->authorize('viewAny', DataKapal::class);
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

    public function resetFilters()
    {
        $this->reset(['search', 'companyFilter', 'galanganFilter', 'statusFilter', 'perPage']);
        $this->resetPage();
    }

    public function create()
    {
        $this->authorize('create', DataKapal::class);

        return $this->redirect(route('master-data.data-kapal.create'), navigate: true);
    }

    public function edit($id)
    {
        $dataKapal = DataKapal::findOrFail($id);
        $this->authorize('update', $dataKapal);

        return $this->redirect(route('master-data.data-kapal.edit', $dataKapal), navigate: true);
    }

    public function confirmDelete($id)
    {
        $dataKapal = DataKapal::findOrFail($id);
        $this->deletingDataKapalId = $dataKapal->id;
        $this->deletingDataKapalNama = $dataKapal->nama;
        $this->showDeleteModal = true;
    }

    public function delete(DataKapalService $service)
    {
        try {
            $dataKapal = DataKapal::findOrFail($this->deletingDataKapalId);
            $this->authorize('delete', $dataKapal);

            $service->delete($dataKapal);
            $this->notifySuccess('Kapal berhasil dihapus! Laporan terkait akan di-set null.');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus kapal ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function toggleStatus($id, DataKapalService $service)
    {
        try {
            $dataKapal = DataKapal::findOrFail($id);
            $this->authorize('toggleStatus', $dataKapal);

            $service->toggleStatus($dataKapal);

            $status = $dataKapal->fresh()->status->label();
            $this->notifySuccess("Status kapal berhasil diubah menjadi {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah status kapal.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', DataKapal::class);

        return Excel::download(
            new DataKapalExport($this->search, $this->statusFilter, $this->companyFilter),
            'data-kapal-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }

    public function exportPdf(DataKapalService $service)
    {
        $this->authorize('exportPdf', DataKapal::class);

        $dataKapalList = DataKapal::with('company')
            ->withCount('laporanHarian')
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

        $pdf = Pdf::loadView('exports.data-kapal-pdf', ['dataKapalList' => $dataKapalList]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'data-kapal-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function openDownloadTemplateModal()
    {
        $this->authorize('downloadTemplate', DataKapal::class);
        $this->showDownloadTemplateModal = true;
    }

    public function closeDownloadTemplateModal()
    {
        $this->showDownloadTemplateModal = false;
    }

    public function downloadDefaultTemplate(string $tipe, DataKapalService $service)
    {
        $this->authorize('downloadTemplate', DataKapal::class);

        $templatePath = $service->downloadDefaultTemplate($tipe);

        if (! $templatePath) {
            $this->notifyError('Template laporan '.$tipe.' tidak ditemukan.');

            return;
        }

        return response()->download($templatePath, 'template-laporan-'.$tipe.'.docx');
    }

    public function openTemplateUploadModal($id, string $tipe)
    {
        $dataKapal = DataKapal::findOrFail($id);
        $this->authorize('uploadTemplate', $dataKapal);

        $this->uploadingDataKapalId = $dataKapal->id;
        $this->uploadingTemplateTipe = $tipe;
        $this->showTemplateUploadModal = true;
    }

    public function closeTemplateUploadModal()
    {
        $this->showTemplateUploadModal = false;
        $this->reset(['uploadingDataKapalId', 'uploadingTemplateTipe', 'template_file']);
        $this->resetValidation();
    }

    public function uploadTemplate(DataKapalService $service)
    {
        try {
            $this->validate([
                'template_file' => file_upload_validation_rule('template_laporan_data_kapal'),
            ], [
                'template_file' => 'template laporan '.$this->uploadingTemplateTipe,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $dataKapal = DataKapal::findOrFail($this->uploadingDataKapalId);
            $this->authorize('uploadTemplate', $dataKapal);

            if ($this->template_file) {
                $service->uploadTemplate($dataKapal, $this->template_file, $this->uploadingTemplateTipe);
                $this->notifySuccess('Template laporan '.$this->uploadingTemplateTipe.' berhasil diupload!');
            }

            $this->closeTemplateUploadModal();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk upload template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function confirmDeleteTemplate($id, string $tipe)
    {
        $dataKapal = DataKapal::findOrFail($id);
        $this->authorize('uploadTemplate', $dataKapal);

        if (! $dataKapal->hasTemplate($tipe)) {
            $this->notifyWarning('Tidak ada template '.$tipe.' untuk dihapus.');

            return;
        }

        $this->deletingTemplateDataKapalId = $dataKapal->id;
        $this->deletingTemplateTipe = $tipe;
        $this->showDeleteTemplateModal = true;
    }

    public function deleteTemplate(DataKapalService $service)
    {
        try {
            $dataKapal = DataKapal::findOrFail($this->deletingTemplateDataKapalId);
            $this->authorize('uploadTemplate', $dataKapal);

            $service->deleteTemplate($dataKapal, $this->deletingTemplateTipe);
            $this->notifySuccess('Template laporan '.$this->deletingTemplateTipe.' berhasil dihapus!');
            $this->showDeleteTemplateModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function downloadTemplate($id, string $tipe, DataKapalService $service)
    {
        try {
            $dataKapal = DataKapal::findOrFail($id);
            $this->authorize('uploadTemplate', $dataKapal);

            $templatePath = $service->downloadTemplate($dataKapal, $tipe);

            if (! $templatePath || ! file_exists($templatePath)) {
                $this->notifyError('Template tidak ditemukan.');

                return;
            }

            $filename = 'template-'.$tipe.'-'.\Str::slug($dataKapal->nama).'.docx';

            return response()->download($templatePath, $filename);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengunduh template.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function openKurvaSModal(int $id): void
    {
        $dataKapal = DataKapal::findOrFail($id);
        $this->authorize('managekurvaSRencana', $dataKapal);
        $this->dispatch('open-kurvas-modal', dataKapalId: $id);
    }

    public function exportKurvaSTemplate($id, bool $withData, DataKapalService $service)
    {
        try {
            $dataKapal = DataKapal::findOrFail($id);
            $this->authorize('exportKurvaSTemplate', $dataKapal);

            return $service->exportKurvaSTemplate($dataKapal, $withData);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk export template Kurva S.');
        } catch (\Exception $e) {
            \Log::error('Export Kurva S gagal', ['data_kapal_id' => $id, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem saat export. Silakan coba lagi.');
        }
    }

    public function openKurvaSImportModal($id)
    {
        try {
            $dataKapal = DataKapal::findOrFail($id);
            $this->authorize('importKurvaSTemplate', $dataKapal);

            $this->kurvaSDataKapalId = $dataKapal->id;
            $this->showKurvaSImportModal = true;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk import template Kurva S.');
        }
    }

    public function closeKurvaSImportModal()
    {
        $this->showKurvaSImportModal = false;
        $this->reset(['kurvaSDataKapalId', 'kurvaS_file']);
        $this->resetValidation();
    }

    public function importKurvaSTemplate(DataKapalService $service)
    {
        try {
            $this->validate([
                'kurvaS_file' => 'required|file|mimes:xlsx,xls|max:5120',
            ], [
                'kurvaS_file.required' => 'File template wajib diupload',
                'kurvaS_file.file' => 'File tidak valid',
                'kurvaS_file.mimes' => 'File harus berformat Excel (.xlsx atau .xls)',
                'kurvaS_file.max' => 'Ukuran file maksimal 5MB',
            ], [
                'kurvaS_file' => 'file template',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $dataKapal = DataKapal::findOrFail($this->kurvaSDataKapalId);
            $this->authorize('importKurvaSTemplate', $dataKapal);

            $result = $service->importKurvaSTemplate($dataKapal, $this->kurvaS_file);

            if ($result['success']) {
                $this->notifySuccess($result['message'].' Total work groups: '.$result['work_groups_count']);
                $this->closeKurvaSImportModal();
                $this->dispatch('kurvas-imported');
            } else {
                $errorMessage = 'Import gagal:';
                foreach ($result['errors'] as $error) {
                    $errorMessage .= "\n• ".$error;
                }
                $this->notifyError($errorMessage);
            }
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk import template Kurva S.');
        } catch (\Exception $e) {
            \Log::error('Import Kurva S gagal', ['data_kapal_id' => $this->kurvaSDataKapalId, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem saat import. Silakan coba lagi.');
        }
    }

    public function render(DataKapalService $service)
    {
        return view('livewire.master-data.data-kapal-management', [
            'dataKapalList' => $service->getFiltered(
                $this->search,
                $this->statusFilter,
                $this->companyFilter ? (int) $this->companyFilter : null,
                $this->galanganFilter ? (int) $this->galanganFilter : null,
                $this->perPage
            ),
            'statuses' => DataKapalStatus::cases(),
            'companies' => Company::active()->orderBy('name')->get(),
            'galangans' => Galangan::active()->orderBy('nama')->get(),
        ]);
    }
}
