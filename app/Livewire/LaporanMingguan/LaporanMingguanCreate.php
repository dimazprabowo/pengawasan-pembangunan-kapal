<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\LaporanMingguan;
use App\Services\LaporanMingguanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Tambah Laporan Mingguan'])]
class LaporanMingguanCreate extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?int $jenis_kapal_id = null;
    public string $judul = '';
    public string $tanggal_laporan = '';

    public function mount(): void
    {
        $this->authorize('create', LaporanMingguan::class);
        $this->jenis_kapal_id = session('laporan_harian_jenis_kapal_id');
        $this->tanggal_laporan = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'jenis_kapal_id' => 'nullable|exists:jenis_kapal,id',
            'judul' => 'required|string|max:255',
            'tanggal_laporan' => 'required|date',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'jenis_kapal_id' => 'jenis kapal',
            'judul' => 'judul laporan',
            'tanggal_laporan' => 'tanggal laporan',
        ];
    }

    public function save(LaporanMingguanService $service): void
    {
        $this->authorize('create', LaporanMingguan::class);
        
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'user_id' => auth()->id(),
                'jenis_kapal_id' => $this->jenis_kapal_id,
                'judul' => $this->judul,
                'tanggal_laporan' => $this->tanggal_laporan,
            ];

            $service->create($data);

            session()->flash('notify', [
                'type' => 'success',
                'message' => 'Laporan mingguan berhasil ditambahkan!',
            ]);

            $this->redirect(route('laporan-mingguan.index'), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-create', [
            'jenisKapalList' => JenisKapal::with(['company', 'galangan'])->get(),
        ]);
    }
}
