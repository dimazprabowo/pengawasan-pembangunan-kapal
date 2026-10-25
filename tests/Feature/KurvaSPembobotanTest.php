<?php

namespace Tests\Feature;

use App\Exports\KurvaSRencanaSheet;
use App\Imports\KurvaSRencanaImport;
use App\Imports\KurvaSTemplateImport;
use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Livewire\MasterData\KurvaSManagement;
use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSRencanaMingguan;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanMingguan;
use App\Models\User;
use App\Services\KurvaSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * pct_rencana & pct_realisasi dinyatakan dalam poin % PROYEK (kontribusi langsung).
 * Rencana disimpan PER BULAN (bulan_ke); nilai rencana mingguan diturunkan
 * sistem dengan membagi rata pct bulan ke jumlah minggu bulan tsb (truncate
 * 3 desimal, sisa ke minggu terakhir) — atau dari override mingguan di
 * kurva_s_rencana_mingguan bila ada ("mingguan menang").
 * Total distribusi per work group tidak boleh melebihi bobot-nya.
 */
class KurvaSPembobotanTest extends TestCase
{
    use RefreshDatabase;

    private function dataKapal(): DataKapal
    {
        return DataKapal::create(['nama' => 'Kapal Uji', 'status' => 'active']);
    }

    /**
     * Kontrak 5 Jan 2026 + 140 hari = 20 minggu → 5 bulan:
     * Jan (mgg 1-4), Feb (5-8), Mar (9-12), Apr (14-17), Mei (18-20).
     */
    private function dataKapalKontrak(): DataKapal
    {
        return DataKapal::create([
            'nama' => 'Kapal Uji',
            'status' => 'active',
            'tanggal_mulai_kontrak' => '2026-01-05',
            'durasi_kontrak_hari' => 140,
        ]);
    }

    private function service(): KurvaSService
    {
        return app(KurvaSService::class);
    }

    private function seedWorkGroup(DataKapal $jk, float $bobot, array $months): KurvaSWorkGroup
    {
        $wg = KurvaSWorkGroup::create([
            'data_kapal_id' => $jk->id,
            'nama' => 'Grup '.$bobot,
            'bobot' => $bobot,
            'sort_order' => 0,
        ]);

        foreach ($months as $bulan => $pct) {
            KurvaSRencana::create([
                'work_group_id' => $wg->id,
                'bulan_ke' => $bulan,
                'pct_rencana' => $pct,
            ]);
        }

        return $wg;
    }

    public function test_save_work_groups_clamp_pct_rencana_ke_bobot(): void
    {
        $jk = $this->dataKapal();

        $this->service()->saveWorkGroups($jk, [
            [
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '6.00', 'keterangan' => ''],
                    ['bulan_ke' => 2, 'pct_rencana' => '50.00', 'keterangan' => ''], // > bobot → clamp
                ],
            ],
        ]);

        $rencana = KurvaSRencana::orderBy('bulan_ke')->pluck('pct_rencana')->all();
        $this->assertEqualsWithDelta(6.0, (float) $rencana[0], 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $rencana[1], 0.001);
    }

    public function test_get_week_plans_menurunkan_rencana_bulanan_ke_minggu(): void
    {
        $jk = $this->dataKapalKontrak();
        $wg = $this->seedWorkGroup($jk, 10.0, [3 => 6.0]); // bulan 3 = Mar, mgg 9-12 (4 mgg)

        $plans = $this->service()->getWeekPlans($jk, 10);

        // 6.00 / 4 minggu = 1.50 per minggu
        $this->assertEqualsWithDelta(1.5, (float) $plans[$wg->id], 0.001);

        // Minggu di luar bulan 3 → 0
        $plansW5 = $this->service()->getWeekPlans($jk, 5);
        $this->assertEqualsWithDelta(0.0, (float) $plansW5[$wg->id], 0.001);
    }

    public function test_chart_rencana_adalah_jumlah_langsung_tanpa_pembobotan(): void
    {
        $jk = $this->dataKapalKontrak();
        // Bulan 1 (mgg 1-4): 4.0 → 1.0/mgg; Bulan 2 (mgg 5-8): 8.0 → 2.0/mgg
        $this->seedWorkGroup($jk, 10.0, [1 => 4.0]);
        $this->seedWorkGroup($jk, 30.0, [2 => 8.0]);

        $chart = $this->service()->getChartData($jk);

        // Kumulatif: mgg1-4 = +1 → 1,2,3,4 ; mgg5-8 = +2 → 6,8,10,12 ; sisanya tetap 12
        $expected = [1.0, 2.0, 3.0, 4.0, 6.0, 8.0, 10.0, 12.0];
        $expected = array_merge($expected, array_fill(0, 20 - count($expected), 12.0));

        $this->assertSame(20, count($chart['rencana']));
        $this->assertSame($expected, $chart['rencana']);
    }

    public function test_save_progress_clamp_pct_realisasi_ke_bobot(): void
    {
        $jk = $this->dataKapal();
        $user = User::factory()->create();
        $wg = $this->seedWorkGroup($jk, 10.0, [1 => 10.0]);

        $laporan = LaporanMingguan::create([
            'user_id' => $user->id,
            'data_kapal_id' => $jk->id,
            'tanggal_laporan' => '2026-01-21',
            'minggu_ke' => 1,
        ]);

        $this->service()->saveProgress($laporan, [$wg->id => 25.0]);

        $this->assertDatabaseHas('laporan_mingguan_progress', [
            'laporan_mingguan_id' => $laporan->id,
            'work_group_id' => $wg->id,
            'pct_realisasi' => 10.0,
        ]);
    }

    public function test_history_totals_dan_kontribusi_tanpa_pembobotan(): void
    {
        $jk = $this->dataKapalKontrak();
        $user = User::factory()->create();
        // Bulan 1 = 8.0 → mgg 1-4 masing-masing 2.0
        $wg = $this->seedWorkGroup($jk, 10.0, [1 => 8.0]);

        foreach ([1 => 3.0, 2 => 5.0] as $minggu => $pct) {
            $laporan = LaporanMingguan::create([
                'user_id' => $user->id,
                'data_kapal_id' => $jk->id,
                'tanggal_laporan' => '2026-01-21',
                'minggu_ke' => $minggu,
            ]);
            $this->service()->saveProgress($laporan, [$wg->id => $pct]);
        }

        $history = $this->service()->getProgressHistory($jk);

        // Rencana mingguan = 2.0/mgg → kumulatif 2.0 lalu 4.0
        $this->assertSame(2.0, $history[0]['cumulative_plan']);
        $this->assertSame(4.0, $history[1]['cumulative_plan']);
        $this->assertSame(3.0, $history[0]['cumulative_actual']);
        $this->assertSame(8.0, $history[1]['cumulative_actual']);

        $totals = $this->service()->calculateTotalKontribusiHistory($history, [$wg]);
        $this->assertSame(8.0, $totals[$wg->id]);
    }

    public function test_management_save_ditolak_bila_rencana_melebihi_bobot(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2026-01-05')
            ->set('durasi_kontrak_hari', 140)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '8.00', 'keterangan' => ''],
                    ['bulan_ke' => 2, 'pct_rencana' => '5.00', 'keterangan' => ''],
                ],
            ]])
            ->call('save')
            ->assertSet('showModal', true);

        $this->assertSame(0, KurvaSWorkGroup::where('data_kapal_id', $jk->id)->count());
    }

    public function test_management_save_ditolak_bila_rencana_melebihi_bobot_sedikit(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        // Regresi: kelebihan 0.01 (satu langkah presisi tampilan) dulu lolos
        // toleransi +0.01 — padahal indikator Σ menampilkan "10.01% > 10.00%".
        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2026-01-05')
            ->set('durasi_kontrak_hari', 140)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '6.00', 'keterangan' => ''],
                    ['bulan_ke' => 2, 'pct_rencana' => '4.01', 'keterangan' => ''],
                ],
            ]])
            ->call('save')
            ->assertSet('showModal', true);

        $this->assertSame(0, KurvaSWorkGroup::where('data_kapal_id', $jk->id)->count());
    }

    public function test_management_save_sukses_bila_rencana_sesuai_bobot(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2026-01-05')
            ->set('durasi_kontrak_hari', 140)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '6.00', 'keterangan' => ''],
                    ['bulan_ke' => 2, 'pct_rencana' => '4.00', 'keterangan' => ''],
                ],
            ]])
            ->call('save')
            ->assertSet('showModal', false);

        $wg = KurvaSWorkGroup::where('data_kapal_id', $jk->id)->firstOrFail();
        $this->assertEqualsWithDelta(10.0, (float) $wg->kurvaSRencana->sum('pct_rencana'), 0.001);

        // Kontrak ikut tersimpan
        $jk->refresh();
        $this->assertTrue($jk->hasKontrak());
    }

    public function test_management_save_ditolak_tanpa_kontrak(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        // Rencana bulanan tidak bermakna tanpa kontrak → save ditolak
        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '4.00', 'keterangan' => ''],
                ],
            ]])
            ->call('save')
            ->assertSet('showModal', true);

        $this->assertSame(0, KurvaSWorkGroup::where('data_kapal_id', $jk->id)->count());
    }

    public function test_sync_draft_menerapkan_nilai_tanpa_gagal(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        // Sinkronisasi ringan saat mengetik: nilai tetap masuk ke properti
        // komponen; tidak ada error walau tanpa render ulang.
        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '4.00', 'keterangan' => ''],
                ],
            ]])
            ->call('syncDraft')
            ->assertHasNoErrors()
            ->assertSet('workGroups.0.months.0.pct_rencana', '4.00');
    }

    public function test_apply_contract_months_menyesuaikan_semua_group(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal(); // tanpa kontrak di DB — kontrak diisi via form

        // Kontrak 90 hari dari 1 Jan 2025 → 3 bulan (Jan/Feb/Mar masing-masing 4 mgg)
        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2025-01-01')
            ->set('durasi_kontrak_hari', 90)
            ->set('workGroups', [
                [
                    'id' => null, 'nama' => 'A', 'bobot' => '10.00',
                    'months' => [['bulan_ke' => 1, 'pct_rencana' => '5.00', 'keterangan' => '']],
                ],
                [
                    'id' => null, 'nama' => 'B', 'bobot' => '20.00',
                    'months' => array_map(fn ($i) => ['bulan_ke' => $i, 'pct_rencana' => '1.00', 'keterangan' => ''], range(1, 5)),
                ],
            ])
            ->call('applyContractMonths')
            ->assertSet('totalBulan', 3);

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2025-01-01')
            ->set('durasi_kontrak_hari', 90)
            ->set('workGroups', [
                [
                    'id' => null, 'nama' => 'A', 'bobot' => '10.00',
                    'months' => [['bulan_ke' => 1, 'pct_rencana' => '5.00', 'keterangan' => '']],
                ],
            ])
            ->call('applyContractMonths')
            ->assertSet('workGroups.0.months', function ($months) {
                return count($months) === 3
                    && $months[2]['bulan_ke'] === 3
                    && $months[0]['pct_rencana'] === '5.00'; // nilai existing tidak hilang
            });
    }

    public function test_request_apply_contract_months_membuka_konfirmasi(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2025-01-01')
            ->set('durasi_kontrak_hari', 90)
            ->set('workGroups', [[
                'id' => null, 'nama' => 'A', 'bobot' => '10.00',
                'months' => array_map(fn ($i) => ['bulan_ke' => $i, 'pct_rencana' => '1.00', 'keterangan' => ''], range(1, 5)),
            ]])
            ->call('requestApplyContractMonths')
            ->assertSet('confirmApplyMonths', true)
            ->assertSet('workGroups.0.months', fn ($months) => count($months) === 5) // belum berubah
            ->call('confirmApplyContractMonths')
            ->assertSet('confirmApplyMonths', false)
            ->assertSet('workGroups.0.months', fn ($months) => count($months) === 3); // sesudah confirm → 3 bulan
    }

    public function test_apply_contract_months_menyusutkan_bulan_berlebih(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2025-01-01')
            ->set('durasi_kontrak_hari', 90)
            ->set('workGroups', [[
                'id' => null, 'nama' => 'B', 'bobot' => '20.00',
                'months' => array_map(fn ($i) => ['bulan_ke' => $i, 'pct_rencana' => '1.00', 'keterangan' => ''], range(1, 5)),
            ]])
            ->call('applyContractMonths')
            ->assertSet('workGroups.0.months', fn ($months) => count($months) === 3);
    }

    public function test_apply_contract_months_ditolak_tanpa_kontrak(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('workGroups', [[
                'id' => null, 'nama' => 'A', 'bobot' => '10.00',
                'months' => [['bulan_ke' => 1, 'pct_rencana' => '5.00', 'keterangan' => '']],
            ]])
            ->call('applyContractMonths')
            ->assertSet('workGroups.0.months', fn ($months) => count($months) === 1); // tidak berubah
    }

    public function test_import_menolak_rencana_melebihi_bobot(): void
    {
        $jk = $this->dataKapal();
        $parent = new KurvaSTemplateImport($jk);
        $import = new KurvaSRencanaImport($jk, $parent);

        $import->collection(collect([
            collect(['no' => 1, 'work_group' => 'Engineering', 'bobot' => 10, 'b1_rencana' => 8, 'b1_keterangan' => '', 'b2_rencana' => 5, 'b2_keterangan' => '']),
        ]));

        $this->assertTrue($parent->hasErrors());
        $this->assertStringContainsString('melebihi bobot', $parent->getErrors()[0]);
    }

    public function test_import_menerima_rencana_sesuai_bobot(): void
    {
        $jk = $this->dataKapal();
        $parent = new KurvaSTemplateImport($jk);
        $import = new KurvaSRencanaImport($jk, $parent);

        $import->collection(collect([
            collect(['no' => 1, 'work_group' => 'Engineering', 'bobot' => 100, 'b1_rencana' => 6, 'b1_keterangan' => '', 'b2_rencana' => 4, 'b2_keterangan' => '']),
        ]));

        $this->assertFalse($parent->hasErrors());
        $this->assertSame(10.0, (float) collect($parent->getRencanaData()['Engineering']['monthly_data'])->sum('pct_rencana'));
    }

    public function test_laporan_save_diblokir_bila_kumulatif_melebihi_bobot(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();
        $wg = $this->seedWorkGroup($jk, 10.0, [1 => 10.0]);

        // Laporan minggu 1 sudah melaporkan 8 poin dari bobot 10
        $laporan1 = LaporanMingguan::create([
            'user_id' => $admin->id,
            'data_kapal_id' => $jk->id,
            'tanggal_laporan' => '2026-01-21',
            'minggu_ke' => 1,
        ]);
        $this->service()->saveProgress($laporan1, [$wg->id => 8.0]);

        // Laporan minggu 2: input 5 → total 13 > bobot 10 → diblokir
        Livewire::actingAs($admin)
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $jk])
            ->set('tanggal_laporan', '2026-01-28')
            ->set('minggu_ke', 2)
            ->set('progressPerGroup.'.$wg->id, 5.0)
            ->call('save');

        $this->assertSame(1, LaporanMingguan::where('data_kapal_id', $jk->id)->count());
    }

    public function test_export_kolom_bulan_mengikuti_kontrak(): void
    {
        // Kontrak 90 hari dari 1 Jan 2025 → 3 bulan → kolom B1-B3
        $jk = DataKapal::create([
            'nama' => 'Kapal Uji',
            'status' => 'active',
            'tanggal_mulai_kontrak' => '2025-01-01',
            'durasi_kontrak_hari' => 90,
        ]);

        $this->assertSame(3, KurvaSRencanaSheet::resolveMonths($jk));

        $sheet = new KurvaSRencanaSheet($jk, false);
        // headings: No, Work Group, Bobot, Kumulatif, Sisa + 2 kolom per bulan
        $this->assertCount(5 + (3 * 2), $sheet->headings());
        // baris data: No, Work Group, Bobot + 2 kolom per bulan
        $this->assertCount(3 + (3 * 2), $sheet->array()[0]);
    }

    public function test_export_kolom_bulan_fallback_data_dan_default(): void
    {
        $jk = $this->dataKapal();
        // Tanpa kontrak & tanpa data → default 24
        $this->assertSame(24, KurvaSRencanaSheet::resolveMonths($jk));

        // Tanpa kontrak, data hingga bulan 5 → ikut data
        $this->seedWorkGroup($jk, 100.0, [5 => 1.0]);
        $this->assertSame(5, KurvaSRencanaSheet::resolveMonths($jk));
    }

    public function test_split_to_weeks_truncate_3_digit_dan_sisa_ke_minggu_terakhir(): void
    {
        $service = $this->service();

        // 10 / 3 = 3.3333… → cut 3.333, sisa 0.001 ke minggu terakhir
        $split = $service->splitToWeeks(10.0, 3);
        $this->assertSame([3.333, 3.333, 3.334], $split);
        $this->assertEqualsWithDelta(10.0, array_sum($split), 0.0001);

        // Pembagian bulat tetap tepat
        $this->assertSame([1.5, 1.5, 1.5, 1.5], $service->splitToWeeks(6.0, 4));

        // 1 / 6 = 0.1666… → cut 0.166, minggu terakhir menyerap sisa
        $split = $service->splitToWeeks(1.0, 6);
        $this->assertSame([0.166, 0.166, 0.166, 0.166, 0.166, 0.17], $split);
        $this->assertEqualsWithDelta(1.0, array_sum($split), 0.0001);
    }

    public function test_get_week_plans_menggunakan_override_mingguan_tersimpan(): void
    {
        $jk = $this->dataKapalKontrak();
        $wg = $this->seedWorkGroup($jk, 10.0, [1 => 4.0, 3 => 6.0]);

        // Bulan 3 (mgg 9-12): override manual — bukan bagi rata 6/4 = 1.5
        foreach ([9 => 2.0, 10 => 2.0, 11 => 1.0, 12 => 1.0] as $minggu => $pct) {
            KurvaSRencanaMingguan::create([
                'work_group_id' => $wg->id,
                'minggu_ke' => $minggu,
                'pct_rencana' => $pct,
            ]);
        }

        // Mingguan menang: pakai nilai tersimpan
        $this->assertEqualsWithDelta(2.0, (float) $this->service()->getWeekPlans($jk, 9)[$wg->id], 0.0001);
        $this->assertEqualsWithDelta(1.0, (float) $this->service()->getWeekPlans($jk, 12)[$wg->id], 0.0001);

        // Bulan 1 (mgg 1-4) tanpa override → fallback bagi rata 4/4 = 1.0
        $this->assertEqualsWithDelta(1.0, (float) $this->service()->getWeekPlans($jk, 2)[$wg->id], 0.0001);
    }

    public function test_get_week_plans_bagi_rata_hasil_3_digit(): void
    {
        $jk = $this->dataKapalKontrak();
        $wg = $this->seedWorkGroup($jk, 10.0, [3 => 5.0]); // bulan 3 = mgg 9-12 → 1.25/mgg

        $plans = $this->service()->getWeekPlans($jk, 9);
        $this->assertEqualsWithDelta(1.25, (float) $plans[$wg->id], 0.0001);

        // Nilai tidak habis dibagi: 5 / 3 mgg (bulan 1 = mgg 1-4? gunakan 3 mgg lewat bulan 5: mgg 18-20)
        $jk2 = $this->dataKapalKontrak();
        $wg2 = $this->seedWorkGroup($jk2, 10.0, [5 => 5.0]); // bulan 5 = mgg 18-20 (3 mgg)
        $plans2 = $this->service()->getWeekPlans($jk2, 18);
        $this->assertEqualsWithDelta(1.666, (float) $plans2[$wg2->id], 0.0001);
        // Minggu terakhir menyerap sisa → total = 5.0
        $total = 0.0;
        foreach ([18, 19, 20] as $m) {
            $total += (float) $this->service()->getWeekPlans($jk2, $m)[$wg2->id];
        }
        $this->assertEqualsWithDelta(5.0, $total, 0.0001);
    }

    public function test_save_work_groups_menyimpan_dan_menghapus_override_mingguan(): void
    {
        $jk = $this->dataKapalKontrak();

        $this->service()->saveWorkGroups($jk, [[
            'nama' => 'Engineering',
            'bobot' => '10.00',
            'months' => [
                [
                    'bulan_ke' => 3,
                    'pct_rencana' => '6.00',
                    'keterangan' => '',
                    'weeks' => [
                        ['minggu_ke' => 9, 'pct_rencana' => '2.500'],
                        ['minggu_ke' => 10, 'pct_rencana' => '2.000'],
                        ['minggu_ke' => 11, 'pct_rencana' => '1.000'],
                        ['minggu_ke' => 12, 'pct_rencana' => '0.500'],
                    ],
                ],
            ],
        ]]);

        $this->assertSame(4, KurvaSRencanaMingguan::count());
        $this->assertDatabaseHas('kurva_s_rencana_mingguan', [
            'minggu_ke' => 9,
            'pct_rencana' => 2.5,
        ]);

        // Simpan ulang tanpa 'weeks' → override mingguan terhapus (fallback bagi rata)
        $this->service()->saveWorkGroups($jk, [[
            'nama' => 'Engineering',
            'bobot' => '10.00',
            'months' => [
                ['bulan_ke' => 3, 'pct_rencana' => '6.00', 'keterangan' => ''],
            ],
        ]]);

        $this->assertSame(0, KurvaSRencanaMingguan::count());
    }

    public function test_management_bagi_rata_mengisi_mingguan_dari_bulanan(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2026-01-05')
            ->set('durasi_kontrak_hari', 140)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    ['bulan_ke' => 1, 'pct_rencana' => '10.00', 'keterangan' => ''], // mgg 1-4 → 2.5/mgg
                    ['bulan_ke' => 2, 'pct_rencana' => '0.00', 'keterangan' => ''],
                ],
            ]])
            ->call('bagiRata', 0, 0)
            ->assertSet('expandedWeeksKey', '0-0')
            ->assertSet('workGroups.0.months.0.weeks', function ($weeks) {
                return count($weeks) === 4
                    && collect($weeks)->sum(fn ($w) => (float) $w['pct_rencana']) == 10.0
                    && $weeks[0]['minggu_ke'] === 1;
            });
    }

    public function test_management_save_ditolak_bila_total_mingguan_melebihi_bobot(): void
    {
        $this->seed();
        $admin = User::firstOrFail();
        $jk = $this->dataKapal();

        // Bulanan 8% ≤ bobot 10 — tapi mingguan di-override hingga total 20% → tolak
        Livewire::actingAs($admin)
            ->test(KurvaSManagement::class)
            ->call('openModal', $jk->id)
            ->set('tanggal_mulai_kontrak', '2026-01-05')
            ->set('durasi_kontrak_hari', 140)
            ->set('workGroups', [[
                'id' => null,
                'nama' => 'Engineering',
                'bobot' => '10.00',
                'months' => [
                    [
                        'bulan_ke' => 1,
                        'pct_rencana' => '8.00',
                        'keterangan' => '',
                        'weeks' => [
                            ['minggu_ke' => 1, 'pct_rencana' => '5.00'],
                            ['minggu_ke' => 2, 'pct_rencana' => '5.00'],
                            ['minggu_ke' => 3, 'pct_rencana' => '5.00'],
                            ['minggu_ke' => 4, 'pct_rencana' => '5.00'],
                        ],
                    ],
                ],
            ]])
            ->call('save')
            ->assertSet('showModal', true);

        $this->assertSame(0, KurvaSWorkGroup::where('data_kapal_id', $jk->id)->count());
    }
}
