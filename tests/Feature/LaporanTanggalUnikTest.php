<?php

namespace Tests\Feature;

use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanHarian\LaporanHarianEdit;
use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\User;
use App\Services\LaporanMingguanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanTanggalUnikTest extends TestCase
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

    private function dataKapalTanpaLaporanHarian(): DataKapal
    {
        $used = LaporanHarian::pluck('data_kapal_id')->unique();
        $dataKapal = DataKapal::whereNotIn('id', $used)->first();

        return $dataKapal ?? DataKapal::firstOrFail();
    }

    public function test_create_harian_rejects_tanggal_yang_sudah_dipakai(): void
    {
        $existing = LaporanHarian::firstOrFail();
        $dataKapal = $existing->dataKapal;

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $dataKapal])
            ->set('items.0.tanggal_laporan', $existing->tanggal_laporan->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['items.0.tanggal_laporan']);
    }

    public function test_create_harian_rejects_tanggal_ganda_antar_tab(): void
    {
        $dataKapal = $this->dataKapalTanpaLaporanHarian();

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $dataKapal])
            ->call('addItem');

        $tanggal = $component->get('items.0.tanggal_laporan');

        $component
            ->set('items.1.tanggal_laporan', $tanggal)
            ->call('save')
            ->assertHasErrors(['items.1.tanggal_laporan' => 'distinct']);
    }

    public function test_create_harian_sukses_untuk_tanggal_yang_belum_dipakai(): void
    {
        $existing = LaporanHarian::firstOrFail();
        $dataKapal = $existing->dataKapal;

        // Cari tanggal kosong di masa lalu (tanggal harus <= hari ini)
        $tanggal = now()->subDay();
        while (LaporanHarian::where('data_kapal_id', $dataKapal->id)
            ->whereDate('tanggal_laporan', $tanggal)
            ->exists()) {
            $tanggal->subDay();
        }
        $tanggalBaru = $tanggal->format('Y-m-d');

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $dataKapal])
            ->set('items.0.tanggal_laporan', $tanggalBaru)
            ->call('save');

        $this->assertTrue(
            LaporanHarian::where('data_kapal_id', $dataKapal->id)
                ->whereDate('tanggal_laporan', $tanggalBaru)
                ->exists()
        );
    }

    public function test_create_harian_rejects_tanggal_masa_depan(): void
    {
        $dataKapal = $this->dataKapalTanpaLaporanHarian();

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $dataKapal])
            ->set('items.0.tanggal_laporan', now()->addDay()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['items.0.tanggal_laporan' => 'before_or_equal']);
    }

    public function test_edit_harian_boleh_memakai_tanggal_sendiri_tapi_bukan_milik_laporan_lain(): void
    {
        $laporans = LaporanHarian::all()->groupBy('data_kapal_id')->first(fn ($g) => $g->count() > 1)
            ?? collect();

        if ($laporans->count() < 2) {
            $base = LaporanHarian::firstOrFail();
            $laporans = collect([$base, LaporanHarian::create([
                'user_id' => $base->user_id,
                'data_kapal_id' => $base->data_kapal_id,
                'tanggal_laporan' => $base->tanggal_laporan->copy()->addDay(),
            ])]);
        }

        [$laporanA, $laporanB] = $laporans->values();
        $dataKapal = $laporanA->dataKapal;

        // Simpan dengan tanggal sendiri -> tidak ada error unique
        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['dataKapal' => $dataKapal, 'laporanHarian' => $laporanA])
            ->call('save')
            ->assertHasNoErrors(['tanggal_laporan' => 'unique']);

        // Simpan dengan tanggal milik laporan lain -> error unique
        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['dataKapal' => $dataKapal, 'laporanHarian' => $laporanA])
            ->set('tanggal_laporan', $laporanB->tanggal_laporan->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['tanggal_laporan']);
    }

    public function test_index_harian_menandai_tanggal_ganda(): void
    {
        $base = LaporanHarian::firstOrFail();
        $dataKapal = $base->dataKapal;

        // Simulasi duplikat legacy (bypass validasi form)
        LaporanHarian::create([
            'user_id' => $base->user_id,
            'data_kapal_id' => $dataKapal->id,
            'tanggal_laporan' => $base->tanggal_laporan,
        ]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\LaporanHarian\LaporanHarianIndex::class, ['dataKapal' => $dataKapal])
            ->set('search', $base->tanggal_laporan->format('Y-m-d'))
            ->assertSee('Ganda');
    }

    public function test_create_mingguan_meminta_konfirmasi_saat_hari_sudah_diklaim_minggu_lain(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $dataKapal = $harian->dataKapal;

        // Klaim hari ini ke laporan mingguan yang sudah ada
        $mingguanLama = LaporanMingguan::create([
            'user_id' => $harian->user_id,
            'data_kapal_id' => $dataKapal->id,
            'minggu_ke' => 91,
            'tanggal_laporan' => $harian->tanggal_laporan,
            'periode_mulai' => $harian->tanggal_laporan,
            'periode_selesai' => $harian->tanggal_laporan,
        ]);
        $mingguanLama->laporanHarian()->attach($harian->id);

        // Seeder bisa membuat minggu_ke yang sama — lacak id, bukan query unscoped
        $lastId = (int) LaporanMingguan::max('id');

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $dataKapal])
            ->set('minggu_ke', 92)
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('periode_mulai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('periode_selesai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('laporan_harian_ids', [$harian->id])
            ->call('save')
            ->assertSet('showReassignModal', true);

        // Belum ada laporan mingguan baru yang dibuat
        $this->assertSame($lastId, (int) LaporanMingguan::max('id'));

        // Konfirmasi -> hari dipindahkan ke mingguan baru
        $component->call('saveWithReassign');

        $mingguanBaru = LaporanMingguan::where('id', '>', $lastId)->sole();
        $this->assertSame(92, $mingguanBaru->minggu_ke);

        $this->assertDatabaseHas('laporan_mingguan_harian', [
            'laporan_mingguan_id' => $mingguanBaru->id,
            'laporan_harian_id' => $harian->id,
        ]);
        $this->assertDatabaseMissing('laporan_mingguan_harian', [
            'laporan_mingguan_id' => $mingguanLama->id,
            'laporan_harian_id' => $harian->id,
        ]);
    }

    public function test_create_mingguan_tanpa_konflik_tidak_memunculkan_modal(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $dataKapal = $harian->dataKapal;

        // Pastikan hari ini belum diklaim mingguan manapun
        DB::table('laporan_mingguan_harian')->where('laporan_harian_id', $harian->id)->delete();

        Livewire::actingAs($this->admin())
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $dataKapal])
            ->set('minggu_ke', 93)
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('periode_mulai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('periode_selesai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('laporan_harian_ids', [$harian->id])
            ->call('save')
            ->assertSet('showReassignModal', false);

        // Scope ke record yang baru dibuat (seeder bisa punya minggu_ke sama)
        $mingguanBaru = LaporanMingguan::latest('id')->firstOrFail();
        $this->assertSame(93, $mingguanBaru->minggu_ke);
        $this->assertSame($dataKapal->id, $mingguanBaru->data_kapal_id);
    }

    public function test_index_mingguan_menampilkan_tanggal_harian_tercakup(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $dataKapal = $harian->dataKapal;

        $mingguan = LaporanMingguan::create([
            'user_id' => $harian->user_id,
            'data_kapal_id' => $dataKapal->id,
            'minggu_ke' => 94,
            'tanggal_laporan' => $harian->tanggal_laporan,
        ]);
        $mingguan->laporanHarian()->attach($harian->id);

        // Filter index ke mingguan yang dibuat (data seeder mengisi halaman 1)
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\LaporanMingguan\LaporanMingguanIndex::class, ['dataKapal' => $dataKapal])
            ->set('search', '94')
            ->assertSee($harian->tanggal_laporan->translatedFormat('d M'));
    }

    public function test_service_find_harian_conflicts_dan_release(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $dataKapal = $harian->dataKapal;
        $service = app(LaporanMingguanService::class);

        $mingguan = LaporanMingguan::create([
            'user_id' => $harian->user_id,
            'data_kapal_id' => $dataKapal->id,
            'minggu_ke' => 95,
            'tanggal_laporan' => $harian->tanggal_laporan,
        ]);
        $mingguan->laporanHarian()->attach($harian->id);

        $conflicts = $service->findHarianConflicts([$harian->id]);
        $this->assertCount(1, $conflicts);
        $this->assertSame($mingguan->id, $conflicts->first()['mingguan_id']);
        $this->assertCount(1, $conflicts->first()['dates']);

        // Dikecualikan untuk mingguan itu sendiri -> tidak konflik
        $this->assertTrue($service->findHarianConflicts([$harian->id], $mingguan->id)->isEmpty());

        // Release -> pivot terhapus
        $service->releaseHarianFromOtherMingguan([$harian->id], null);
        $this->assertDatabaseMissing('laporan_mingguan_harian', ['laporan_harian_id' => $harian->id]);
    }
}
