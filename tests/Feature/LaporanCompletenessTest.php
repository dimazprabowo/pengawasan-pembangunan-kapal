<?php

namespace Tests\Feature;

use App\Jobs\GenerateLaporanBulananJob;
use App\Jobs\GenerateLaporanMingguanJob;
use App\Livewire\LaporanBulanan\LaporanBulananShow;
use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Livewire\LaporanMingguan\LaporanMingguanShow;
use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\Owner;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use App\Services\LaporanCompletenessService;
use App\Services\LaporanMingguanWordService;
use App\Services\QueueStatusService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaporanCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DataKapal $kapal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['laporan_view', 'laporan_show', 'laporan_download', 'laporan_create', 'laporan_view_all_data_kapal']);
        $this->actingAs($this->user);
        $this->travelTo(now()->setDate(2026, 3, 1));
        $this->kapal = DataKapal::create(['nama' => 'Uji Kelengkapan', 'status' => 'active', 'tanggal_mulai_pelaksanaan' => '2026-01-01', 'waktu_pelaksanaan_hari' => 20]);
        Storage::fake('local');
    }

    private function week(int $week = 1): LaporanMingguan
    {
        $periode = app(KontrakPeriodeService::class)->getMingguPeriode($this->kapal, $week);

        return LaporanMingguan::create([
            'user_id' => $this->user->id, 'data_kapal_id' => $this->kapal->id,
            'tanggal_laporan' => $periode['selesai'], 'minggu_ke' => $week,
            'periode_mulai' => $periode['mulai'], 'periode_selesai' => $periode['selesai'],
        ]);
    }

    private function month(): LaporanBulanan
    {
        return LaporanBulanan::create([
            'user_id' => $this->user->id, 'data_kapal_id' => $this->kapal->id,
            'tanggal_laporan' => '2026-01-20', 'bulan_ke' => 1,
            'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-01-20',
        ]);
    }

    private function daily(int $day, bool $libur = false, ?DataKapal $kapal = null): LaporanHarian
    {
        return LaporanHarian::create([
            'user_id' => $this->user->id, 'data_kapal_id' => ($kapal ?? $this->kapal)->id,
            'tanggal_laporan' => sprintf('2026-01-%02d', $day),
            'is_libur' => $libur, 'alasan_libur' => $libur ? 'Hari libur' : null,
        ]);
    }

    #[Test]
    public function harian_tanpa_dokumen_dan_libur_dihitung_ada_sesuai_kapal_dan_periode(): void
    {
        $week = $this->week();
        foreach (range(1, 6) as $day) {
            $this->daily($day, $day === 4);
        }
        $other = DataKapal::create(['nama' => 'Kapal Lain', 'status' => 'active']);
        $this->daily(7, false, $other);
        $missing = app(LaporanCompletenessService::class)->missing($week);
        $this->assertSame(['2026-01-07'], array_column($missing, 'value'));
        $daily = $this->daily(7);
        $this->assertSame([], app(LaporanCompletenessService::class)->missing($week));
        $daily->delete();
        $this->assertCount(1, app(LaporanCompletenessService::class)->missing($week));
    }

    #[Test]
    public function bulanan_memeriksa_mingguan_dan_harian_termasuk_sisa_hari_minggu_terakhir(): void
    {
        $month = $this->month();
        $this->week();
        foreach (range(1, 19) as $day) {
            $this->daily($day);
        }
        $missing = app(LaporanCompletenessService::class)->missing($month);
        $this->assertSame(['mingguan', 'harian'], array_column($missing, 'type'));
        $this->assertSame(['2', '2026-01-20'], array_column($missing, 'value'));
        $this->week(2);
        $this->daily(20, true);
        $this->assertSame([], app(LaporanCompletenessService::class)->missing($month));
    }

    #[Test]
    public function modal_mingguan_memblokir_job_dan_mempertahankan_file_lama(): void
    {
        Bus::fake();
        $week = $this->week();
        $week->update(['doc_path' => 'lama.docx', 'doc_status' => 'completed']);
        Storage::disk('local')->put('lama.docx', 'dokumen lama');
        Livewire::test(LaporanMingguanShow::class, ['dataKapal' => $this->kapal, 'laporanMingguan' => $week])
            ->call('confirmRegenerate')->assertSet('showMissingReports', true)
            ->assertSet('showRegenerateConfirm', false)->assertCount('missingReports', 7)
            ->assertSee('Laporan Sumber Belum Lengkap')
            ->call('generateWord')->assertSet('showMissingReports', true)
            ->call('createMissingReport', 0)->assertRedirect();
        Bus::assertNotDispatched(GenerateLaporanMingguanJob::class);
        Storage::disk('local')->assertExists('lama.docx');
        $this->assertSame('completed', $week->fresh()->doc_status);
    }

    #[Test]
    public function modal_bulanan_memblokir_job_dan_menampilkan_kedua_jenis_laporan(): void
    {
        Bus::fake();
        Livewire::test(LaporanBulananShow::class, ['dataKapal' => $this->kapal, 'laporanBulanan' => $this->month()])
            ->call('generateWord')->assertSet('showMissingReports', true)
            ->assertCount('missingReports', 22)->assertSee('Laporan Mingguan ke-1')
            ->assertSee('Laporan Harian')->call('createMissingReport', 0)->assertRedirect();
        Bus::assertNotDispatched(GenerateLaporanBulananJob::class);
    }

    #[Test]
    public function data_lengkap_mengirim_job_meski_belum_ada_dokumen_sumber(): void
    {
        Bus::fake();
        $week = $this->week();
        foreach (range(1, 7) as $day) {
            $this->daily($day, $day === 7);
        }
        Livewire::test(LaporanMingguanShow::class, ['dataKapal' => $this->kapal, 'laporanMingguan' => $week])
            ->call('generateWord')->assertSet('showMissingReports', false);
        Bus::assertDispatched(GenerateLaporanMingguanJob::class);
        app(LaporanCompletenessService::class)->prepareForGeneration($week);
        $this->assertSame(7, $week->laporanHarian()->count());
    }

    #[Test]
    public function izin_generate_dan_create_tetap_diperiksa(): void
    {
        Bus::fake();
        $week = $this->week();
        $this->user->revokePermissionTo('laporan_create');
        Livewire::test(LaporanMingguanShow::class, ['dataKapal' => $this->kapal, 'laporanMingguan' => $week])
            ->call('generateWord')->assertDontSee('Buat Laporan')->call('createMissingReport', 0)->assertForbidden();
        $this->user->revokePermissionTo('laporan_download');
        Livewire::test(LaporanMingguanShow::class, ['dataKapal' => $this->kapal, 'laporanMingguan' => $week])
            ->call('generateWord')->assertForbidden();
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function worker_memeriksa_ulang_sebelum_menghapus_file_lama(): void
    {
        $week = $this->week();
        $week->update(['doc_path' => 'lama.docx', 'doc_status' => 'pending']);
        Storage::disk('local')->put('lama.docx', 'dokumen lama');
        $word = $this->mock(LaporanMingguanWordService::class);
        $word->shouldNotReceive('generate');
        (new GenerateLaporanMingguanJob($week))->handle($word, app(QueueStatusService::class));
        Storage::disk('local')->assertExists('lama.docx');
        $this->assertSame('failed', $week->fresh()->doc_status);
    }

    #[Test]
    public function bulanan_lengkap_dapat_digenerate_dan_harian_baru_ikut_lampiran(): void
    {
        Bus::fake();
        $month = $this->month();
        $first = $this->week();
        $last = $this->week(2);
        foreach (range(1, 20) as $day) {
            $this->daily($day, $day === 20);
        }
        $first->update(['doc_status' => 'completed', 'doc_path' => 'mingguan-lama.docx']);
        Storage::disk('local')->put('mingguan-lama.docx', 'lama');
        Livewire::test(LaporanBulananShow::class, ['dataKapal' => $this->kapal, 'laporanBulanan' => $month])
            ->call('generateWord')->assertSet('showMissingReports', false);
        Bus::assertDispatched(GenerateLaporanBulananJob::class);
        app(LaporanCompletenessService::class)->prepareForGeneration($month);
        $this->assertSame(7, $first->laporanHarian()->count());
        $this->assertSame(13, $last->laporanHarian()->count());
        $this->assertNull($first->fresh()->doc_path);
        Storage::disk('local')->assertMissing('mingguan-lama.docx');
    }

    #[Test]
    public function scope_kapal_tetap_dilindungi(): void
    {
        $owner = Owner::create(['code' => 'LAIN', 'name' => 'Owner Lain', 'status' => 'active']);
        $this->kapal->update(['owner_id' => $owner->id]);
        $week = $this->week();
        $this->user->revokePermissionTo('laporan_view_all_data_kapal');
        Livewire::test(LaporanMingguanShow::class, ['dataKapal' => $this->kapal, 'laporanMingguan' => $week])
            ->assertForbidden();
    }

    #[Test]
    public function tanggal_masa_depan_tetap_didaftar_tetapi_tidak_mengarahkan_ke_tanggal_lain(): void
    {
        $week = $this->week();
        $this->travelTo(now()->setDate(2025, 12, 31));
        Livewire::test(LaporanMingguanShow::class, ['dataKapal' => $this->kapal, 'laporanMingguan' => $week])
            ->call('generateWord')->assertSet('showMissingReports', true)
            ->assertSee('Tanggal pelaporan belum tiba.')->assertDontSee('Buat Laporan')
            ->call('createMissingReport', 0)->assertNoRedirect();
    }

    #[Test]
    public function form_create_menerima_tanggal_dan_minggu_dari_modal(): void
    {
        Livewire::withQueryParams(['tanggal' => '2026-01-05'])
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->kapal])
            ->assertSet('items.0.tanggal_laporan', '2026-01-05');
        Livewire::withQueryParams(['minggu' => 2])
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $this->kapal])
            ->assertSet('minggu_ke', 2)->assertSet('periode_mulai', '2026-01-08')
            ->assertSet('periode_selesai', '2026-01-20');
    }
}
