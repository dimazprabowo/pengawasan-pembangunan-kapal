<?php

namespace App\Livewire\LaporanBulanan;

use App\Livewire\Traits\HasJenisKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use App\Services\KurvaSService;
use App\Services\LaporanBulananService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Manajemen Laporan Bulanan'])]
class LaporanBulananIndex extends Component
{
    use AuthorizesRequests, HasJenisKapalFilter, HasNotification, WithPagination;

    protected $paginationTheme = 'tailwind';

    #[Url(as: 'q')]
    public string $search = '';

    public JenisKapal $jenisKapal;

    public ?int $jenisKapalId = null;

    public int $perPage = 10;

    // Kurva S Panel
    public bool $showKurvaS = false;

    // Delete Modal
    public bool $showDeleteModal = false;

    public ?int $deletingLaporanId = null;

    public ?string $deletingLaporanJudul = null;

    public function mount(JenisKapal $jenisKapal): void
    {
        $this->authorize('viewAny', LaporanBulanan::class);
        $this->authorizeJenisKapalAccess($jenisKapal);

        $this->jenisKapal = $jenisKapal;
        $this->jenisKapalId = $jenisKapal->id;
        $this->showKurvaS = session('laporan_bulanan_show_kurva_s', true);

        if (session()->has('notify')) {
            $notify = session('notify');
            $this->dispatch('notify', type: $notify['type'], message: $notify['message']);
        }
    }

    public function gantiKapal()
    {
        return $this->redirect(route('laporan.index'), navigate: true);
    }

    public function toggleKurvaS(): void
    {
        $this->showKurvaS = ! $this->showKurvaS;
        $this->updatedShowKurvaS($this->showKurvaS);
    }

    public function updatedShowKurvaS($value): void
    {
        session(['laporan_bulanan_show_kurva_s' => $value]);

        if ($value && $this->jenisKapalId) {
            $this->dispatchRealtimeUpdates();
        }
    }

    private function dispatchRealtimeUpdates(): void
    {
        $kurvaSService = app(KurvaSService::class);
        $chartData = $kurvaSService->getMonthlyChartData($this->jenisKapal);
        $progressHistory = $kurvaSService->getMonthlyProgressHistory($this->jenisKapal);
        $workGroupsForHistory = $this->getWorkGroupsForHistory();

        $totalRencana = null;
        $totalAktual = null;
        if (! empty($progressHistory) && ! empty($workGroupsForHistory)) {
            $totals = $kurvaSService->calculateTotalsFromHistory($progressHistory, $workGroupsForHistory);
            $totalRencana = $totals['total_rencana'];
            $totalAktual = $totals['total_aktual'];
        }

        $this->dispatch('kurva-s-updated', chartData: $chartData);
        $this->dispatch('progress-history-updated', history: $progressHistory);
        $this->dispatch('work-groups-updated', workGroups: $workGroupsForHistory);
        $this->dispatch('totals-updated', totalRencana: $totalRencana, totalAktual: $totalAktual);
    }

    private function getWorkGroupsForHistory(): array
    {
        return KurvaSWorkGroup::where('jenis_kapal_id', $this->jenisKapalId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($wg) => [
                'work_group_id' => $wg->id,
                'nama' => $wg->nama,
                'bobot' => $wg->bobot,
            ])
            ->toArray();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'perPage']);
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->deletingLaporanId = $laporan->id;
        $this->deletingLaporanJudul = $laporan->judul;
        $this->showDeleteModal = true;
    }

    public function delete(LaporanBulananService $service): void
    {
        try {
            $laporan = LaporanBulanan::findOrFail($this->deletingLaporanId);
            $this->authorize('delete', $laporan);

            $service->delete($laporan);
            $this->notifySuccess('Laporan berhasil dihapus!');
            $this->showDeleteModal = false;
            $this->deletingLaporanId = null;
            $this->deletingLaporanJudul = null;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function render(LaporanBulananService $service, KurvaSService $kurvaSService)
    {
        $chartData = [];
        $progressHistory = [];
        $workGroupsForHistory = [];
        $totalRencana = null;
        $totalAktual = null;

        if ($this->showKurvaS && $this->jenisKapal) {
            $chartData = $kurvaSService->getMonthlyChartData($this->jenisKapal);
            $progressHistory = $kurvaSService->getMonthlyProgressHistory($this->jenisKapal);
            $workGroupsForHistory = $this->getWorkGroupsForHistory();

            if (! empty($progressHistory) && ! empty($workGroupsForHistory)) {
                $totals = $kurvaSService->calculateTotalsFromHistory($progressHistory, $workGroupsForHistory);
                $totalRencana = $totals['total_rencana'];
                $totalAktual = $totals['total_aktual'];
            }
        }

        return view('livewire.laporan-bulanan.laporan-bulanan-index', [
            'laporanList' => $service->getFiltered(
                $this->search,
                $this->jenisKapalId,
                $this->perPage
            ),
            'jenisKapal' => $this->jenisKapal,
            'kurvaSChartData' => $chartData,
            'progressHistory' => $progressHistory,
            'workGroupsForHistory' => $workGroupsForHistory,
            'totalRencana' => $totalRencana,
            'totalAktual' => $totalAktual,
        ]);
    }
}
