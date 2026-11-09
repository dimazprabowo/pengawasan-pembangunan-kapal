<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LaporanAktivitas;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * File lampiran/NC di-serve via route (bukan base64 di payload Livewire —
 * penyebab PayloadTooLargeException di form mingguan). Route wajib: izin
 * laporan_lampiran_preview + scope data kapal perusahaan + file completed.
 */
class LampiranFileRouteTest extends TestCase
{
    use RefreshDatabase;

    private LaporanLampiran $lampiran;

    private LaporanHarian $harian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');

        $this->harian = LaporanHarian::firstOrFail();
        $aktivitas = LaporanAktivitas::create([
            'laporan_harian_id' => $this->harian->id,
            'aktivitas' => 'Aktivitas uji lampiran',
        ]);

        Storage::disk('local')->put('test/foto.webp', 'fake-webp-bytes');
        $this->lampiran = LaporanLampiran::create([
            'laporan_harian_id' => $this->harian->id,
            'laporan_aktivitas_id' => $aktivitas->id,
            'file_path' => 'test/foto.webp',
            'file_name' => 'foto.webp',
            'file_size' => 15,
            'file_status' => 'completed',
        ]);
    }

    private function userDenganIzin(?int $companyId = null): User
    {
        $user = User::factory()->create([
            'company_id' => $companyId ?? $this->harian->dataKapal->company_id,
        ]);
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'laporan_lampiran_preview', 'guard_name' => 'web'])
        );

        return $user;
    }

    public function test_file_lampiran_diserve_untuk_user_berizin_dan_scope_sesuai(): void
    {
        $response = $this->actingAs($this->userDenganIzin())
            ->get(route('laporan-lampiran.file', $this->lampiran));

        $response->assertOk();
        $this->assertStringContainsString('image/webp', $response->headers->get('content-type'));
        $this->assertStringContainsString('immutable', $response->headers->get('cache-control'));
        // File di-preview via <iframe> — DENY memblokir render (refused to connect).
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_file_lampiran_ditolak_tanpa_permission(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->harian->dataKapal->company_id,
        ]);

        $this->actingAs($user)
            ->get(route('laporan-lampiran.file', $this->lampiran))
            ->assertForbidden();
    }

    public function test_file_lampiran_ditolak_beda_perusahaan(): void
    {
        $lain = Company::create(['code' => 'LAIN', 'name' => 'PT Lain', 'status' => 'active']);

        $this->actingAs($this->userDenganIzin($lain->id))
            ->get(route('laporan-lampiran.file', $this->lampiran))
            ->assertForbidden();
    }

    public function test_file_lampiran_404_bila_belum_selesai_atau_file_hilang(): void
    {
        $aktivitas2 = LaporanAktivitas::create([
            'laporan_harian_id' => $this->harian->id,
            'aktivitas' => 'Aktivitas uji kedua',
        ]);
        $aktivitas3 = LaporanAktivitas::create([
            'laporan_harian_id' => $this->harian->id,
            'aktivitas' => 'Aktivitas uji ketiga',
        ]);
        $pending = LaporanLampiran::create([
            'laporan_harian_id' => $this->harian->id,
            'laporan_aktivitas_id' => $aktivitas2->id,
            'file_path' => 'test/pending.webp',
            'file_name' => 'pending.webp',
            'file_size' => 10,
            'file_status' => 'processing',
        ]);
        $hilang = LaporanLampiran::create([
            'laporan_harian_id' => $this->harian->id,
            'laporan_aktivitas_id' => $aktivitas3->id,
            'file_path' => 'test/tidak-ada.webp',
            'file_name' => 'tidak-ada.webp',
            'file_size' => 10,
            'file_status' => 'completed',
        ]);
        $user = $this->userDenganIzin();

        $this->actingAs($user)->get(route('laporan-lampiran.file', $pending))->assertNotFound();
        $this->actingAs($user)->get(route('laporan-lampiran.file', $hilang))->assertNotFound();
    }

    public function test_foto_nc_diserve_untuk_user_berizin_dan_ditolak_beda_perusahaan(): void
    {
        Storage::disk('local')->put('test/nc.webp', 'fake-webp-bytes');
        $nc = LaporanNc::create([
            'laporan_harian_id' => $this->harian->id,
            'data_kapal_id' => $this->harian->data_kapal_id,
            'kendala' => 'Uji foto NC',
        ]);
        $foto = $nc->foto()->create([
            'file_path' => 'test/nc.webp',
            'file_name' => 'nc.webp',
            'file_size' => 15,
            'file_status' => 'completed',
        ]);

        $this->actingAs($this->userDenganIzin())
            ->get(route('laporan-nc-foto.file', $foto))
            ->assertOk();

        $lain = Company::create(['code' => 'LAIN2', 'name' => 'PT Lain 2', 'status' => 'active']);
        $this->actingAs($this->userDenganIzin($lain->id))
            ->get(route('laporan-nc-foto.file', $foto))
            ->assertForbidden();
    }

    public function test_scan_form_nc_diserve_untuk_user_berizin(): void
    {
        Storage::disk('local')->put('test/ncr.pdf', 'fake-pdf-bytes');
        $form = LaporanNcForm::create([
            'data_kapal_id' => $this->harian->data_kapal_id,
            'jenis' => 'penutupan',
            'nomor' => $this->harian->dataKapal->kode.'-NCR-001',
            'tanggal' => now()->toDateString(),
            'file_path' => 'test/ncr.pdf',
            'file_name' => 'ncr.pdf',
            'file_status' => 'completed',
            'signed_at' => now(),
        ]);

        $response = $this->actingAs($this->userDenganIzin())
            ->get(route('laporan-nc-form.file', $form));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_halaman_biasa_tetap_dilindungi_frame_deny(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY');
    }
}
