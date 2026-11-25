<?php

namespace App\Livewire\Traits;

use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Services\LaporanCompletenessService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait ChecksLaporanCompleteness
{
    #[Locked]
    public array $missingReports = [];

    public bool $showMissingReports = false;

    protected function checkSourceCompleteness(): bool
    {
        $this->authorize('generateWord', $this->laporan);
        $this->authorizeDataKapalAccess($this->dataKapal);
        try {
            $this->missingReports = app(LaporanCompletenessService::class)->missing($this->laporan);
        } catch (ValidationException $e) {
            $this->notifyWarning($e->validator->errors()->first());

            return false;
        }
        $this->showMissingReports = $this->missingReports !== [];

        return ! $this->showMissingReports;
    }

    public function closeMissingReports(): void
    {
        $this->showMissingReports = false;
        $this->missingReports = [];
    }

    public function createMissingReport(int $index): mixed
    {
        $this->authorize('generateWord', $this->laporan);
        $this->authorizeDataKapalAccess($this->dataKapal);
        $item = $this->missingReports[$index] ?? null;
        abort_unless($item, 404);
        $model = $item['type'] === 'harian' ? LaporanHarian::class : LaporanMingguan::class;
        $this->authorize('create', $model);
        $missing = app(LaporanCompletenessService::class)->missing($this->laporan);
        $current = collect($missing)->first(fn ($row) => $row['type'] === $item['type'] && $row['value'] === $item['value']);
        if (! $current) {
            $this->checkSourceCompleteness();
            $this->notifyInfo('Laporan tersebut sudah tersedia. Daftar kekurangan telah diperbarui.');

            return null;
        }
        if (! $current['can_create']) {
            $this->notifyWarning('Tanggal pelaporan belum tiba. Laporan harian tidak dapat dibuat untuk tanggal masa depan.');

            return null;
        }
        $this->closeMissingReports();

        return $this->redirect(route('laporan-'.$item['type'].'.create', [
            'dataKapal' => $this->dataKapal,
            $item['type'] === 'harian' ? 'tanggal' : 'minggu' => $item['value'],
        ]), navigate: true);
    }
}
