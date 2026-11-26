<?php

namespace Tests\Feature;

use App\Enums\DataKapalStatus;
use App\Enums\GalanganStatus;
use App\Enums\NcStatus;
use App\Enums\ProgressStatus;
use App\Livewire\MasterData\GalanganManagement;
use App\Livewire\Pages\Dashboard;
use App\Models\DataKapal;
use App\Models\Galangan;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\LaporanNc;
use App\Models\Owner;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardGalanganMapTest extends TestCase
{
    use RefreshDatabase;

    private Owner $ownerA;

    private Owner $ownerB;

    private Galangan $galangan;

    private DataKapal $kapalA;

    private DataKapal $kapalB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->ownerA = Owner::create(['code' => 'CMP-A', 'name' => 'PT Alfa', 'status' => 'active']);
        $this->ownerB = Owner::create(['code' => 'CMP-B', 'name' => 'PT Beta', 'status' => 'active']);
        $this->galangan = Galangan::factory()->active()->create([
            'kode' => 'GLG-TEST1', 'nama' => 'Galangan Uji Surabaya', 'latitude' => -7.2, 'longitude' => 112.73,
        ]);
        $this->kapalA = DataKapal::create([
            'owner_id' => $this->ownerA->id, 'galangan_id' => $this->galangan->id,
            'nama' => 'Kapal Alfa', 'status' => DataKapalStatus::Active,
            'tanggal_mulai_pelaksanaan' => today()->subDays(80), 'waktu_pelaksanaan_hari' => 100,
        ]);
        $this->kapalB = DataKapal::create([
            'owner_id' => $this->ownerB->id, 'galangan_id' => $this->galangan->id,
            'nama' => 'Kapal Beta', 'status' => DataKapalStatus::Active,
        ]);
    }

    public static function jenisPdf(): array
    {
        return [
            'harian' => ['harian', LaporanHarian::class, []],
            'mingguan' => ['mingguan', LaporanMingguan::class, ['minggu_ke' => 1]],
            'bulanan' => ['bulanan', LaporanBulanan::class, ['bulan_ke' => 1]],
        ];
    }

    #[Test]
    #[DataProvider('jenisPdf')]
    public function arsip_pdf_hanya_memuat_rilis_selesai_dan_kapal_terscope(string $type, string $model, array $extra): void
    {
        $user = $this->user(['dashboard_view', 'laporan_view', 'laporan_download']);
        $this->actingAs($user);
        $attributes = $extra + ['user_id' => $user->id, 'data_kapal_id' => $this->kapalA->id,
            'tanggal_laporan' => today(), 'pdf_status' => 'completed', 'pdf_path' => 'reports/final.pdf',
            'pdf_name' => 'final.pdf', 'pdf_generated_at' => now()];
        $released = $model::create($attributes);
        $model::create(array_replace($attributes, ['tanggal_laporan' => today()->subDay(), 'pdf_status' => 'processing']));
        $model::create(array_replace($attributes, ['tanggal_laporan' => today()->subDays(2), 'pdf_path' => null]));
        $foreign = $model::create(array_replace($attributes, ['data_kapal_id' => $this->kapalB->id]));

        Storage::fake('local');
        Storage::disk('local')->put('reports/final.pdf', '%PDF-1.4 test');
        Livewire::test(Dashboard::class)
            ->call('downloadReleasedPdf', $type, $released->getRouteKey())->assertFileDownloaded('final.pdf');
        $user->revokePermissionTo('laporan_download');
        Livewire::test(Dashboard::class)
            ->call('downloadReleasedPdf', $type, $released->getRouteKey())->assertForbidden();
        $user->givePermissionTo('laporan_download');

        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalA->id, 'nama' => 'Hull', 'bobot' => 100, 'sort_order' => 0]);
        Livewire::test(Dashboard::class)
            ->assertSee('Pilih kapal untuk melihat laporan')
            ->assertDontSee('Jenis laporan PDF')->assertDontSee('Cari nama kapal atau dokumen PDF...')
            ->assertViewHas('releasedPdfs', null)
            ->call('selectKurvaSkapal', $this->kapalA->getRouteKey())
            ->call('setPdfTab', $type)
            ->assertViewHas('releasedPdfs', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $released->id)
            ->set('search', 'tidak-ditemukan')->assertViewHas('releasedPdfs', fn ($rows) => $rows->total() === 0)
            ->call('resetFilters')->assertSet('search', '')
            ->assertViewHas('releasedPdfs', fn ($rows) => $rows->total() === 1)
            ->call('downloadReleasedPdf', $type, $foreign->getRouteKey())->assertNotFound();
    }

    #[Test]
    public function arsip_pdf_dilindungi_permission_dan_tab_url_divalidasi(): void
    {
        $this->actingAs($this->user(['dashboard_view']));
        Livewire::withQueryParams(['pdf' => 'invalid'])->test(Dashboard::class)
            ->assertSet('pdfTab', 'harian')->assertDontSee('Laporan PDF Terilis')
            ->call('setPdfTab', 'mingguan')->assertForbidden();
    }

    #[Test]
    public function target_loading_kurva_s_sama_dengan_aksi_di_baris_masing_masing(): void
    {
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalA->id, 'nama' => 'Hull', 'bobot' => 100, 'sort_order' => 0]);
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalB->id, 'nama' => 'Hull', 'bobot' => 100, 'sort_order' => 0]);
        $this->actingAs($this->user(['dashboard_view', 'laporan_view_all_data_kapal']));
        $html = Livewire::test(Dashboard::class)->html();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//tr[starts-with(@*[name()="wire:key"], "kurvas-")]');
        $this->assertSame(2, $rows->length);
        foreach ($rows as $row) {
            $targets = $xpath->query('.//svg[@*[name()="wire:target"]]', $row);
            $this->assertSame(2, $targets->length);
        }
    }

    #[Test]
    public function arsip_pdf_menampilkan_overlay_loading_saat_ganti_kapal(): void
    {
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalA->id, 'nama' => 'Hull', 'bobot' => 100, 'sort_order' => 0]);
        $this->actingAs($this->user(['dashboard_view', 'laporan_view', 'laporan_view_all_data_kapal']));
        $html = Livewire::test(Dashboard::class)->html();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $overlay = $xpath->query('//section[@aria-label="Arsip laporan PDF"]/div[@*[name()="wire:loading.flex"]]');
        $this->assertSame(1, $overlay->length);
        $this->assertSame('selectKurvaSkapal', $overlay->item(0)->attributes->getNamedItem('wire:target')->nodeValue);
    }

    #[Test]
    public function setiap_tab_pdf_memiliki_spinner_loading_sendiri(): void
    {
        $this->actingAs($this->user(['dashboard_view', 'laporan_view', 'laporan_view_all_data_kapal']));
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalA->id, 'nama' => 'Hull', 'bobot' => 100, 'sort_order' => 0]);
        $html = Livewire::test(Dashboard::class)
            ->call('selectKurvaSkapal', $this->kapalA->getRouteKey())->html();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $tabs = $xpath->query('//button[@role="tab"]');
        $this->assertSame(3, $tabs->length);
        foreach ($tabs as $tab) {
            $action = $tab->attributes->getNamedItem('wire:click')->nodeValue;
            $this->assertSame($action, $tab->attributes->getNamedItem('wire:target')->nodeValue);
            $spinners = $xpath->query('.//svg[@*[name()="wire:loading"]]', $tab);
            $this->assertSame(1, $spinners->length);
            $this->assertSame($action, $spinners->item(0)->attributes->getNamedItem('wire:target')->nodeValue);
        }
    }

    private function user(array $permissions, ?Owner $owner = null): User
    {
        $user = User::factory()->create(['owner_id' => ($owner ?? $this->ownerA)->id]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    #[Test]
    public function user_dashboard_view_melihat_peta_dan_kpi(): void
    {
        $harian = LaporanHarian::create(['user_id' => $this->user([])->id, 'data_kapal_id' => $this->kapalA->id, 'tanggal_laporan' => today()]);
        LaporanNc::create(['laporan_harian_id' => $harian->id, 'data_kapal_id' => $this->kapalA->id, 'nomor' => 'NC-001', 'kendala' => 'Las retak', 'status' => NcStatus::Open]);

        $this->actingAs($this->user(['dashboard_view', 'laporan_view_all_data_kapal']));

        Livewire::test(Dashboard::class)
            ->assertSee('Peta Sebaran Galangan')
            ->assertSee('Galangan Uji Surabaya')
            ->assertViewHas('summary', fn (array $s) => $s['kapal'] === 2 && $s['nc_open'] === 1 && $s['laporan_hari_ini'] === 1 && $s['kontrak_segera_berakhir'] === 1)
            ->assertViewHas('markers', fn (array $m) => collect($m)->firstWhere('kode', 'GLG-TEST1') === [
                'kode' => 'GLG-TEST1', 'nama' => 'Galangan Uji Surabaya',
                'kota' => $this->galangan->kota, 'provinsi' => $this->galangan->provinsi,
                'lat' => -7.2, 'lng' => 112.73, 'kapal' => 2, 'nc' => 1,
            ]);
    }

    #[Test]
    public function user_tanpa_dashboard_view_hanya_welcome_dan_tidak_bisa_memilih_galangan(): void
    {
        $this->actingAs($this->user([]));

        Livewire::test(Dashboard::class)
            ->assertDontSee('Peta Sebaran Galangan')
            ->assertViewMissing('markers')
            ->call('selectGalangan', 'GLG-TEST1')
            ->assertForbidden();
    }

    #[Test]
    public function detail_galangan_hanya_menampilkan_kapal_sesuai_scope_owner(): void
    {
        $this->actingAs($this->user(['dashboard_view']));

        Livewire::test(Dashboard::class)
            ->call('selectGalangan', 'GLG-TEST1')
            ->assertSet('galanganKode', 'GLG-TEST1')
            ->assertSee('Kapal Alfa')
            ->assertDontSee('Kapal Beta')
            ->assertViewHas('detail', fn (array $d) => $d['kapal']->count() === 1
                && $d['kapal']->first()->progress_status === ProgressStatus::BelumAdaData)
            ->assertViewHas('markers', fn (array $m) => collect($m)->firstWhere('kode', 'GLG-TEST1')['kapal'] === 1);
    }

    #[Test]
    public function kode_galangan_tidak_valid_di_url_direset(): void
    {
        $this->actingAs($this->user(['dashboard_view']));

        Livewire::withQueryParams(['galangan' => 'TIDAK-ADA'])
            ->test(Dashboard::class)
            ->assertSet('galanganKode', null)
            ->assertViewHas('detail', null);
    }

    #[Test]
    public function open_laporan_mengarah_ke_index_laporan_kapal(): void
    {
        $this->actingAs($this->user(['dashboard_view', 'laporan_view']));

        $redirect = Livewire::test(Dashboard::class)
            ->call('openLaporan', $this->kapalA->id, 'mingguan')
            ->effects['redirect'];

        // Route key terenkripsi (IV acak) — cocokkan pola URL lalu dekripsi id kapal.
        $this->assertMatchesRegularExpression('#/laporan/([^/]+)/mingguan$#', $redirect);
        preg_match('#/laporan/([^/]+)/mingguan$#', $redirect, $match);
        $this->assertSame((string) $this->kapalA->id, Crypt::decryptString(urldecode($match[1])));
    }

    #[Test]
    public function open_laporan_kapal_owner_lain_ditolak(): void
    {
        $this->actingAs($this->user(['dashboard_view', 'laporan_view']));

        Livewire::test(Dashboard::class)
            ->call('openLaporan', $this->kapalB->id, 'harian')
            ->assertForbidden();
    }

    #[Test]
    public function open_laporan_tipe_di_luar_whitelist_ditolak(): void
    {
        $this->actingAs($this->user(['dashboard_view', 'laporan_view']));

        Livewire::test(Dashboard::class)
            ->call('openLaporan', $this->kapalA->id, 'nc')
            ->assertNotFound();
    }

    #[Test]
    public function galangan_nonaktif_tidak_tampil_di_peta(): void
    {
        $this->galangan->update(['status' => GalanganStatus::Inactive]);

        $markers = app(DashboardService::class)->markers(collect([$this->kapalA->id]));

        $this->assertNull(collect($markers)->firstWhere('kode', 'GLG-TEST1'));
    }

    #[Test]
    public function simpan_galangan_dengan_koordinat_dibulatkan(): void
    {
        $this->actingAs($this->user(['galangan_view', 'galangan_update']));

        Livewire::test(GalanganManagement::class)
            ->call('edit', $this->galangan->id)
            ->assertSet('latitude', -7.2)
            ->set('latitude', '-6.123456789')
            ->set('longitude', '106.987654321')
            ->call('save')
            ->assertHasNoErrors();

        $this->galangan->refresh();
        $this->assertSame(-6.1234568, $this->galangan->latitude);
        $this->assertSame(106.9876543, $this->galangan->longitude);
        $this->assertTrue($this->galangan->hasLokasi());
    }

    #[Test]
    public function koordinat_kosong_menghapus_lokasi(): void
    {
        $this->actingAs($this->user(['galangan_view', 'galangan_update']));

        Livewire::test(GalanganManagement::class)
            ->call('edit', $this->galangan->id)
            ->set('latitude', null)
            ->set('longitude', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($this->galangan->refresh()->hasLokasi());
    }

    public static function koordinatTidakValid(): array
    {
        return [
            'latitude di luar rentang' => ['95', '110', 'latitude'],
            'longitude di luar rentang' => ['-7', '181', 'longitude'],
            'latitude tanpa longitude' => ['-7', null, 'longitude'],
            'longitude tanpa latitude' => [null, '110', 'latitude'],
        ];
    }

    #[Test]
    #[DataProvider('koordinatTidakValid')]
    public function koordinat_tidak_valid_ditolak(?string $lat, ?string $lng, string $field): void
    {
        $this->actingAs($this->user(['galangan_view', 'galangan_update']));

        Livewire::test(GalanganManagement::class)
            ->call('edit', $this->galangan->id)
            ->set('latitude', $lat)
            ->set('longitude', $lng)
            ->call('save')
            ->assertHasErrors([$field]);
    }

    #[Test]
    public function kurva_s_dashboard_hanya_menampilkan_kapal_terscope_dan_punya_jadwal(): void
    {
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalA->id, 'nama' => 'Hull', 'bobot' => 60, 'sort_order' => 0]);
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalB->id, 'nama' => 'Hull', 'bobot' => 60, 'sort_order' => 0]);

        $this->actingAs($this->user(['dashboard_view']));

        // Scope owner: hanya kapalA yang punya jadwal; kapalB (owner lain) tidak tampil.
        Livewire::test(Dashboard::class)
            ->assertSee('Progres Kurva-S per Kapal')
            ->assertSee('Kapal Alfa')
            ->assertDontSee('Kapal Beta')
            ->assertViewHas('kurvaSKapal', fn ($rows) => $rows->pluck('id')->all() === [$this->kapalA->id])
            ->assertViewHas('selectedKurvaSkapal', null);

        // Kapal tanpa work group tidak masuk daftar.
        KurvaSWorkGroup::where('data_kapal_id', $this->kapalA->id)->delete();
        Livewire::test(Dashboard::class)
            ->assertSee('Belum ada kapal dengan jadwal Kurva-S')
            ->assertViewHas('kurvaSKapal', fn ($rows) => $rows->isEmpty());
    }

    #[Test]
    public function kurva_s_dashboard_pilih_kapal_menampilkan_grafik_dan_tolak_scope_lain(): void
    {
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalA->id, 'nama' => 'Hull', 'bobot' => 60, 'sort_order' => 0]);
        KurvaSWorkGroup::create(['data_kapal_id' => $this->kapalB->id, 'nama' => 'Hull', 'bobot' => 60, 'sort_order' => 0]);

        $this->actingAs($this->user(['dashboard_view']));

        $keyA = $this->kapalA->getRouteKey();
        Livewire::test(Dashboard::class)
            ->call('selectKurvaSkapal', $keyA)
            ->assertSet('kurvaSkapalKey', $keyA)
            ->assertViewHas('selectedKurvaSkapal', fn ($k) => $k?->id === $this->kapalA->id)
            ->assertViewHas('kurvaSChart', fn (array $c) => $c['has_rencana'] === true)
            ->call('setKurvaSMode', 'bulanan')
            ->assertSet('kurvaSMode', 'bulanan')
            ->call('setKurvaSMode', 'asal')
            ->assertStatus(404);

        // Kapal di luar scope owner tidak bisa dipilih walau key valid.
        Livewire::test(Dashboard::class)
            ->call('selectKurvaSkapal', $this->kapalB->getRouteKey())
            ->assertSet('kurvaSkapalKey', null)
            ->assertViewHas('selectedKurvaSkapal', null);
    }

    #[Test]
    public function progress_status_dari_deviasi(): void
    {
        $this->assertSame(ProgressStatus::BelumAdaData, ProgressStatus::fromDeviasi(null));
        $this->assertSame(ProgressStatus::Terlambat, ProgressStatus::fromDeviasi(-5.0));
        $this->assertSame(ProgressStatus::Waspada, ProgressStatus::fromDeviasi(-3.0));
        $this->assertSame(ProgressStatus::SesuaiRencana, ProgressStatus::fromDeviasi(0.5));
        $this->assertSame(ProgressStatus::LebihCepat, ProgressStatus::fromDeviasi(4.0));
    }
}
