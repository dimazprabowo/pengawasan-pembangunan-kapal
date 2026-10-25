<?php

namespace App\Livewire\Laporan;

use App\Exports\RekapDataKapalExport;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanHarian;
use App\Services\KurvaSService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Manajemen Laporan'])]
class LaporanIndex extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, WithPagination;

    protected $paginationTheme = 'tailwind';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'perusahaan')]
    public ?int $companyFilter = null;

    #[Url(as: 'galangan')]
    public ?int $galanganFilter = null;

    public int $perPage = 10;

    public function mount()
    {
        $this->authorize('viewAny', LaporanHarian::class);

        $accessible = $this->getDataKapalList();

        // Single accessible ship: skip picker entirely
        if ($accessible->count() === 1) {
            return redirect()->route('laporan-harian.index', $accessible->first());
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCompanyFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGalanganFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'companyFilter', 'galanganFilter', 'perPage']);
        $this->resetPage();
    }

    public function openLaporan(int $dataKapalId)
    {
        $dataKapal = $this->getDataKapalList()->firstWhere('id', $dataKapalId);
        abort_unless($dataKapal, 403);

        return $this->redirect(route('laporan-harian.index', $dataKapal), navigate: true);
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', LaporanHarian::class);

        $items = $this->withProgress($this->filteredQuery()->get());

        return (new RekapDataKapalExport($items))
            ->download('rekap-data-kapal-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', LaporanHarian::class);

        $items = $this->withProgress($this->filteredQuery()->get());

        $pdf = Pdf::loadView('exports.rekap-data-kapal-pdf', [
            'dataKapalList' => $items,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'rekap-data-kapal-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    /**
     * Base query for the picker table: accessible data kapal + search/filters + summary aggregates.
     */
    private function filteredQuery(): Builder
    {
        return $this->getDataKapalQuery()
            ->withCount(['laporanHarian', 'laporanMingguan', 'laporanBulanan'])
            ->withMax('laporanHarian', 'tanggal_laporan')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                        ->orWhereHas('company', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('galangan', fn ($q) => $q->where('nama', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->companyFilter, fn ($q) => $q->where('company_id', $this->companyFilter))
            ->when($this->galanganFilter, fn ($q) => $q->where('galangan_id', $this->galanganFilter));
    }

    /**
     * Enrich a data kapal collection with `progress_aktual` (Kurva-S realization).
     * Progress only exists once a mingguan report has been made.
     */
    private function withProgress(Collection|\Illuminate\Pagination\LengthAwarePaginator $items): Collection|\Illuminate\Pagination\LengthAwarePaginator
    {
        $workGroupBobots = KurvaSWorkGroup::whereIn('data_kapal_id', $items->pluck('id'))
            ->get(['id', 'data_kapal_id', 'bobot'])
            ->groupBy('data_kapal_id');

        $kurvaSService = app(KurvaSService::class);

        foreach ($items as $dataKapal) {
            $dataKapal->progress_aktual = null;

            if ($dataKapal->laporan_mingguan_count > 0) {
                $workGroups = ($workGroupBobots[$dataKapal->id] ?? collect())
                    ->map(fn ($wg) => ['work_group_id' => $wg->id, 'bobot' => $wg->bobot])
                    ->toArray();

                $dataKapal->progress_aktual = $kurvaSService->calculateTotalsFromHistory(
                    $kurvaSService->getProgressHistory($dataKapal),
                    $workGroups
                )['total_aktual'];
            }
        }

        return $items;
    }

    public function render()
    {
        // Filter options: only companies/galangans that own accessible data kapal
        $accessible = $this->getDataKapalList();
        $companies = $accessible->pluck('company')->filter()->unique('id')->sortBy('name')->values();
        $galangans = $accessible->pluck('galangan')->filter()->unique('id')->sortBy('nama')->values();

        $dataKapalList = $this->withProgress($this->filteredQuery()->paginate($this->perPage));

        return view('livewire.laporan.laporan-index', [
            'dataKapalList' => $dataKapalList,
            'companies' => $companies,
            'galangans' => $galangans,
        ]);
    }
}
