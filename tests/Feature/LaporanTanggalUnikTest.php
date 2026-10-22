<?php

namespace Tests\Feature;

use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanHarian\LaporanHarianEdit;
use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Models\JenisKapal;
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

    private function jenisKapalTanpaLaporanHarian(): JenisKapal
    {
        $used = LaporanHarian::pluck('jenis_kapal_id')->unique();
        $jenisKapal = JenisKapal::whereNotIn('id', $used)->first();

        return $jenisKapal ?? JenisKapal::firstOrFail();
    }

    public function test_create_harian_rejects_tanggal_yang_sudah_dipakai(): void
    {
        $existing = LaporanHarian::firstOrFail();
        $jenisKapal = $existing->jenisKapal;

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('items.0.tanggal_laporan', $existing->tanggal_laporan->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['items.0.tanggal_laporan']);
    }

    public function test_create_harian_rejects_tanggal_ganda_antar_tab(): void
    {
        $jenisKapal = $this->jenisKapalTanpaLaporanHarian();

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['jenisKapal' => $jenisKapal])
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
        $jenisKapal = $existing->jenisKapal;

        // Cari tanggal kosong di masa lalu (tanggal harus <= hari ini)
        $tanggal = now()->subDay();
        while (LaporanHarian::where('jenis_kapal_id', $jenisKapal->id)
            ->whereDate('tanggal_laporan', $tanggal)
            ->exists()) {
            $tanggal->subDay();
        }
        $tanggalBaru = $tanggal->format('Y-m-d');

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('items.0.tanggal_laporan', $tanggalBaru)
            ->call('save');

        $this->assertTrue(
            LaporanHarian::where('jenis_kapal_id', $jenisKapal->id)
                ->whereDate('tanggal_laporan', $tanggalBaru)
                ->exists()
        );
    }

    public function test_create_harian_rejects_tanggal_masa_depan(): void
    {
        $jenisKapal = $this->jenisKapalTanpaLaporanHarian();

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('items.0.tanggal_laporan', now()->addDay()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['items.0.tanggal_laporan' => 'before_or_equal']);
    }

    public function test_edit_harian_boleh_memakai_tanggal_sendiri_tapi_bukan_milik_laporan_lain(): void
    {
        $laporans = LaporanHarian::all()->groupBy('jenis_kapal_id')->first(fn ($g) => $g->count() > 1)
            ?? collect();

        if ($laporans->count() < 2) {
            $base = LaporanHarian::firstOrFail();
            $laporans = collect([$base, LaporanHarian::create([
                'user_id' => $base->user_id,
                'jenis_kapal_id' => $base->jenis_kapal_id,
                'judul' => 'Laporan Tambahan',
                'tanggal_laporan' => $base->tanggal_laporan->copy()->addDay(),
            ])]);
        }

        [$laporanA, $laporanB] = $laporans->values();
        $jenisKapal = $laporanA->jenisKapal;

        // Simpan dengan tanggal sendiri -> tidak ada error unique
        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['jenisKapal' => $jenisKapal, 'laporanHarian' => $laporanA])
            ->call('save')
            ->assertHasNoErrors(['tanggal_laporan' => 'unique']);

        // Simpan dengan tanggal milik laporan lain -> error unique
        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['jenisKapal' => $jenisKapal, 'laporanHarian' => $laporanA])
            ->set('tanggal_laporan', $laporanB->tanggal_laporan->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['tanggal_laporan']);
    }

    public function test_index_harian_menandai_tanggal_ganda(): void
    {
        $base = LaporanHarian::firstOrFail();
        $jenisKapal = $base->jenisKapal;

        // Simulasi duplikat legacy (bypass validasi form)
        LaporanHarian::create([
            'user_id' => $base->user_id,
            'jenis_kapal_id' => $jenisKapal->id,
            'judul' => 'Duplikat Legacy',
            'tanggal_laporan' => $base->tanggal_laporan,
        ]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\LaporanHarian\LaporanHarianIndex::class, ['jenisKapal' => $jenisKapal])
            ->assertSee('Ganda');
    }

    public function test_create_mingguan_meminta_konfirmasi_saat_hari_sudah_diklaim_minggu_lain(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $jenisKapal = $harian->jenisKapal;

        // Klaim hari ini ke laporan mingguan yang sudah ada
        $mingguanLama = LaporanMingguan::create([
            'user_id' => $harian->user_id,
            'jenis_kapal_id' => $jenisKapal->id,
            'judul' => 'Mingguan Lama',
            'tanggal_laporan' => $harian->tanggal_laporan,
            'periode_mulai' => $harian->tanggal_laporan,
            'periode_selesai' => $harian->tanggal_laporan,
        ]);
        $mingguanLama->laporanHarian()->attach($harian->id);

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanMingguanCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('judul', 'Mingguan Baru')
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('periode_mulai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('periode_selesai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('laporan_harian_ids', [$harian->id])
            ->call('save')
            ->assertSet('showReassignModal', true);

        // Belum ada laporan mingguan baru yang dibuat
        $this->assertDatabaseMissing('laporan_mingguan', ['judul' => 'Mingguan Baru']);

        // Konfirmasi -> hari dipindahkan ke mingguan baru
        $component->call('saveWithReassign');

        $mingguanBaru = LaporanMingguan::where('judul', 'Mingguan Baru')->firstOrFail();

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
        $jenisKapal = $harian->jenisKapal;

        // Pastikan hari ini belum diklaim mingguan manapun
        DB::table('laporan_mingguan_harian')->where('laporan_harian_id', $harian->id)->delete();

        Livewire::actingAs($this->admin())
            ->test(LaporanMingguanCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('judul', 'Mingguan Bebas')
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('periode_mulai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('periode_selesai', $harian->tanggal_laporan->format('Y-m-d'))
            ->set('laporan_harian_ids', [$harian->id])
            ->call('save')
            ->assertSet('showReassignModal', false);

        $this->assertDatabaseHas('laporan_mingguan', ['judul' => 'Mingguan Bebas']);
    }

    public function test_index_mingguan_menampilkan_tanggal_harian_tercakup(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $jenisKapal = $harian->jenisKapal;

        $mingguan = LaporanMingguan::create([
            'user_id' => $harian->user_id,
            'jenis_kapal_id' => $jenisKapal->id,
            'judul' => 'Mingguan Dengan Hari',
            'tanggal_laporan' => $harian->tanggal_laporan,
        ]);
        $mingguan->laporanHarian()->attach($harian->id);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\LaporanMingguan\LaporanMingguanIndex::class, ['jenisKapal' => $jenisKapal])
            ->assertSee($harian->tanggal_laporan->translatedFormat('d M'));
    }

    public function test_service_find_harian_conflicts_dan_release(): void
    {
        $harian = LaporanHarian::firstOrFail();
        $jenisKapal = $harian->jenisKapal;
        $service = app(LaporanMingguanService::class);

        $mingguan = LaporanMingguan::create([
            'user_id' => $harian->user_id,
            'jenis_kapal_id' => $jenisKapal->id,
            'judul' => 'Mingguan Klaim',
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
