<?php

namespace Tests\Feature;

use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MingguanReassignReproTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DataKapal $dataKapal;

    private \Carbon\Carbon $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::firstOrFail();
        // Kapal baru tanpa kontrak & tanpa Kurva-S: terisolasi dari data seeded
        // (seeder mengisi 100 minggu + realisasi acak yang bisa memicu blokir
        // anyExceedsBobot dan klaim harian pada kapal seeded).
        $this->dataKapal = DataKapal::create(['nama' => 'Kapal Reassign', 'status' => 'active']);
        $this->base = now()->startOfMonth()->subMonth();
    }

    private function createHarianRange(int $from, int $to): array
    {
        $ids = [];
        for ($d = $from; $d <= $to; $d++) {
            $ids[$d] = LaporanHarian::create([
                'user_id' => $this->admin->id,
                'data_kapal_id' => $this->dataKapal->id,
                'tanggal_laporan' => $this->base->copy()->day($d)->format('Y-m-d'),
            ])->id;
        }

        return $ids;
    }

    private function tanggalMingguan(LaporanMingguan $mingguan): array
    {
        return $mingguan->laporanHarian()
            ->pluck('tanggal_laporan')
            ->map(fn ($t) => \Carbon\Carbon::parse($t)->format('d'))
            ->sort()
            ->values()
            ->all();
    }

    public function test_tanggal_ujung_periode_ikut_terpilih_di_daftar_harian(): void
    {
        $this->createHarianRange(1, 10);

        $component = Livewire::actingAs($this->admin)
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $this->dataKapal])
            ->set('periode_mulai', $this->base->copy()->day(1)->format('Y-m-d'))
            ->set('periode_selesai', $this->base->copy()->day(10)->format('Y-m-d'));

        $this->assertCount(10, $component->get('availableLaporanHarian'));
        $this->assertCount(10, $component->get('laporan_harian_ids'));
    }

    public function test_mingguan_baru_mengambil_alih_hari_di_tengah_periode_mingguan_lama(): void
    {
        $this->createHarianRange(1, 10);

        // Mingguan 1: periode 1..10
        // Seeder bisa punya minggu_ke sama -> identifikasi via latest id, bukan where('minggu_ke')
        Livewire::actingAs($this->admin)
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $this->dataKapal])
            ->set('minggu_ke', 81)
            ->set('tanggal_laporan', $this->base->copy()->day(10)->format('Y-m-d'))
            ->set('periode_mulai', $this->base->copy()->day(1)->format('Y-m-d'))
            ->set('periode_selesai', $this->base->copy()->day(10)->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $mingguan1 = LaporanMingguan::latest('id')->firstOrFail();
        $this->assertSame(81, $mingguan1->minggu_ke);
        $this->assertCount(10, $mingguan1->laporanHarian);

        // Mingguan 2: periode 2..9 -> konflik -> konfirmasi pemindahan
        $component = Livewire::actingAs($this->admin)
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $this->dataKapal])
            ->set('minggu_ke', 82)
            ->set('tanggal_laporan', $this->base->copy()->day(9)->format('Y-m-d'))
            ->set('periode_mulai', $this->base->copy()->day(2)->format('Y-m-d'))
            ->set('periode_selesai', $this->base->copy()->day(9)->format('Y-m-d'))
            ->call('save')
            ->assertSet('showReassignModal', true);

        $component->call('saveWithReassign');

        $mingguan2 = LaporanMingguan::latest('id')->firstOrFail();
        $this->assertSame(82, $mingguan2->minggu_ke);

        $this->assertSame(['01', '10'], $this->tanggalMingguan($mingguan1));
        $this->assertSame(
            ['02', '03', '04', '05', '06', '07', '08', '09'],
            $this->tanggalMingguan($mingguan2)
        );
    }
}
