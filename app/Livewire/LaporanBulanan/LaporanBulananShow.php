<?php

namespace App\Livewire\LaporanBulanan;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use App\Services\KurvaSService;
use App\Services\LaporanBulananService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Bulanan'])]
class LaporanBulananShow extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification;

    public LaporanBulanan $laporan;

    public DataKapal $dataKapal;

    public function mount(DataKapal $dataKapal, LaporanBulanan $laporanBulanan): void
    {
        $this->authorize('view', $laporanBulanan);
        $this->authorizeDataKapalAccess($dataKapal);
        abort_unless($laporanBulanan->data_kapal_id === $dataKapal->id, 404);

        $this->dataKapal = $dataKapal;
        $this->laporan = $laporanBulanan->load([
            'user',
            'dataKapal.company',
            'dataKapal.galangan',
            'laporanExternal',
        ]);
    }

    public function refreshExternalFileStatus(): void
    {
        $this->laporan->load('laporanExternal');
    }

    public function hasProcessingExternalFiles(): bool
    {
        return $this->laporan->laporanExternal->contains(function ($external) {
            return $external->isFileProcessing();
        });
    }

    public function downloadExternalFile(int $externalId)
    {
        $this->authorize('view', $this->laporan);

        $external = $this->laporan->laporanExternal->find($externalId);

        if (! $external || ! $external->hasFile() || ! $external->isFileCompleted()) {
            $this->notifyError('File tidak tersedia.');

            return;
        }

        if (! Storage::disk('local')->exists($external->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        $originalFileName = $external->file_name;
        $title = $external->judul ?? '';
        $downloadFileName = ! empty($title)
            ? $title.'.'.pathinfo($originalFileName, PATHINFO_EXTENSION)
            : $originalFileName;

        return response()->download(
            Storage::disk('local')->path($external->file_path),
            $downloadFileName
        );
    }

    public function render(LaporanBulananService $service, KurvaSService $kurvaSService)
    {
        $chartData = [];
        $totalRencana = null;
        $totalAktual = null;
        $progressHistory = [];
        $workGroupsForHistory = [];

        if ($this->laporan->dataKapal) {
            $chartData = $kurvaSService->getMonthlyChartData($this->laporan->dataKapal);
            $progressHistory = $kurvaSService->getMonthlyProgressHistory($this->laporan->dataKapal);

            $workGroupsForHistory = KurvaSWorkGroup::where('data_kapal_id', $this->laporan->data_kapal_id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($wg) => [
                    'work_group_id' => $wg->id,
                    'nama' => $wg->nama,
                    'bobot' => $wg->bobot,
                ])
                ->toArray();

            if (! empty($progressHistory) && ! empty($workGroupsForHistory)) {
                $totals = $kurvaSService->calculateTotalsFromHistory($progressHistory, $workGroupsForHistory);
                $totalRencana = $totals['total_rencana'];
                $totalAktual = $totals['total_aktual'];
            }
        }

        return view('livewire.laporan-bulanan.laporan-bulanan-show', [
            'laporanMingguanBulan' => $service->getMingguanBulan($this->laporan),
            'kurvaSChartData' => $chartData,
            'dataKapalNama' => $this->laporan->dataKapal?->nama,
            'totalRencana' => $totalRencana,
            'totalAktual' => $totalAktual,
            'progressHistory' => $progressHistory,
            'workGroupsForHistory' => $workGroupsForHistory,
        ]);
    }
}
