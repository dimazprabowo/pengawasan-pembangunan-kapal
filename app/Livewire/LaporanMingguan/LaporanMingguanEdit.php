<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Models\LaporanMingguan;
use App\Services\LaporanMingguanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Edit Laporan Mingguan'])]
class LaporanMingguanEdit extends Component
{
    use AuthorizesRequests, HasNotification;

    public LaporanMingguan $laporan;

    public ?int $jenis_kapal_id = null;
    public string $judul = '';
    public string $tanggal_laporan = '';

    public function mount(LaporanMingguan $laporanMingguan): void
    {
        $this->authorize('update', $laporanMingguan);
        $this->laporan = $laporanMingguan;
        $this->jenis_kapal_id = $laporanMingguan->jenis_kapal_id;
        $this->judul = $laporanMingguan->judul;
        $this->tanggal_laporan = $laporanMingguan->tanggal_laporan ? $laporanMingguan->tanggal_laporan->format('Y-m-d') : '';
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
        $this->authorize('update', $this->laporan);
        
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'jenis_kapal_id' => $this->jenis_kapal_id,
                'judul' => $this->judul,
                'tanggal_laporan' => $this->tanggal_laporan,
            ];

            $service->update($this->laporan, $data);

            session()->flash('notify', [
                'type' => 'success',
                'message' => 'Laporan mingguan berhasil diupdate!',
            ]);

            $this->redirect(route('laporan-mingguan.index'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengupdate laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-edit', [
            'jenisKapalList' => JenisKapal::with(['company', 'galangan'])->get(),
        ]);
    }
}
