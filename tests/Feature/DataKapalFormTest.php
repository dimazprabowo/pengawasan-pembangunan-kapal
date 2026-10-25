<?php

namespace Tests\Feature;

use App\Livewire\MasterData\DataKapalForm;
use App\Models\DataKapal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataKapalFormTest extends TestCase
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

    public function test_index_renders_dengan_judul_data_kapal(): void
    {
        $this->actingAs($this->admin())
            ->get(route('master-data.data-kapal'))
            ->assertOk()
            ->assertSee('Data Kapal');
    }

    public function test_create_page_renders_dan_edit_memakai_route_key_terenkripsi(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('master-data.data-kapal.create'))
            ->assertOk()
            ->assertSee('Tambah Kapal');

        $editUrl = route('master-data.data-kapal.edit', $dataKapal);
        $this->assertStringNotContainsString('/'.$dataKapal->id.'/', $editUrl);

        $this->actingAs($this->admin())
            ->get($editUrl)
            ->assertOk()
            ->assertSee('Edit Kapal')
            ->assertSee($dataKapal->nama);
    }

    public function test_user_tanpa_izin_tidak_bisa_akses_halaman_form(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $dataKapal = DataKapal::firstOrFail();

        $this->actingAs($user)
            ->get(route('master-data.data-kapal.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('master-data.data-kapal.edit', $dataKapal))
            ->assertForbidden();
    }

    public function test_form_create_menyimpan_kapal_baru(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DataKapalForm::class)
            ->set('nama', 'KMP Uji Baru')
            ->set('deskripsi', 'Kapal uji coba')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('master-data.data-kapal'));

        $this->assertDatabaseHas('data_kapal', [
            'nama' => 'KMP Uji Baru',
            'status' => 'active',
        ]);
    }

    public function test_form_edit_mengupdate_kapal(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(DataKapalForm::class, ['dataKapal' => $dataKapal])
            ->assertSet('nama', $dataKapal->nama)
            ->assertSet('editMode', true)
            ->set('nama', 'Nama Diubah')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('master-data.data-kapal'));

        $this->assertDatabaseHas('data_kapal', [
            'id' => $dataKapal->id,
            'nama' => 'Nama Diubah',
        ]);
    }

    public function test_form_validasi_nama_wajib(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DataKapalForm::class)
            ->set('nama', '')
            ->call('save')
            ->assertHasErrors(['nama']);
    }

    public function test_form_menyimpan_spesifikasi_dinamis(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(DataKapalForm::class)
            ->set('nama', 'KMP Spek Uji')
            ->call('addSpesifikasi')
            ->call('addSpesifikasi')
            ->call('addSpesifikasi')
            ->set('spesifikasi.0.item', 'Year of Build')
            ->set('spesifikasi.0.keterangan', '2015')
            ->set('spesifikasi.1.item', 'Material')
            ->set('spesifikasi.1.keterangan', 'Steel')
            // baris ke-3 dibiarkan kosong total -> otomatis dibuang saat save
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('master-data.data-kapal'));

        $saved = DataKapal::where('nama', 'KMP Spek Uji')->firstOrFail();
        $this->assertSame(
            [
                ['item' => 'Year of Build', 'keterangan' => '2015'],
                ['item' => 'Material', 'keterangan' => 'Steel'],
            ],
            $saved->spesifikasi
        );
        $this->assertNotSame($dataKapal->id, $saved->id);
    }

    public function test_form_spesifikasi_item_duplikat_ditolak(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DataKapalForm::class)
            ->set('nama', 'KMP Spek Duplikat')
            ->call('addSpesifikasi')
            ->call('addSpesifikasi')
            ->set('spesifikasi.0.item', 'Material')
            ->set('spesifikasi.0.keterangan', 'Steel')
            ->set('spesifikasi.1.item', 'Material')
            ->set('spesifikasi.1.keterangan', 'Aluminium')
            ->call('save')
            ->assertHasErrors(['spesifikasi.1.item']);
    }

    public function test_form_edit_prefill_spesifikasi_tersimpan(): void
    {
        $dataKapal = DataKapal::firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(DataKapalForm::class, ['dataKapal' => $dataKapal])
            ->assertSet('editMode', true)
            ->assertSet('spesifikasi.0.item', 'Year of Build');
    }
}
