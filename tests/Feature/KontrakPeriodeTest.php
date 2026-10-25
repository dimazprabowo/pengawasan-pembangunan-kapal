<?php

namespace Tests\Feature;

use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Models\DataKapal;
use App\Models\LaporanMingguan;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KontrakPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): KontrakPeriodeService
    {
        return app(KontrakPeriodeService::class);
    }

    private function dataKapalKontrak(int $durasi = 300): DataKapal
    {
        return DataKapal::create([
            'nama' => 'Kapal Uji',
            'status' => 'active',
            'tanggal_mulai_kontrak' => '2026-01-15',
            'durasi_kontrak_hari' => $durasi,
        ]);
    }

    public function test_has_kontrak_memerlukan_kedua_field(): void
    {
        $jk = new DataKapal;
        $this->assertFalse($jk->hasKontrak());

        $jk->tanggal_mulai_kontrak = '2026-01-15';
        $this->assertFalse($jk->hasKontrak());

        $jk->durasi_kontrak_hari = 300;
        $this->assertTrue($jk->hasKontrak());
    }

    public function test_tanggal_selesai_kontrak_inklusif(): void
    {
        $jk = $this->dataKapalKontrak(300);
        // 15 Jan + 299 hari = 10 Nov 2026
        $this->assertSame('2026-11-10', $jk->tanggal_selesai_kontrak->format('Y-m-d'));
    }

    public function test_total_minggu_sisa_digabung_ke_minggu_terakhir(): void
    {
        // 300 hari = 42 minggu penuh + 6 hari sisa → 42 minggu (sisa masuk minggu terakhir)
        $this->assertSame(42, $this->service()->getTotalMinggu($this->dataKapalKontrak(300)));
        $this->assertSame(100, $this->service()->getTotalMinggu($this->dataKapalKontrak(700)));
        $this->assertSame(3, $this->service()->getTotalMinggu($this->dataKapalKontrak(21)));
        $this->assertSame(2, $this->service()->getTotalMinggu($this->dataKapalKontrak(20)));
        $this->assertSame(1, $this->service()->getTotalMinggu($this->dataKapalKontrak(13)));
        $this->assertSame(1, $this->service()->getTotalMinggu($this->dataKapalKontrak(1)));
        $this->assertSame(0, $this->service()->getTotalMinggu(new DataKapal));
    }

    public function test_minggu_periode_blok_7_hari(): void
    {
        $jk = $this->dataKapalKontrak(300);

        $m1 = $this->service()->getMingguPeriode($jk, 1);
        $this->assertSame('2026-01-15', $m1['mulai']->format('Y-m-d'));
        $this->assertSame('2026-01-21', $m1['selesai']->format('Y-m-d'));

        $m2 = $this->service()->getMingguPeriode($jk, 2);
        $this->assertSame('2026-01-22', $m2['mulai']->format('Y-m-d'));
        $this->assertSame('2026-01-28', $m2['selesai']->format('Y-m-d'));
    }

    public function test_minggu_terakhir_menyerap_sisa_hari_maks_13_hari(): void
    {
        $jk = $this->dataKapalKontrak(300);

        // Minggu 42 = hari 288-300 → 7 + 6 sisa = 13 hari (29 Okt - 10 Nov)
        $m42 = $this->service()->getMingguPeriode($jk, 42);
        $this->assertSame('2026-10-29', $m42['mulai']->format('Y-m-d'));
        $this->assertSame('2026-11-10', $m42['selesai']->format('Y-m-d'));

        // Durasi 10 hari → satu minggu 10 hari (tanpa minggu baru)
        $jk10 = $this->dataKapalKontrak(10);
        $this->assertSame(1, $this->service()->getTotalMinggu($jk10));
        $m1 = $this->service()->getMingguPeriode($jk10, 1);
        $this->assertSame('2026-01-15', $m1['mulai']->format('Y-m-d'));
        $this->assertSame('2026-01-24', $m1['selesai']->format('Y-m-d'));
    }

    public function test_minggu_list_memuat_total_hari(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $minggu = $this->service()->getMingguList($jk);

        $this->assertCount(42, $minggu);
        $this->assertSame(7, $minggu[0]['total_hari']);
        $this->assertSame(13, $minggu[41]['total_hari']);
        $this->assertSame(array_sum(array_column($minggu, 'total_hari')), 300);
    }

    public function test_minggu_periode_di_luar_kontrak_null(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $this->assertNull($this->service()->getMingguPeriode($jk, 0));
        $this->assertNull($this->service()->getMingguPeriode($jk, 43));
    }

    public function test_bulan_list_mengikuti_mayoritas_tanggal(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $bulan = $this->service()->getBulanList($jk);

        // Minggu masuk ke bulan MAYORITAS harinya: minggu 3 (29 Jan - 4 Feb)
        // → 4 hari Feb → masuk Feb; minggu terakhir (29 Okt - 10 Nov)
        // → 10 hari Nov → masuk Nov. Januari cuma 2 minggu (bulan pertama
        // bebas), November 1 minggu (bulan terakhir bebas) → 11 bulan.
        $this->assertCount(11, $bulan);
        $this->assertSame(11, $this->service()->getTotalBulan($jk));

        // Bulan 1 (Januari): minggu 1-2, periode 15-28 Jan
        $this->assertSame(1, $bulan[0]['bulan_ke']);
        $this->assertSame('January 2026', $bulan[0]['label']);
        $this->assertSame('2026-01-15', $bulan[0]['mulai']->format('Y-m-d'));
        $this->assertSame('2026-01-28', $bulan[0]['selesai']->format('Y-m-d'));
        $this->assertSame(1, $bulan[0]['minggu_dari']);
        $this->assertSame(2, $bulan[0]['minggu_sampai']);
        $this->assertSame(2, $bulan[0]['total_minggu']);
        $this->assertSame(14, $bulan[0]['total_hari']);

        // Bulan 2 (Februari): minggu 3-6 — dimulai minggu 29 Jan - 4 Feb
        // yang mayoritasnya (4 hari) di Februari
        $this->assertSame('February 2026', $bulan[1]['label']);
        $this->assertSame('2026-01-29', $bulan[1]['mulai']->format('Y-m-d'));
        $this->assertSame('2026-02-25', $bulan[1]['selesai']->format('Y-m-d'));
        $this->assertSame(3, $bulan[1]['minggu_dari']);
        $this->assertSame(6, $bulan[1]['minggu_sampai']);
        $this->assertSame(4, $bulan[1]['total_minggu']);
        $this->assertSame(28, $bulan[1]['total_hari']);

        // Bulan terakhir = November (hanya minggu 42: 29 Okt - 10 Nov)
        $last = end($bulan);
        $this->assertSame(11, $last['bulan_ke']);
        $this->assertSame('November 2026', $last['label']);
        $this->assertSame('2026-10-29', $last['mulai']->format('Y-m-d'));
        $this->assertSame('2026-11-10', $last['selesai']->format('Y-m-d'));
        $this->assertSame(42, $last['minggu_dari']);
        $this->assertSame(42, $last['minggu_sampai']);
        $this->assertSame(1, $last['total_minggu']);
        $this->assertSame(13, $last['total_hari']);
    }

    public function test_bulan_tengah_minimal_empat_minggu(): void
    {
        // Kontrak mulai 12 Mei 2026, 300 hari = 42 minggu (s.d. 8 Mar 2027).
        // Februari 2027 secara natural cuma 3 minggu (w39-41); w42 (2-8 Mar)
        // mayoritas Maret. Karena bulan tengah wajib ≥4, w42 ditarik ke
        // Februari → Maret kehabisan minggu dan hilang dari daftar.
        $jk = DataKapal::create([
            'nama' => 'Kapal Uji',
            'status' => 'active',
            'tanggal_mulai_kontrak' => '2026-05-12',
            'durasi_kontrak_hari' => 300,
        ]);

        $bulan = $this->service()->getBulanList($jk);

        $this->assertCount(10, $bulan);
        // Bulan pertama (Mei) cuma 3 minggu — diizinkan
        $this->assertSame('May 2026', $bulan[0]['label']);
        $this->assertSame(3, $bulan[0]['total_minggu']);
        // Bulan terakhir = Februari 2027 dengan 4 minggu (w39-42) setelah
        // menarik minggu 42 dari Maret; Maret tidak muncul lagi
        $last = end($bulan);
        $this->assertSame('February 2027', $last['label']);
        $this->assertSame([39, 42], [$last['minggu_dari'], $last['minggu_sampai']]);
        $this->assertSame(4, $last['total_minggu']);
        // Tidak ada bulan interior di bawah 4 minggu
        foreach (array_slice($bulan, 1, -1) as $b) {
            $this->assertGreaterThanOrEqual(4, $b['total_minggu'], $b['label'].' harus ≥4 minggu');
        }
    }

    public function test_bulan_pertama_tanpa_minggu_dilewati(): void
    {
        // Kontrak mulai 29 Jan 2026, 28 hari = 4 minggu — w1 (29 Jan - 4 Feb)
        // mayoritas Februari (4 hari), sisanya penuh di Feb → Januari tidak
        // mendapat minggu → dilewati; daftar bulan dimulai dari Februari.
        $jk = DataKapal::create([
            'nama' => 'Kapal Uji',
            'status' => 'active',
            'tanggal_mulai_kontrak' => '2026-01-29',
            'durasi_kontrak_hari' => 28,
        ]);

        $bulan = $this->service()->getBulanList($jk);

        $this->assertCount(1, $bulan);
        $this->assertSame(1, $bulan[0]['bulan_ke']);
        $this->assertSame('February 2026', $bulan[0]['label']);
        $this->assertSame(1, $bulan[0]['minggu_dari']);
        $this->assertSame(4, $bulan[0]['minggu_sampai']);
        $this->assertSame(4, $bulan[0]['total_minggu']);

        // Map minggu→bulan: semua minggu ke bulan 1
        $map = $this->service()->getMingguBulanMap($jk);
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1], $map);
    }

    public function test_periode_bulan_mencakup_seluruh_kontrak_tanpa_irisan(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $bulan = $this->service()->getBulanList($jk);

        // Rentang bulan saling bersambungan dan mencakup penuh rentang kontrak
        $this->assertSame('2026-01-15', $bulan[0]['mulai']->format('Y-m-d'));
        $this->assertSame('2026-11-10', end($bulan)['selesai']->format('Y-m-d'));
        $this->assertSame(300, array_sum(array_column($bulan, 'total_hari')));

        for ($i = 1; $i < count($bulan); $i++) {
            $this->assertSame(
                $bulan[$i]['mulai']->format('Y-m-d'),
                $bulan[$i - 1]['selesai']->copy()->addDay()->format('Y-m-d'),
                'Periode bulan harus kontigu tanpa celah'
            );
        }
    }

    public function test_minggu_options_mengecualikan_yang_sudah_dilaporkan(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $user = User::factory()->create();

        LaporanMingguan::create([
            'user_id' => $user->id,
            'data_kapal_id' => $jk->id,
            'tanggal_laporan' => '2026-01-22',
            'periode_mulai' => '2026-01-15',
            'periode_selesai' => '2026-01-21',
            'minggu_ke' => 1,
        ]);

        $options = $this->service()->getMingguOptions($jk);
        $this->assertCount(41, $options);
        $this->assertNotContains(1, array_column($options, 'value'));

        // Label menyertakan rentang tanggal
        $this->assertStringContainsString('Minggu ke-2', $options[0]['label']);
        $this->assertStringContainsString('22 - 28 Jan 2026', $options[0]['label']);

        // Mode edit: minggu sendiri tetap tersedia
        $optionsEdit = $this->service()->getMingguOptions($jk, 1);
        $this->assertCount(42, $optionsEdit);
    }

    public function test_minggu_options_kosong_tanpa_kontrak(): void
    {
        $this->assertSame([], $this->service()->getMingguOptions(new DataKapal));
    }

    public function test_minggu_ke_from_date(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $this->assertSame(1, $this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-01-15')));
        $this->assertSame(1, $this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-01-21')));
        $this->assertSame(2, $this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-01-22')));
        // Tanggal di ekor minggu terakhir (gabungan sisa) di-clamp ke minggu 42
        $this->assertSame(42, $this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-11-10')));
        $this->assertSame(42, $this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-11-05')));
        $this->assertNull($this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-11-11')));
        $this->assertNull($this->service()->getMingguKeFromDate($jk, \Carbon\Carbon::parse('2026-01-14')));
    }

    public function test_save_kontrak_dan_hapus_kontrak(): void
    {
        $jk = DataKapal::create(['nama' => 'Kapal A', 'status' => 'active']);

        $this->service()->saveKontrak($jk, '2026-01-15', 300);
        $jk->refresh();
        $this->assertTrue($jk->hasKontrak());
        $this->assertSame('2026-01-15', $jk->tanggal_mulai_kontrak->format('Y-m-d'));
        $this->assertSame(300, $jk->durasi_kontrak_hari);
        $this->assertNull($jk->minggu_bulan_map);

        // Kosongkan keduanya = hapus kontrak (map ikut dibersihkan)
        $this->service()->saveKontrak($jk, null, null);
        $jk->refresh();
        $this->assertFalse($jk->hasKontrak());
    }

    public function test_minggu_bulan_map_memindahkan_minggu_irisan(): void
    {
        // Default: Feb = mgg 3-6, Mar = mgg 7-11. Minggu 7 (26 Feb - 4 Mar)
        // irisan Feb–Mar, default-nya masuk Maret — user memilih Februari.
        $jk = $this->dataKapalKontrak(300);
        $jk->minggu_bulan_map = [7 => 2];

        $bulan = $this->service()->getBulanList($jk);

        // Februari kini mgg 3-7 (5 mgg), Maret bergeser ke mgg 8-11 (4 mgg)
        $this->assertSame([3, 7, 5], [$bulan[1]['minggu_dari'], $bulan[1]['minggu_sampai'], $bulan[1]['total_minggu']]);
        $this->assertSame('2026-03-04', $bulan[1]['selesai']->format('Y-m-d'));
        $this->assertSame([8, 11, 4], [$bulan[2]['minggu_dari'], $bulan[2]['minggu_sampai'], $bulan[2]['total_minggu']]);
        $this->assertSame('2026-03-05', $bulan[2]['mulai']->format('Y-m-d'));

        // Label kalender & urutan bulan tidak berubah
        $this->assertSame('February 2026', $bulan[1]['label']);
        $this->assertSame('March 2026', $bulan[2]['label']);

        // Konsumen global ikut pemetaan baru
        $this->assertSame(2, $this->service()->getMingguBulanMap($jk)[7]);
        $periode = $this->service()->getBulanPeriode($jk, 2);
        $this->assertSame('2026-03-04', $periode['selesai']->format('Y-m-d'));
    }

    public function test_minggu_bulan_map_tarik_minggu_pertama_ke_bulan_sebelumnya(): void
    {
        // Arah sebaliknya: mgg 3 (29 Jan - 4 Feb, minggu pertama Februari)
        // ditarik ke Januari → Jan = mgg 1-3, Feb = mgg 4-6.
        $jk = $this->dataKapalKontrak(300);
        $jk->minggu_bulan_map = [3 => 1];

        $bulan = $this->service()->getBulanList($jk);

        $this->assertSame([1, 3, 3], [$bulan[0]['minggu_dari'], $bulan[0]['minggu_sampai'], $bulan[0]['total_minggu']]);
        $this->assertSame('2026-02-04', $bulan[0]['selesai']->format('Y-m-d'));
        $this->assertSame([4, 6, 3], [$bulan[1]['minggu_dari'], $bulan[1]['minggu_sampai'], $bulan[1]['total_minggu']]);
        $this->assertSame('2026-02-05', $bulan[1]['mulai']->format('Y-m-d'));
    }

    public function test_minggu_bulan_map_minggu_non_irisan_ditolak_meski_berantai(): void
    {
        // Setelah mgg 7 pindah ke Feb, mgg 8 (5-11 Mar, murni Maret) memang
        // menjadi minggu pertama Mar — tapi tanggalnya tidak beririsan dua
        // bulan sehingga tidak bisa ikut dipindah.
        $jk = $this->dataKapalKontrak(300);
        $jk->minggu_bulan_map = [7 => 2, 8 => 2];

        $bulan = $this->service()->getBulanList($jk);

        // Hanya mgg 7 yang pindah: Feb = mgg 3-7, Mar = mgg 8-11
        $this->assertSame([3, 7, 5], [$bulan[1]['minggu_dari'], $bulan[1]['minggu_sampai'], $bulan[1]['total_minggu']]);
        $this->assertSame([8, 11, 4], [$bulan[2]['minggu_dari'], $bulan[2]['minggu_sampai'], $bulan[2]['total_minggu']]);
    }

    public function test_minggu_bulan_map_override_invalid_diabaikan(): void
    {
        $jk = $this->dataKapalKontrak(300);
        $default = $this->service()->getBulanList($jk);

        // w4 = interior Februari (bukan batas) → diabaikan
        $jk->minggu_bulan_map = [4 => 3];
        $this->assertEquals($default, $this->service()->getBulanList($jk));

        // w6 → bulan 4 bukan tetangga (Feb = bulan 2) → diabaikan
        $jk->minggu_bulan_map = [6 => 4];
        $this->assertEquals($default, $this->service()->getBulanList($jk));

        // w6 → bulan 3 memang tetangga & w6 batas akhir Feb, tapi w6
        // (19-25 Feb) murni satu bulan → tetap diabaikan
        $jk->minggu_bulan_map = [6 => 3];
        $this->assertEquals($default, $this->service()->getBulanList($jk));

        // w42 satu-satunya minggu November — sumber tidak boleh kosong
        $jk->minggu_bulan_map = [42 => 10];
        $this->assertEquals($default, $this->service()->getBulanList($jk));

        // Minggu di luar kontrak → diabaikan
        $jk->minggu_bulan_map = [99 => 2];
        $this->assertEquals($default, $this->service()->getBulanList($jk));
    }

    public function test_save_kontrak_menyimpan_minggu_bulan_map(): void
    {
        $jk = DataKapal::create(['nama' => 'Kapal A', 'status' => 'active']);

        $this->service()->saveKontrak($jk, '2026-01-15', 300, [7 => 2]);
        $jk->refresh();
        $this->assertSame([7 => 2], $jk->minggu_bulan_map);

        $bulan = $this->service()->getBulanList($jk);
        $this->assertSame(7, $bulan[1]['minggu_sampai']);

        // Null = kembali ke pengelompokan kalender
        $this->service()->saveKontrak($jk, '2026-01-15', 300, null);
        $jk->refresh();
        $this->assertNull($jk->minggu_bulan_map);
        $this->assertSame(6, $this->service()->getBulanList($jk)[1]['minggu_sampai']);
    }

    public function test_form_create_auto_isi_periode_saat_pilih_minggu(): void
    {
        $this->seed();
        $jk = $this->dataKapalKontrak(300);
        $admin = User::firstOrFail();

        Livewire::actingAs($admin)
            ->test(LaporanMingguanCreate::class, ['dataKapal' => $jk])
            ->assertSet('hasKontrak', true)
            ->set('minggu_ke', 3)
            ->assertSet('periode_mulai', '2026-01-29')
            ->assertSet('periode_selesai', '2026-02-04');
    }
}
