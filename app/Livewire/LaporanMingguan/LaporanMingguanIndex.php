<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasJenisKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\LaporanMingguan;
use App\Exports\LaporanMingguanExport;
use App\Services\KurvaSService;
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
    use WithPagination, AuthorizesRequests, HasNotification, HasJenisKapalFilter;

    protected $paginationTheme = 'tailwind';

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $jenisKapalId = null;

    public int $perPage = 10;

    // Kurva S Panel
    public bool $showKurvaS = false;

    // Delete Modal
    public bool $showDeleteModal = false;
    public ?int $deletingLaporanId = null;
    public ?string $deletingLaporanJudul = null;

    public function mount(): void
    {
        $this->authorize('viewAny', LaporanMingguan::class);

        $this->jenisKapalId = $this->getSelectedJenisKapalId();
        $this->showKurvaS = session('laporan_mingguan_show_kurva_s', false);

        if (session()->has('notify')) {
            $notify = session('notify');
            $this->dispatch('notify', type: $notify['type'], message: $notify['message']);
        }
    }

    public function updatedJenisKapalId($value): void
    {
        $this->setSelectedJenisKapalId($value);
        $this->resetPage();

        // Dispatch real-time update for the chart
        $this->dispatchRealtimeUpdates();
    }

    public function updatedShowKurvaS($value): void
    {
        session(['laporan_mingguan_show_kurva_s' => $value]);

        if ($value && $this->jenisKapalId) {
            $this->dispatchRealtimeUpdates();
        }
    }

    private function dispatchRealtimeUpdates(): void
    {
        if (!$this->jenisKapalId) {
            $this->dispatch('kurva-s-updated', chartData: []);
            return;
        }

        $jenisKapal = JenisKapal::find($this->jenisKapalId);
        if (!$jenisKapal) {
            $this->dispatch('kurva-s-updated', chartData: []);
            return;
        }

        $chartData = app(KurvaSService::class)->getChartData($jenisKapal);
        $progressHistory = app(KurvaSService::class)->getProgressHistory($jenisKapal);

        // Get work groups for history table
        $workGroupsForHistory = \App\Models\KurvaSWorkGroup::where('jenis_kapal_id', $this->jenisKapalId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($wg) {
                return [
                    'work_group_id' => $wg->id,
                    'nama' => $wg->nama,
                    'bobot' => $wg->bobot,
                ];
            })
            ->toArray();

        // Calculate totals from progress history using service
        $totalRencana = null;
        $totalAktual = null;
        if (!empty($progressHistory) && !empty($workGroupsForHistory)) {
            $totals = app(KurvaSService::class)->calculateTotalsFromHistory($progressHistory, $workGroupsForHistory);
            $totalRencana = $totals['total_rencana'];
            $totalAktual = $totals['total_aktual'];
        }

        $this->dispatch('kurva-s-updated', chartData: $chartData);
        $this->dispatch('progress-history-updated', history: $progressHistory);
        $this->dispatch('work-groups-updated', workGroups: $workGroupsForHistory);
        $this->dispatch('totals-updated', totalRencana: $totalRencana, totalAktual: $totalAktual);
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

    public function render(LaporanMingguanService $service, KurvaSService $kurvaSService)
    {
        $chartData = [];
        $progressHistory = [];
        $workGroupsForHistory = [];
        $totalRencana = null;
        $totalAktual = null;

        if ($this->showKurvaS && $this->jenisKapalId) {
            $jenisKapal = JenisKapal::find($this->jenisKapalId);
            if ($jenisKapal) {
                $chartData = $kurvaSService->getChartData($jenisKapal);
                $progressHistory = $kurvaSService->getProgressHistory($jenisKapal);
                
                // Get work groups for history table
                $workGroupsForHistory = \App\Models\KurvaSWorkGroup::where('jenis_kapal_id', $this->jenisKapalId)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function ($wg) {
                        return [
                            'work_group_id' => $wg->id,
                            'nama' => $wg->nama,
                            'bobot' => $wg->bobot,
                        ];
                    })
                    ->toArray();
                
                // Calculate totals from progress history
                if (!empty($progressHistory) && !empty($workGroupsForHistory)) {
                    $totalRencana = 0;
                    $totalAktual = 0;
                    
                    foreach ($progressHistory as $hist) {
                        // Calculate total rencana for this week
                        if (!empty($hist['plans'])) {
                            foreach ($workGroupsForHistory as $wg) {
                                $wgId = $wg['work_group_id'];
                                $plan = $hist['plans'][$wgId] ?? 0;
                                $totalRencana += (float)$plan * (float)$wg['bobot'] / 100;
                            }
                        }
                        
                        // Calculate total aktual for this week
                        if (!empty($hist['progress'])) {
                            foreach ($workGroupsForHistory as $wg) {
                                $wgId = $wg['work_group_id'];
                                $actual = $hist['progress'][$wgId] ?? 0;
                                $totalAktual += (float)$actual * (float)$wg['bobot'] / 100;
                            }
                        }
                    }
                    
                    $totalRencana = round($totalRencana, 2);
                    $totalAktual = round($totalAktual, 2);
                }
            }
        }

        return view('livewire.laporan-mingguan.laporan-mingguan-index', [
            'laporanList' => $service->getFiltered(
                $this->search,
                $this->jenisKapalId,
                $this->perPage
            ),
            'jenisKapalList' => $this->getJenisKapalList(),
            'kurvaSChartData'  => $chartData,
            'progressHistory' => $progressHistory,
            'workGroupsForHistory' => $workGroupsForHistory,
            'totalRencana'    => $totalRencana,
            'totalAktual'     => $totalAktual,
            'selectedJenisKapal' => $this->jenisKapalId ? JenisKapal::find($this->jenisKapalId) : null,
        ]);
    }
}
