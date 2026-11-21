<?php

namespace App\Livewire\MasterData;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shell tab Data Proyek per data kapal: drawing register, vendor,
 * material, pengujian, rapat, dan penandatangan — sumber data BAB 2 & 3
 * laporan bulanan.
 * Tiap tab di-render oleh komponen nested-nya sendiri.
 */
#[Layout('layouts.app', ['title' => 'Data Proyek'])]
class DataProyekManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter;

    public DataKapal $dataKapal;

    #[Url(as: 'tab')]
    public string $tab = 'drawing';

    private const TABS = ['penandatangan', 'drawing', 'vendor', 'material', 'pengujian', 'rapat'];

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', DrawingRegister::class);
        $this->authorizeDataKapalAccess($dataKapal);

        $this->dataKapal = $dataKapal;
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'drawing';
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::TABS, true) || $this->tab === $tab) {
            return;
        }

        $this->tab = $tab;
    }

    public function render()
    {
        return view('livewire.master-data.data-proyek-management');
    }
}
