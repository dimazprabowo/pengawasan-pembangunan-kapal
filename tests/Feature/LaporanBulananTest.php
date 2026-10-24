<?php

namespace Tests\Feature;

use App\Livewire\LaporanBulanan\LaporanBulananCreate;
use App\Models\JenisKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use App\Services\KurvaSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanBulananTest extends TestCase
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

    private function jenisKapal(): JenisKapal
    {
        return JenisKapal::whereNotNull('tanggal_mulai_kontrak')->firstOrFail();
    }

    public function test_bulanan_index_renders_in_jenis_kapal_context(): void
    {
        $jenisKapal = $this->jenisKapal();

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.index', $jenisKapal))
            ->assertOk()
            ->assertSee($jenisKapal->nama)
            ->assertSee('Laporan Bulanan');
    }

    public function test_bulanan_create_show_edit_render(): void
    {
        $laporan = LaporanBulanan::firstOrFail();
        $jenisKapal = $laporan->jenisKapal;

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.create', $jenisKapal))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.show', [$jenisKapal, $laporan]))
            ->assertOk()
            ->assertSee($laporan->judul);

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.edit', [$jenisKapal, $laporan]))
            ->assertOk();
    }

    public function test_monthly_chart_menjumlahkan_realisasi_mingguan(): void
    {
        $jenisKapal = $this->jenisKapal();
        $service = app(KurvaSService::class);

        $monthly = $service->getMonthlyChartData($jenisKapal);
        $this->assertNotEmpty($monthly['labels']);
        $this->assertSame('Bulan', $monthly['unit_label']);
        $this->assertSame($monthly['total_unit'], count($monthly['labels']));

        // Kumulatif aktual terakhir chart = kumulatif terakhir history
        $history = $service->getMonthlyProgressHistory($jenisKapal);
        $this->assertNotEmpty($history);

        $lastHistoryCum = end($history)['cumulative_actual'];
        $aktualValues = array_values(array_filter($monthly['aktual'], fn ($v) => $v !== null));
        $this->assertNotEmpty($aktualValues);
        $this->assertEqualsWithDelta($lastHistoryCum, end($aktualValues), 0.01);
    }

    public function test_monthly_rencana_sesuai_kurva_s_rencana(): void
    {
        $jenisKapal = $this->jenisKapal();
        $service = app(KurvaSService::class);

        $workGroupIds = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->pluck('id');

        foreach ($service->getMonthlyProgressHistory($jenisKapal) as $entry) {
            // Rencana bulan = Σ pct_rencana bulan_ke tsb (langsung, tidak dibagi)
            $expected = (float) KurvaSRencana::where('bulan_ke', $entry['bulan_ke'])
                ->whereIn('work_group_id', $workGroupIds)
                ->sum('pct_rencana');

            $this->assertEqualsWithDelta($expected, $entry['week_plan'], 0.01, "Bulan {$entry['bulan_ke']}");
        }
    }

    public function test_get_bulan_options_mengecualikan_bulan_terpakai(): void
    {
        $jenisKapal = $this->jenisKapal();
        $used = LaporanBulanan::where('jenis_kapal_id', $jenisKapal->id)->pluck('bulan_ke')->toArray();
        $this->assertNotEmpty($used);

        $options = app(KontrakPeriodeService::class)->getBulanOptions($jenisKapal);
        $optionValues = array_column($options, 'value');

        foreach ($used as $bulanKe) {
            $this->assertNotContains($bulanKe, $optionValues);
        }
    }

    public function test_create_bulanan_persists_with_derived_periode(): void
    {
        // Kapal pertama sudah penuh (semua bulan ter-seed) — pakai kapal
        // lain dengan kontrak yang belum punya laporan bulanan.
        $jenisKapal = JenisKapal::whereNotNull('tanggal_mulai_kontrak')
            ->whereDoesntHave('laporanBulanan')
            ->firstOrFail();

        $options = app(KontrakPeriodeService::class)->getBulanOptions($jenisKapal);
        $this->assertNotEmpty($options);
        $bulanKe = $options[0]['value'];

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('bulan_ke', $bulanKe)
            ->set('ringkasan', 'Uji laporan bulanan')
            ->call('save')
            ->assertRedirect(); // URL berisi encrypted route key (non-deterministik)

        $laporan = LaporanBulanan::where('jenis_kapal_id', $jenisKapal->id)
            ->where('bulan_ke', $bulanKe)
            ->firstOrFail();

        // Periode snapshot mengikuti kontrak
        $bulan = app(KontrakPeriodeService::class)->getBulanPeriode($jenisKapal, $bulanKe);
        $this->assertEquals($bulan['mulai']->format('Y-m-d'), $laporan->periode_mulai->format('Y-m-d'));
        $this->assertEquals($bulan['selesai']->format('Y-m-d'), $laporan->periode_selesai->format('Y-m-d'));
    }

    public function test_bulan_ke_ganda_ditolak(): void
    {
        $jenisKapal = $this->jenisKapal();
        $existing = LaporanBulanan::where('jenis_kapal_id', $jenisKapal->id)->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananCreate::class, ['jenisKapal' => $jenisKapal])
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('bulan_ke', $existing->bulan_ke)
            ->call('save');

        $this->assertSame(
            1,
            LaporanBulanan::where('jenis_kapal_id', $jenisKapal->id)
                ->where('bulan_ke', $existing->bulan_ke)
                ->count()
        );
    }

    public function test_user_tanpa_izin_ditolak(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $jenisKapal = $this->jenisKapal();

        $this->actingAs($user)
            ->get(route('laporan-bulanan.index', $jenisKapal))
            ->assertForbidden();
    }
}
