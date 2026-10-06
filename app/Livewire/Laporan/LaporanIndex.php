<?php

namespace App\Livewire\Laporan;

use App\Exports\RekapJenisKapalExport;
use App\Livewire\Traits\HasJenisKapalFilter;
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
    use AuthorizesRequests, HasJenisKapalFilter, WithPagination;

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

        $accessible = $this->getJenisKapalList();

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

    public function openLaporan(int $jenisKapalId)
    {
        $jenisKapal = $this->getJenisKapalList()->firstWhere('id', $jenisKapalId);
        abort_unless($jenisKapal, 403);

        return $this->redirect(route('laporan-harian.index', $jenisKapal), navigate: true);
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', LaporanHarian::class);

        $items = $this->withProgress($this->filteredQuery()->get());

        return (new RekapJenisKapalExport($items))
            ->download('rekap-jenis-kapal-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', LaporanHarian::class);

        $items = $this->withProgress($this->filteredQuery()->get());

        $pdf = Pdf::loadView('exports.rekap-jenis-kapal-pdf', [
            'jenisKapalList' => $items,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'rekap-jenis-kapal-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    /**
     * Base query for the picker table: accessible jenis kapal + search/filters + summary aggregates.
     */
    private function filteredQuery(): Builder
    {
        return $this->getJenisKapalQuery()
            ->withCount(['laporanHarian', 'laporanMingguan'])
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
     * Enrich a jenis kapal collection with `progress_aktual` (Kurva-S realization).
     * Progress only exists once a mingguan report has been made.
     */
    private function withProgress(Collection|\Illuminate\Pagination\LengthAwarePaginator $items): Collection|\Illuminate\Pagination\LengthAwarePaginator
    {
        $workGroupBobots = KurvaSWorkGroup::whereIn('jenis_kapal_id', $items->pluck('id'))
            ->get(['id', 'jenis_kapal_id', 'bobot'])
            ->groupBy('jenis_kapal_id');

        $kurvaSService = app(KurvaSService::class);

        foreach ($items as $jenisKapal) {
            $jenisKapal->progress_aktual = null;

            if ($jenisKapal->laporan_mingguan_count > 0) {
                $workGroups = ($workGroupBobots[$jenisKapal->id] ?? collect())
                    ->map(fn ($wg) => ['work_group_id' => $wg->id, 'bobot' => $wg->bobot])
                    ->toArray();

                $jenisKapal->progress_aktual = $kurvaSService->calculateTotalsFromHistory(
                    $kurvaSService->getProgressHistory($jenisKapal),
                    $workGroups
                )['total_aktual'];
            }
        }

        return $items;
    }

    public function render()
    {
        // Filter options: only companies/galangans that own accessible jenis kapal
        $accessible = $this->getJenisKapalList();
        $companies = $accessible->pluck('company')->filter()->unique('id')->sortBy('name')->values();
        $galangans = $accessible->pluck('galangan')->filter()->unique('id')->sortBy('nama')->values();

        $jenisKapalList = $this->withProgress($this->filteredQuery()->paginate($this->perPage));

        return view('livewire.laporan.laporan-index', [
            'jenisKapalList' => $jenisKapalList,
            'companies' => $companies,
            'galangans' => $galangans,
        ]);
    }
}
