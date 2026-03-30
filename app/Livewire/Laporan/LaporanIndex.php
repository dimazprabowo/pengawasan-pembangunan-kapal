<?php

namespace App\Livewire\Laporan;

use App\Enums\LaporanTipe;
use App\Exports\LaporanExport;
use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\Laporan;
use App\Services\LaporanService;
use App\Services\QueueStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Manajemen Laporan'])]
class LaporanIndex extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

    // #[Url(as: 'tab')]
    public string $activeTab = 'harian';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $jenisKapalId = null;

    public int $perPage = 10;

    // Delete Modal
    public bool $showDeleteModal = false;
    public ?int $deletingLaporanId = null;
    public ?string $deletingLaporanJudul = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Laporan::class);

        $this->jenisKapalId = session('laporan_jenis_kapal_id');

        if (session()->has('notify')) {
            $notify = session('notify');
            $this->dispatch('notify', type: $notify['type'], message: $notify['message']);
        }
    }

    public function updatedJenisKapalId($value): void
    {
        session(['laporan_jenis_kapal_id' => $value]);
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, LaporanTipe::values())) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $laporan = Laporan::findOrFail($id);
        $this->deletingLaporanId = $laporan->id;
        $this->deletingLaporanJudul = $laporan->judul;
        $this->showDeleteModal = true;
    }

    public function delete(LaporanService $service): void
    {
        try {
            $laporan = Laporan::findOrFail($this->deletingLaporanId);
            $this->authorize('delete', $laporan);

            $service->delete($laporan);
            $this->notifySuccess('Laporan berhasil dihapus!');
            $this->showDeleteModal = false;
            $this->deletingLaporanId = null;
            $this->deletingLaporanJudul = null;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Laporan::class);

        $tipeEnum = LaporanTipe::from($this->activeTab);
        $filename = 'laporan-' . $tipeEnum->value . '-' . now()->format('Y-m-d-His') . '.xlsx';

        return (new LaporanExport($this->activeTab, $this->search))
            ->download($filename);
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', Laporan::class);

        $tipeEnum = LaporanTipe::from($this->activeTab);

        $laporanList = Laporan::with('user')
            ->where('tipe', $this->activeTab)
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('judul', 'like', "%{$this->search}%")
                      ->orWhere('isi', 'like', "%{$this->search}%")
                      ->orWhereHas('user', function ($q) {
                          $q->where('name', 'like', "%{$this->search}%");
                      });
                });
            })
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('exports.laporan-pdf', [
            'laporanList' => $laporanList,
            'tipeLabel' => $tipeEnum->label(),
        ]);
        $pdf->setPaper('a4', 'landscape');

        $filename = 'laporan-' . $tipeEnum->value . '-' . now()->format('Y-m-d-His') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function render(LaporanService $service, QueueStatusService $queueStatusService)
    {
        $canViewAllJenisKapal = auth()->user()->can('laporan_view_all_jenis_kapal');
        
        $jenisKapalList = JenisKapal::with(['company', 'galangan'])
            ->active()
            ->when(!$canViewAllJenisKapal, function ($q) {
                $q->whereHas('company', function ($q) {
                    $q->where('id', auth()->user()->company_id);
                });
            })
            ->orderBy('nama')
            ->get();

        return view('livewire.laporan.laporan-index', [
            'laporanList' => $service->getFiltered(
                $this->activeTab,
                $this->search,
                $this->jenisKapalId,
                $this->perPage
            ),
            'tabs' => LaporanTipe::cases(),
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            'jenisKapalList' => $jenisKapalList,
            'canViewAllJenisKapal' => $canViewAllJenisKapal,
        ]);
    }
}
