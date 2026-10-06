<?php

namespace Tests\Feature;

use App\Models\JenisKapal;
use App\Models\LaporanHarian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_picker_filters_by_company(): void
    {
        $kapalA = JenisKapal::where('company_id', 1)->firstOrFail();
        $companyB = \App\Models\Company::findOrFail(2);
        $kapalB = JenisKapal::create([
            'company_id' => $companyB->id,
            'nama' => 'Kapal Unik Filter Test',
            'status' => \App\Enums\JenisKapalStatus::Active,
        ]);

        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Laporan\LaporanIndex::class)
            ->set('companyFilter', 1)
            ->assertSee($kapalA->nama)
            ->assertDontSee($kapalB->nama);
    }

    public function test_picker_exports_excel_and_pdf(): void
    {
        \Livewire\Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Laporan\LaporanIndex::class)
            ->call('exportExcel')
            ->assertOk()
            ->call('exportPdf')
            ->assertOk();
    }

    public function test_picker_always_renders_after_visiting_jenis_kapal_context(): void
    {
        $jenisKapal = JenisKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.index', $jenisKapal))
            ->assertOk();

        $this->get('/laporan')
            ->assertOk()
            ->assertSee('Manajemen Laporan');
    }

    public function test_laporan_harian_index_renders_in_jenis_kapal_context(): void
    {
        $jenisKapal = JenisKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.index', $jenisKapal))
            ->assertOk()
            ->assertSee($jenisKapal->nama);
    }

    public function test_laporan_mingguan_index_renders_in_jenis_kapal_context(): void
    {
        $jenisKapal = JenisKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-mingguan.index', $jenisKapal))
            ->assertOk()
            ->assertSee($jenisKapal->nama);
    }

    public function test_laporan_harian_show_create_edit_render_in_context(): void
    {
        $laporan = LaporanHarian::firstOrFail();
        $jenisKapal = $laporan->jenisKapal;

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.show', [$jenisKapal, $laporan]))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.create', $jenisKapal))
            ->assertOk()
            ->assertSee($jenisKapal->nama);

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.edit', [$jenisKapal, $laporan]))
            ->assertOk();
    }

    public function test_laporan_from_different_jenis_kapal_returns_404(): void
    {
        $laporan = LaporanHarian::firstOrFail();
        $wrongKapal = JenisKapal::where('id', '!=', $laporan->jenis_kapal_id)->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('laporan-harian.show', [$wrongKapal, $laporan]))
            ->assertNotFound();
    }

    public function test_jenis_kapal_outside_user_company_scope_is_forbidden(): void
    {
        $company = \App\Models\Company::create([
            'code' => 'OTHER',
            'name' => 'PT Other Company',
            'status' => 'active',
        ]);

        $scopedUser = User::factory()->create([
            'company_id' => $company->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $scopedUser->givePermissionTo(['laporan_view']);

        $jenisKapal = JenisKapal::firstOrFail();

        $this->actingAs($scopedUser)
            ->get(route('laporan-harian.index', $jenisKapal))
            ->assertForbidden();
    }
}
