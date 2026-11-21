<?php

namespace Tests\Feature;

use App\Enums\DataKapalStatus;
use App\Livewire\Laporan\LaporanIndex;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanNavigationTest extends TestCase
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

    public function test_picker_renders_when_no_ship_selected(): void
    {
        $this->actingAs($this->admin())
            ->get('/laporan')
            ->assertOk()
            ->assertSee('Manajemen Laporan');
    }

    public function test_picker_filters_by_owner(): void
    {
        $kapalA = DataKapal::where('owner_id', 1)->firstOrFail();
        $ownerB = Owner::findOrFail(2);
        $kapalB = DataKapal::create([
            'owner_id' => $ownerB->id,
            'nama' => 'Kapal Unik Filter Test',
            'status' => DataKapalStatus::Active,
        ]);

        Livewire::actingAs($this->admin())
            ->test(LaporanIndex::class)
            ->set('ownerFilter', 1)
            ->assertSee($kapalA->nama)
            ->assertDontSee($kapalB->nama);
    }

    public function test_picker_exports_excel_and_pdf(): void
    {
        Livewire::actingAs($this->admin())
            ->test(LaporanIndex::class)
            ->call('exportExcel')
            ->assertOk()
            ->call('exportPdf')
            ->assertOk();
    }

    public function test_picker_always_renders_after_visiting_data_kapal_context(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.index', $dataKapal))
            ->assertOk();

        $this->get('/laporan')
            ->assertOk()
            ->assertSee('Manajemen Laporan');
    }

    public function test_laporan_harian_index_renders_in_data_kapal_context(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.index', $dataKapal))
            ->assertOk()
            ->assertSee($dataKapal->nama);
    }

    public function test_laporan_mingguan_index_renders_in_data_kapal_context(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-mingguan.index', $dataKapal))
            ->assertOk()
            ->assertSee($dataKapal->nama);
    }

    public function test_laporan_harian_show_create_edit_render_in_context(): void
    {
        $laporan = LaporanHarian::firstOrFail();
        $dataKapal = $laporan->dataKapal;

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.show', [$dataKapal, $laporan]))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.create', $dataKapal))
            ->assertOk()
            ->assertSee($dataKapal->nama);

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.edit', [$dataKapal, $laporan]))
            ->assertOk();
    }

    public function test_laporan_from_different_data_kapal_returns_404(): void
    {
        $laporan = LaporanHarian::firstOrFail();
        $wrongKapal = DataKapal::where('id', '!=', $laporan->data_kapal_id)->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.show', [$wrongKapal, $laporan]))
            ->assertNotFound();
    }

    public function test_data_kapal_outside_user_owner_scope_is_forbidden(): void
    {
        $owner = Owner::create([
            'code' => 'OTHER',
            'name' => 'PT Other Owner',
            'status' => 'active',
        ]);

        $scopedUser = User::factory()->create([
            'owner_id' => $owner->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $scopedUser->givePermissionTo(['laporan_view']);

        $dataKapal = DataKapal::firstOrFail();

        $this->actingAs($scopedUser)
            ->get(route('laporan-harian.index', $dataKapal))
            ->assertForbidden();
    }
}
