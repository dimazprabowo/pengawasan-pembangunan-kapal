<?php

namespace App\Livewire\LaporanHarian;

use App\Exports\LaporanHarianExport;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Livewire\Traits\HasTableSorting;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Services\KontrakPeriodeService;
use App\Services\LaporanHarianService;
use App\Services\QueueStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Manajemen Laporan Harian'])]
class LaporanHarianIndex extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithPagination;
    use HasTableSorting;

    protected $paginationTheme = 'tailwind';

    #[Url(as: 'q')]
    public string $search = '';

    public DataKapal $dataKapal;

    public ?int $dataKapalId = null;

    public int $perPage = 10;

    #[Url(as: 'tab')]
    public string $tab = 'laporan';

    public array $mingguFilter = [];

    public array $bulanFilter = [];

    // Delete Modal
    public bool $showDeleteModal = false;

    public ?int $deletingLaporanId = null;

    public ?string $deletingLaporanJudul = null;

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', LaporanHarian::class);
        $this->authorizeDataKapalAccess($dataKapal);

        $this->dataKapal = $dataKapal;
        $this->dataKapalId = $dataKapal->id;

        if (session()->has('notify')) {
            $notify = session('notify');
            $this->dispatch('notify', type: $notify['type'], message: $notify['message']);
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['laporan', 'nc']) ? $tab : 'laporan';
        $this->resetPage();
    }

    public function gantiKapal()
    {
        return $this->redirect(route('laporan.index'), navigate: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingMingguFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBulanFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Bulan berubah → buang minggu terpilih yang tidak ada di bulan tsb.
     */
    public function updatedBulanFilter(): void
    {
        if (empty($this->mingguFilter)) {
            return;
        }

        $allowed = $this->allowedMingguKe(app(KontrakPeriodeService::class));
        if ($allowed === null) {
            return;
        }

        $this->mingguFilter = array_values(array_intersect(
            array_map('intval', $this->mingguFilter),
            $allowed
        ));
    }

    /**
     * Minggu ke- yang valid untuk bulan terpilih; null = semua (tak ada bulan dipilih).
     */
    private function allowedMingguKe(KontrakPeriodeService $periodeService): ?array
    {
        $bulanKe = array_filter(array_map('intval', $this->bulanFilter));
        if (empty($bulanKe)) {
            return null;
        }

        $allowed = [];
        foreach ($bulanKe as $ke) {
            $periode = $periodeService->getBulanPeriode($this->dataKapal, $ke);
            if ($periode) {
                $allowed = array_merge($allowed, range($periode['minggu_dari'], $periode['minggu_sampai']));
            }
        }

        return array_values(array_unique($allowed));
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'mingguFilter', 'bulanFilter', 'perPage']);
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $laporan = LaporanHarian::findOrFail($id);
        $this->deletingLaporanId = $laporan->id;
        $this->deletingLaporanJudul = $laporan->judul;
        $this->showDeleteModal = true;
    }

    public function delete(LaporanHarianService $service): void
    {
        try {
            $laporan = LaporanHarian::findOrFail($this->deletingLaporanId);
            $this->authorize('delete', $laporan);

            $service->delete($laporan);
            $this->notifySuccess('Laporan berhasil dihapus!');
            $this->showDeleteModal = false;
            $this->deletingLaporanId = null;
            $this->deletingLaporanJudul = null;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', LaporanHarian::class);

        $filename = 'laporan-harian-'.now()->format('Y-m-d-His').'.xlsx';

        return (new LaporanHarianExport($this->search, $this->dataKapalId))
            ->download($filename);
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', LaporanHarian::class);

        $laporanList = LaporanHarian::with(['user', 'dataKapal.company', 'dataKapal.galangan'])
            ->byDataKapal($this->dataKapalId)
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('tanggal_laporan', 'like', "%{$this->search}%")
                        ->orWhereHas('user', function ($q) {
                            $q->where('name', 'like', "%{$this->search}%");
                        });
                });
            })
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('exports.laporan-harian-pdf', [
            'laporanList' => $laporanList,
            'tipeLabel' => 'Harian',
        ]);
        $pdf->setPaper('a4', 'landscape');

        $filename = 'laporan-harian-'.now()->format('Y-m-d-His').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename
        );
    }

    public function render(LaporanHarianService $service, QueueStatusService $queueStatusService, KontrakPeriodeService $periodeService)
    {
        return view('livewire.laporan-harian.laporan-harian-index', [
            'laporanList' => $service->getFiltered(
                $this->search,
                $this->dataKapalId,
                $this->perPage,
                $this->sortField,
                $this->sortDir,
                $this->mingguFilter,
                $this->bulanFilter
            ),
            'mingguOptions' => collect($periodeService->getMingguList($this->dataKapal))
                ->when($this->allowedMingguKe($periodeService) !== null, fn ($c) => $c->filter(fn ($m) => in_array($m['minggu_ke'], $this->allowedMingguKe($periodeService), true)))
                ->map(fn ($m) => ['value' => $m['minggu_ke'], 'label' => 'Minggu ke-'.$m['minggu_ke'].' ('.$m['label'].')'])->all(),
            'bulanOptions' => collect($periodeService->getBulanList($this->dataKapal))
                ->map(fn ($b) => ['value' => $b['bulan_ke'], 'label' => 'Bulan ke-'.$b['bulan_ke'].' ('.$b['label'].')'])->all(),
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            'dataKapal' => $this->dataKapal,
        ]);
    }
}
