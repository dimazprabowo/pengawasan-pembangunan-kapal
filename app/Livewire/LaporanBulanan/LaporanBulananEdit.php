<?php

namespace App\Livewire\LaporanBulanan;

use App\Jobs\ProcessLaporanExternal;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\LaporanExternal;
use App\Services\KontrakPeriodeService;
use App\Services\LaporanBulananService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Edit Laporan Bulanan'])]
class LaporanBulananEdit extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithFileUploads;

    public DataKapal $dataKapal;

    public LaporanBulanan $laporan;

    public string $tanggal_laporan = '';

    public ?int $bulan_ke = null;

    public string $periode_mulai = '';

    public string $periode_selesai = '';

    public string $ringkasan = '';

    public array $bulanOptions = [];

    public bool $hasKontrak = false;

    // Laporan External
    public array $laporanExternal = [];

    public bool $showDeleteExternalModal = false;

    public ?string $externalToDelete = null;

    public ?string $deletingExternalId = null;

    public function mount(DataKapal $dataKapal, LaporanBulanan $laporanBulanan): void
    {
        $this->authorize('update', $laporanBulanan);
        $this->authorizeDataKapalAccess($dataKapal);
        abort_unless($laporanBulanan->data_kapal_id === $dataKapal->id, 404);

        $this->dataKapal = $dataKapal;
        $this->laporan = $laporanBulanan;
        $this->hasKontrak = $dataKapal->hasKontrak();
        $this->tanggal_laporan = $laporanBulanan->tanggal_laporan?->format('Y-m-d') ?? '';
        $this->periode_mulai = $laporanBulanan->periode_mulai?->format('Y-m-d') ?? '';
        $this->periode_selesai = $laporanBulanan->periode_selesai?->format('Y-m-d') ?? '';
        $this->ringkasan = $laporanBulanan->ringkasan ?? '';
        $this->bulan_ke = $laporanBulanan->bulan_ke;
        $this->bulanOptions = app(KontrakPeriodeService::class)->getBulanOptions($dataKapal, $this->bulan_ke);

        $this->laporanExternal = $laporanBulanan->laporanExternal->map(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'file' => null,
                'existing_file_path' => $item->file_path,
                'existing_file_name' => $item->file_name,
                'existing_file_size' => $item->file_size,
                'file_status' => $item->file_status,
                'file_error' => $item->file_error,
            ];
        })->toArray();
    }

    protected function rules(): array
    {
        return [
            'tanggal_laporan' => 'required|date|before_or_equal:today',
            'bulan_ke' => 'required|integer|min:1',
            'periode_mulai' => 'required|date',
            'periode_selesai' => 'required|date|after_or_equal:periode_mulai',
            'ringkasan' => 'nullable|string|max:5000',
            'laporanExternal.*.judul' => 'required|string|max:255',
            'laporanExternal.*.deskripsi' => 'nullable|string|max:1000',
            'laporanExternal.*.file' => file_upload_validation_rule('laporan_external'),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'tanggal_laporan' => 'tanggal laporan',
            'bulan_ke' => 'bulan ke-',
            'periode_mulai' => 'periode mulai',
            'periode_selesai' => 'periode selesai',
            'ringkasan' => 'ringkasan',
            'laporanExternal.*.judul' => 'judul laporan external',
            'laporanExternal.*.deskripsi' => 'deskripsi laporan external',
            'laporanExternal.*.file' => 'file laporan external',
        ];
    }

    /**
     * Periode bulan turun otomatis dari kontrak begitu bulan ke- dipilih.
     */
    public function updatedBulanKe(): void
    {
        $periode = $this->bulan_ke
            ? app(KontrakPeriodeService::class)->getBulanPeriode($this->dataKapal, (int) $this->bulan_ke)
            : null;

        $this->periode_mulai = $periode ? $periode['mulai']->format('Y-m-d') : '';
        $this->periode_selesai = $periode ? $periode['selesai']->format('Y-m-d') : '';
    }

    // Laporan External Methods
    public function addExternalReport(): void
    {
        $this->laporanExternal[] = [
            'id' => 'temp_'.uniqid(),
            'judul' => '',
            'deskripsi' => '',
            'file' => null,
        ];
    }

    public function removeExternalFile(int $index): void
    {
        if (isset($this->laporanExternal[$index])) {
            $this->laporanExternal[$index]['file'] = null;
        }
    }

    public function removeExternalReport(string $tempId): void
    {
        if (is_numeric($tempId)) {
            $existingRecord = LaporanExternal::find($tempId);
            if ($existingRecord) {
                if ($existingRecord->file_path && Storage::disk('local')->exists($existingRecord->file_path)) {
                    Storage::disk('local')->delete($existingRecord->file_path);
                }
                $existingRecord->delete();
            }
        }

        $this->laporanExternal = array_values(array_filter($this->laporanExternal, function ($item) use ($tempId) {
            return ($item['id'] ?? '') !== $tempId;
        }));
    }

    public function confirmDeleteExternal(string $tempId): void
    {
        $this->externalToDelete = $tempId;
        $this->deletingExternalId = $tempId;
        $this->showDeleteExternalModal = true;
    }

    public function deleteExternalReport(): void
    {
        if ($this->externalToDelete) {
            $isExistingRecord = is_numeric($this->externalToDelete);
            $this->removeExternalReport($this->externalToDelete);

            if ($isExistingRecord) {
                $this->refreshLaporanExternal();
            }

            $this->externalToDelete = null;
            $this->deletingExternalId = null;
            $this->showDeleteExternalModal = false;
            $this->notifySuccess('Laporan external berhasil dihapus.');
        }
    }

    public function cancelDeleteExternal(): void
    {
        $this->externalToDelete = null;
        $this->deletingExternalId = null;
        $this->showDeleteExternalModal = false;
    }

    protected function refreshLaporanExternal(): void
    {
        $newItems = collect($this->laporanExternal)->filter(function ($item) {
            return isset($item['id']) && ! is_numeric($item['id']);
        })->values()->toArray();

        $this->laporan->load('laporanExternal');

        $existingItems = $this->laporan->laporanExternal->map(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'file' => null,
                'existing_file_path' => $item->file_path,
                'existing_file_name' => $item->file_name,
                'existing_file_size' => $item->file_size,
                'file_status' => $item->file_status,
                'file_error' => $item->file_error,
            ];
        })->toArray();

        $this->laporanExternal = array_merge($existingItems, $newItems);
    }

    public function refreshExternalFileStatus(): void
    {
        $this->refreshLaporanExternal();
    }

    public function hasProcessingExternalFiles(): bool
    {
        return collect($this->laporanExternal)->contains(function ($item) {
            return isset($item['file_status']) && in_array($item['file_status'], ['pending', 'processing']);
        });
    }

    public function downloadExternalFile(int $index)
    {
        $external = $this->laporanExternal[$index] ?? null;

        if (! $external || empty($external['existing_file_path']) || ! Storage::disk('local')->exists($external['existing_file_path'])) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        $originalFileName = $external['existing_file_name'];
        $title = $external['judul'] ?? '';
        $downloadFileName = ! empty($title)
            ? $title.'.'.pathinfo($originalFileName, PATHINFO_EXTENSION)
            : $originalFileName;

        return response()->download(
            Storage::disk('local')->path($external['existing_file_path']),
            $downloadFileName
        );
    }

    public function save(LaporanBulananService $service): void
    {
        $this->authorize('update', $this->laporan);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        if ($service->bulanTaken($this->dataKapal->id, (int) $this->bulan_ke, $this->laporan->id)) {
            $this->notifyError('Bulan ke-'.$this->bulan_ke.' sudah memiliki laporan bulanan.');

            return;
        }

        try {
            $service->update($this->laporan, [
                'tanggal_laporan' => $this->tanggal_laporan,
                'periode_mulai' => $this->periode_mulai ?: null,
                'periode_selesai' => $this->periode_selesai ?: null,
                'ringkasan' => $this->ringkasan ?: null,
                'bulan_ke' => $this->bulan_ke,
            ]);

            $this->updateLaporanExternal();

            $this->notifySuccess('Laporan bulanan berhasil diupdate!');

            $this->redirect(route('laporan-bulanan.index', $this->dataKapal), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    protected function updateLaporanExternal(): void
    {
        foreach ($this->laporanExternal as $external) {
            if (empty($external['judul'])) {
                continue;
            }

            $isExisting = is_numeric($external['id']);

            if ($isExisting) {
                $laporanExternal = LaporanExternal::find($external['id']);
                if (! $laporanExternal) {
                    continue;
                }
            } else {
                $laporanExternal = new LaporanExternal([
                    'laporan_bulanan_id' => $this->laporan->id,
                    'file_status' => 'pending',
                ]);
            }

            $laporanExternal->judul = $external['judul'];
            $laporanExternal->deskripsi = $external['deskripsi'] ?? null;

            if ($external['file']) {
                if ($laporanExternal->file_path && Storage::disk('local')->exists($laporanExternal->file_path)) {
                    Storage::disk('local')->delete($laporanExternal->file_path);
                }

                $tempPath = $external['file']->store('temp/laporan-external', 'local');
                $laporanExternal->file_name = $external['file']->getClientOriginalName();
                $laporanExternal->file_status = 'pending';
                $laporanExternal->file_processed_at = null;
                $laporanExternal->file_error = null;
                $laporanExternal->save();

                ProcessLaporanExternal::dispatch($laporanExternal->id, $tempPath);

                continue;
            }

            if (! $isExisting) {
                $laporanExternal->file_status = 'pending';
            }

            $laporanExternal->save();
        }
    }

    public function cancel()
    {
        return $this->redirect(route('laporan-bulanan.index', $this->dataKapal), navigate: true);
    }

    public function render()
    {
        return view('livewire.laporan-bulanan.laporan-bulanan-edit');
    }
}
