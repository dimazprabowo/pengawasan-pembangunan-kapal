<?php

namespace Tests\Feature;

use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Models\JenisKapal;
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

    private JenisKapal $jenisKapal;

    private \Carbon\Carbon $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::firstOrFail();
        $this->jenisKapal = JenisKapal::firstOrFail();
        $this->base = now()->startOfMonth()->subMonth();
    }

    private function createHarianRange(int $from, int $to): array
    {
        $ids = [];
        for ($d = $from; $d <= $to; $d++) {
            $ids[$d] = LaporanHarian::create([
                'user_id' => $this->admin->id,
                'jenis_kapal_id' => $this->jenisKapal->id,
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
            ->test(LaporanMingguanCreate::class, ['jenisKapal' => $this->jenisKapal])
            ->set('periode_mulai', $this->base->copy()->day(1)->format('Y-m-d'))
            ->set('periode_selesai', $this->base->copy()->day(10)->format('Y-m-d'));

        $this->assertCount(10, $component->get('availableLaporanHarian'));
        $this->assertCount(10, $component->get('laporan_harian_ids'));
    }

    public function test_mingguan_baru_mengambil_alih_hari_di_tengah_periode_mingguan_lama(): void
    {
        $this->createHarianRange(1, 10);

        // Mingguan 1: periode 1..10
        Livewire::actingAs($this->admin)
            ->test(LaporanMingguanCreate::class, ['jenisKapal' => $this->jenisKapal])
            ->set('minggu_ke', 81)
            ->set('tanggal_laporan', $this->base->copy()->day(10)->format('Y-m-d'))
            ->set('periode_mulai', $this->base->copy()->day(1)->format('Y-m-d'))
            ->set('periode_selesai', $this->base->copy()->day(10)->format('Y-m-d'))
            ->call('save');

        $mingguan1 = LaporanMingguan::where('minggu_ke', 81)->firstOrFail();
        $this->assertCount(10, $mingguan1->laporanHarian);

        // Mingguan 2: periode 2..9 -> konflik -> konfirmasi pemindahan
        $component = Livewire::actingAs($this->admin)
            ->test(LaporanMingguanCreate::class, ['jenisKapal' => $this->jenisKapal])
            ->set('minggu_ke', 82)
            ->set('tanggal_laporan', $this->base->copy()->day(9)->format('Y-m-d'))
            ->set('periode_mulai', $this->base->copy()->day(2)->format('Y-m-d'))
            ->set('periode_selesai', $this->base->copy()->day(9)->format('Y-m-d'))
            ->call('save')
            ->assertSet('showReassignModal', true);

        $component->call('saveWithReassign');

        $mingguan2 = LaporanMingguan::where('minggu_ke', 82)->firstOrFail();

        $this->assertSame(['01', '10'], $this->tanggalMingguan($mingguan1));
        $this->assertSame(
            ['02', '03', '04', '05', '06', '07', '08', '09'],
            $this->tanggalMingguan($mingguan2)
        );
    }
}
