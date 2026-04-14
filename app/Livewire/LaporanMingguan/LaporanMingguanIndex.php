<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\LaporanMingguan;
use App\Exports\LaporanMingguanExport;
use App\Services\LaporanMingguanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Manajemen Laporan Mingguan'])]
class LaporanMingguanIndex extends Component
{
    use WithPagination, AuthorizesRequests, HasNotification;

    protected $paginationTheme = 'tailwind';

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
        $this->authorize('viewAny', LaporanMingguan::class);

        $this->jenisKapalId = session('laporan_harian_jenis_kapal_id');

        if (session()->has('notify')) {
            $notify = session('notify');
            $this->dispatch('notify', type: $notify['type'], message: $notify['message']);
        }
    }

    public function updatedJenisKapalId($value): void
    {
        session(['laporan_harian_jenis_kapal_id' => $value]);
        $this->resetPage();
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
        $laporan = LaporanMingguan::findOrFail($id);
        $this->deletingLaporanId = $laporan->id;
        $this->deletingLaporanJudul = $laporan->judul;
        $this->showDeleteModal = true;
    }

    public function delete(LaporanMingguanService $service): void
    {
        try {
            $laporan = LaporanMingguan::findOrFail($this->deletingLaporanId);
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
        $this->authorize('exportExcel', LaporanMingguan::class);

        $filename = 'laporan-mingguan-' . now()->format('Y-m-d-His') . '.xlsx';

        return (new LaporanMingguanExport($this->search, $this->jenisKapalId))
            ->download($filename);
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', LaporanMingguan::class);

        $laporanList = LaporanMingguan::with(['user', 'jenisKapal'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('judul', 'like', "%{$this->search}%")
                      ->orWhereHas('user', function ($q) {
                          $q->where('name', 'like', "%{$this->search}%");
                      });
                });
            })
            ->when($this->jenisKapalId, function ($q) {
                $q->where('jenis_kapal_id', $this->jenisKapalId);
            })
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('exports.laporan-mingguan-pdf', [
            'laporanList' => $laporanList,
            'tipeLabel' => 'Mingguan',
        ]);
        $pdf->setPaper('a4', 'landscape');

        $filename = 'laporan-mingguan-' . now()->format('Y-m-d-His') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename
        );
    }

    public function render(LaporanMingguanService $service)
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-index', [
            'laporanList' => $service->getFiltered(
                $this->search,
                $this->jenisKapalId,
                $this->perPage
            ),
            'jenisKapalList' => $this->authorize('viewAllJenisKapal', LaporanMingguan::class)
                ? JenisKapal::with(['company', 'galangan'])->get()
                : JenisKapal::whereHas('users', function ($q) {
                    $q->where('users.id', auth()->id());
                })->with(['company', 'galangan'])->get(),
        ]);
    }
}
