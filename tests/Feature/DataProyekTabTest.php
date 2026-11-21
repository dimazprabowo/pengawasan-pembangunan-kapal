<?php

namespace Tests\Feature;

use App\Enums\RapatJenis;
use App\Livewire\MasterData\DataProyekManagement;
use App\Livewire\MasterData\ProjectRapatManagement;
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
        $mingguanId = \App\Models\RapatJenis::where('nama', RapatJenis::Mingguan->value)->value('id');
        $bulananId = \App\Models\RapatJenis::where('nama', RapatJenis::Bulanan->value)->value('id');
        $mingguan = $kapal->rapats()->create([
            'tanggal' => now(), 'rapat_jenis_id' => $mingguanId, 'topik' => 'TopikFilterMingguanXYZ',
        ]);
        $bulanan = $kapal->rapats()->create([
            'tanggal' => now(), 'rapat_jenis_id' => $bulananId, 'topik' => 'TopikFilterBulananXYZ',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(ProjectRapatManagement::class, ['dataKapal' => $kapal])
            ->set('jenisFilter', $mingguanId);

        $keys = array_keys($component->get('drafts'));
        $this->assertContains('row-'.$mingguan->id, $keys);
        $this->assertNotContains('row-'.$bulanan->id, $keys);
    }

    public function test_rapat_filters_reset(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ProjectRapatManagement::class, ['dataKapal' => $this->kapal()])
            ->set('jenisFilter', \App\Models\RapatJenis::where('nama', RapatJenis::Mingguan->value)->value('id'))
            ->call('resetFilters')
            ->assertSet('jenisFilter', '');
    }

    public function test_rapat_excel_like_row_crud(): void
    {
        $kapal = $this->kapal();

        $component = Livewire::actingAs($this->admin())
            ->test(ProjectRapatManagement::class, ['dataKapal' => $kapal])
            ->call('addRow');

        $key = collect($component->get('drafts'))->keys()->first(fn ($k) => str_starts_with($k, 'new-'));

        $component
            ->set("drafts.$key.tanggal", '2026-01-15')
            ->set("drafts.$key.rapat_jenis_id", \App\Models\RapatJenis::where('nama', RapatJenis::Bulanan->value)->value('id'))
            ->set("drafts.$key.lokasi", 'Ruang QC')
            ->set("drafts.$key.topik", 'Pembahasan progres')
            ->call('saveRow', $key);

        $rapat = $kapal->rapats()->where('topik', 'Pembahasan progres')->sole();
        $this->assertSame('Pembahasan progres', $rapat->topik);
        $this->assertSame(RapatJenis::Bulanan->value, $rapat->jenis->nama);
        $this->assertSame('2026-01-15', $rapat->tanggal->format('Y-m-d'));

        $component
            ->set("drafts.row-{$rapat->id}.topik", 'Topik direvisi')
            ->call('saveRow', 'row-'.$rapat->id);

        $this->assertSame('Topik direvisi', $rapat->fresh()->topik);

        $component->call('confirmDelete', $rapat->id)->call('delete');
        $this->assertSoftDeleted('rapat', ['id' => $rapat->id]);
    }

    public function test_tab_switch_does_not_notify(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DataProyekManagement::class, ['dataKapal' => $this->kapal()])
            ->call('setTab', 'rapat')
            ->assertNotDispatched('notify')
            ->call('setTab', 'material')
            ->assertNotDispatched('notify');
    }
}
