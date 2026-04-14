<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\LaporanMingguan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Mingguan'])]
class LaporanMingguanShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public LaporanMingguan $laporan;

    public function mount(LaporanMingguan $laporanMingguan): void
    {
        $this->authorize('view', $laporanMingguan);
        $this->loadLaporan($laporanMingguan);
    }

    private function loadLaporan(LaporanMingguan $laporanMingguan): void
    {
        $this->laporan = $laporanMingguan->load(['user', 'jenisKapal.company', 'jenisKapal.galangan']);
    }

    public function render()
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-show');
    }
}
