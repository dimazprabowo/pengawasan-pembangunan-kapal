<?php

namespace Tests\Feature;

use App\Enums\RapatJenis;
use App\Livewire\MasterData\DataProyekManagement;
use App\Models\DataKapal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataProyekTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::firstOrFail();
    }

    private function kapal(): DataKapal
    {
        return DataKapal::firstOrFail();
    }

    public function test_tab_persists_from_query_string(): void
    {
        Livewire::actingAs($this->admin())
            ->withQueryParams(['tab' => 'rapat'])
            ->test(DataProyekManagement::class, ['dataKapal' => $this->kapal()])
            ->assertSet('tab', 'rapat');
    }

    public function test_invalid_tab_from_query_string_falls_back_to_drawing(): void
    {
        Livewire::actingAs($this->admin())
            ->withQueryParams(['tab' => 'ngawur'])
            ->test(DataProyekManagement::class, ['dataKapal' => $this->kapal()])
            ->assertSet('tab', 'drawing');
    }

    public function test_set_tab_ignores_unknown_value(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DataProyekManagement::class, ['dataKapal' => $this->kapal()])
            ->call('setTab', 'rapat')
            ->assertSet('tab', 'rapat')
            ->call('setTab', 'ngawur')
            ->assertSet('tab', 'rapat');
    }

    public function test_rapat_tab_filters_by_jenis(): void
    {
        $kapal = $this->kapal();
        $kapal->rapats()->create([
            'tanggal' => now(), 'jenis' => RapatJenis::Mingguan, 'topik' => 'TopikFilterMingguanXYZ',
        ]);
        $kapal->rapats()->create([
            'tanggal' => now(), 'jenis' => RapatJenis::Bulanan, 'topik' => 'TopikFilterBulananXYZ',
        ]);

        Livewire::actingAs($this->admin())
            ->test(DataProyekManagement::class, ['dataKapal' => $kapal])
            ->call('setTab', 'rapat')
            ->set('rapatJenisFilter', RapatJenis::Mingguan->value)
            ->assertSee('TopikFilterMingguanXYZ')
            ->assertDontSee('TopikFilterBulananXYZ');
    }

    public function test_filters_reset_when_switching_tab(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DataProyekManagement::class, ['dataKapal' => $this->kapal()])
            ->call('setTab', 'rapat')
            ->set('rapatJenisFilter', RapatJenis::Mingguan->value)
            ->call('setTab', 'material')
            ->assertSet('rapatJenisFilter', '');
    }
}
