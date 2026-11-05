<?php

namespace App\Livewire\LaporanBulanan;

use App\Jobs\GenerateLaporanBulananJob;
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

    public bool $showRegenerateConfirm = false;

    public bool $showDeleteDocConfirm = false;

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

    public function confirmRegenerate(): void
    {
        $this->authorize('generateWord', $this->laporan);

        if ($this->laporan->isDocCompleted()) {
            $this->showRegenerateConfirm = true;

            return;
        }

        $this->generateWord();
    }

    public function cancelRegenerate(): void
    {
        $this->showRegenerateConfirm = false;
    }

    public function generateWord(): void
    {
        $this->showRegenerateConfirm = false;
        $this->authorize('generateWord', $this->laporan);

        if ($this->laporan->isDocProcessing()) {
            $this->notifyWarning('Dokumen sedang dalam proses generate. Mohon tunggu.');

            return;
        }

        try {
            // Hapus file lama jika ada (untuk generate ulang)
            if ($this->laporan->doc_path) {
                $oldPath = storage_path('app/'.$this->laporan->doc_path);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $this->laporan->update([
                'doc_status' => 'pending',
                'doc_error' => null,
            ]);

            GenerateLaporanBulananJob::dispatch($this->laporan);

            $this->laporan->refresh();

            $this->notifySuccess('Proses generate dokumen Word dimulai. Halaman akan otomatis diperbarui.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal memulai proses generate. Silakan coba lagi.');
        }
    }

    public function refreshDocStatus(): void
    {
        $this->laporan->refresh();

        if ($this->laporan->isDocCompleted()) {
            $this->notifySuccess('Dokumen Word berhasil digenerate! Silakan download.');
        } elseif ($this->laporan->isDocFailed()) {
            $this->notifyError('Generate dokumen gagal. Silakan coba lagi.');
        }
    }

    public function confirmDeleteDoc(): void
    {
        $this->authorize('generateWord', $this->laporan);
        $this->showDeleteDocConfirm = true;
    }

    public function cancelDeleteDoc(): void
    {
        $this->showDeleteDocConfirm = false;
    }

    public function deleteDoc(): void
    {
        $this->showDeleteDocConfirm = false;
        $this->authorize('generateWord', $this->laporan);

        try {
            if ($this->laporan->doc_path) {
                $path = storage_path('app/'.$this->laporan->doc_path);
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $this->laporan->update([
                'doc_path' => null,
                'doc_name' => null,
                'doc_status' => null,
                'doc_generated_at' => null,
                'doc_error' => null,
            ]);

            $this->laporan->refresh();
            $this->notifySuccess('Dokumen Word berhasil dihapus.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal menghapus dokumen. Silakan coba lagi.');
        }
    }

    public function downloadWord()
    {
        $this->authorize('downloadWord', $this->laporan);

        if (! $this->laporan->hasDoc() || ! $this->laporan->isDocCompleted()) {
            $this->notifyError('Dokumen tidak tersedia.');

            return;
        }

        $filePath = storage_path('app/'.$this->laporan->doc_path);
        if (! file_exists($filePath)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return response()->download($filePath, $this->laporan->doc_name);
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
