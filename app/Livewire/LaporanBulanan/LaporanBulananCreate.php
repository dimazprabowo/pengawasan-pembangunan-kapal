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
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Tambah Laporan Bulanan'])]
class LaporanBulananCreate extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithFileUploads;

    public DataKapal $dataKapal;

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

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('create', LaporanBulanan::class);
        $this->authorizeDataKapalAccess($dataKapal);

        $this->dataKapal = $dataKapal;
        $this->hasKontrak = $dataKapal->hasKontrak();
        $this->tanggal_laporan = now()->format('Y-m-d');
        $this->bulanOptions = app(KontrakPeriodeService::class)->getBulanOptions($dataKapal);
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
            $this->removeExternalReport($this->externalToDelete);
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

    public function save(LaporanBulananService $service): void
    {
        $this->authorize('create', LaporanBulanan::class);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        if ($service->bulanTaken($this->dataKapal->id, (int) $this->bulan_ke)) {
            $this->notifyError('Bulan ke-'.$this->bulan_ke.' sudah memiliki laporan bulanan.');

            return;
        }

        try {
            $laporan = $service->create([
                'user_id' => auth()->id(),
                'data_kapal_id' => $this->dataKapal->id,
                'tanggal_laporan' => $this->tanggal_laporan,
                'periode_mulai' => $this->periode_mulai ?: null,
                'periode_selesai' => $this->periode_selesai ?: null,
                'ringkasan' => $this->ringkasan ?: null,
                'bulan_ke' => $this->bulan_ke,
            ]);

            $this->saveLaporanExternal($laporan);

            $this->notifySuccess('Laporan bulanan berhasil ditambahkan!');

            $this->redirect(route('laporan-bulanan.index', $this->dataKapal), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    protected function saveLaporanExternal(LaporanBulanan $laporan): void
    {
        foreach ($this->laporanExternal as $external) {
            if (empty($external['judul'])) {
                continue;
            }

            $laporanExternal = new LaporanExternal([
                'laporan_bulanan_id' => $laporan->id,
                'judul' => $external['judul'],
                'deskripsi' => $external['deskripsi'] ?? null,
                'file_name' => $external['file'] ? $external['file']->getClientOriginalName() : null,
                'file_size' => null,
                'file_status' => 'pending',
            ]);

            $laporanExternal->save();

            if ($external['file']) {
                $tempPath = $external['file']->store('temp/laporan-external', 'local');
                ProcessLaporanExternal::dispatch($laporanExternal->id, $tempPath);
            }
        }
    }

    public function hasProcessingExternalFiles(): bool
    {
        return collect($this->laporanExternal)->contains(function ($item) {
            return isset($item['file_status']) && in_array($item['file_status'], ['pending', 'processing']);
        });
    }

    public function cancel()
    {
        return $this->redirect(route('laporan-bulanan.index', $this->dataKapal), navigate: true);
    }

    public function render()
    {
        return view('livewire.laporan-bulanan.laporan-bulanan-create');
    }
}
