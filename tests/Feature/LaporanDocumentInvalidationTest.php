<?php

namespace Tests\Feature;

use App\Enums\NcStatus;
use App\Jobs\ProcessLaporanExternal;
use App\Models\LaporanBulanan;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\LaporanNc;
use App\Models\User;
use App\Services\FileStorageService;
use App\Services\LaporanBulananService;
use App\Services\LaporanDocumentService;
use App\Services\LaporanHarianService;
use App\Services\LaporanMingguanService;
use App\Services\LaporanNcService;
use App\Services\QueueStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan induk memakai ulang docx anak yang sudah tergenerate, jadi
 * perubahan isi wajib mengosongkan docx sendiri + induk yang melampirkannya.
 */
class LaporanDocumentInvalidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function modal_release_pdf_tidak_menyatakan_word_akan_dihapus(): void
    {
        $this->blade('<x-document-confirm-modal :show="true" type="release" doc-name="laporan.docx" />')
            ->assertSee('Dokumen Word sumber (tetap tersimpan)')->assertSee('laporan.docx')
            ->assertSee('Ya, Release PDF')->assertDontSee('File akan dihapus permanen')
            ->assertDontSee('Ya, Hapus')->assertDontSee('Ya, Generate Ulang');
        $this->blade('<x-document-confirm-modal :show="true" type="delete" doc-name="laporan.docx" />')
            ->assertSee('File akan dihapus permanen')->assertSee('Ya, Hapus');
        $this->blade('<x-document-confirm-modal :show="true" type="regenerate" doc-name="laporan.docx" />')
            ->assertSee('Dokumen yang akan dibuat ulang')->assertSee('Ya, Generate Ulang');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    /**
     * @return array{0: LaporanBulanan, 1: LaporanMingguan, 2: LaporanHarian}
     */
    private function rantai(): array
    {
        $bulanan = LaporanBulanan::firstOrFail();
        $mingguan = app(LaporanBulananService::class)->getMingguanBulan($bulanan)->firstOrFail();
        $harian = $mingguan->laporanHarian()->first();
        if (! $harian) {
            $harian = LaporanHarian::where('data_kapal_id', $mingguan->data_kapal_id)
                ->whereBetween('tanggal_laporan', [$mingguan->periode_mulai, $mingguan->periode_selesai])
                ->firstOrFail();
            app(LaporanMingguanService::class)->releaseHarianFromOtherMingguan([$harian->id], $mingguan->id);
            $mingguan->laporanHarian()->attach($harian->id);
        }

        foreach ([$bulanan, $mingguan, $harian] as $laporan) {
            $this->beriDokumen($laporan);
        }

        return [$bulanan, $mingguan, $harian];
    }

    private function beriDokumen(Model $laporan): void
    {
        $key = class_basename($laporan).'-'.$laporan->id;
        Storage::disk('local')->put("laporan/word/{$key}.docx", 'docx');
        Storage::disk('local')->put("laporan/pdf/{$key}.pdf", 'pdf');
        Storage::disk('local')->put('laporan/lampiran-pages/'.sha1("laporan/word/{$key}.docx").'/pages.json', '[]');
        $laporan->update([
            'doc_path' => "laporan/word/{$key}.docx",
            'doc_status' => 'completed',
            'pdf_path' => "laporan/pdf/{$key}.pdf",
            'pdf_status' => 'completed',
        ]);
    }

    private function assertDokumenKosong(Model $laporan): void
    {
        $key = class_basename($laporan).'-'.$laporan->id;
        $laporan->refresh();
        $this->assertNull($laporan->doc_path, "$key doc_path");
        $this->assertNull($laporan->doc_status, "$key doc_status");
        $this->assertNull($laporan->pdf_path, "$key pdf_path");
        Storage::disk('local')->assertMissing("laporan/word/{$key}.docx");
        Storage::disk('local')->assertMissing("laporan/pdf/{$key}.pdf");
        Storage::disk('local')->assertMissing('laporan/lampiran-pages/'.sha1("laporan/word/{$key}.docx"));
    }

    #[Test]
    public function edit_harian_mengosongkan_dokumen_harian_mingguan_dan_bulanan(): void
    {
        [$bulanan, $mingguan, $harian] = $this->rantai();

        app(LaporanHarianService::class)->update($harian, []);

        $this->assertDokumenKosong($harian);
        $this->assertDokumenKosong($mingguan);
        $this->assertDokumenKosong($bulanan);
    }

    #[Test]
    public function proses_dan_hapus_dokumen_pendukung_harian_menginvalidasi_rantai(): void
    {
        [$bulanan, $mingguan, $harian] = $this->rantai();
        $external = $harian->laporanExternal()->create(['judul' => 'Inspeksi', 'file_name' => 'scan.jpg', 'file_status' => 'pending']);
        $temp = app(FileStorageService::class)->storeTemp(UploadedFile::fake()->image('scan.jpg'), 'laporan-external');
        (new ProcessLaporanExternal($external->id, $temp))->handle(app(QueueStatusService::class));
        $this->assertTrue($external->fresh()->isFileCompleted());
        foreach ([$harian, $mingguan, $bulanan] as $report) {
            $this->assertDokumenKosong($report);
            $this->beriDokumen($report);
        }
        app(LaporanHarianService::class)->deleteExternal($harian, $external->id);
        $this->assertDatabaseMissing('laporan_external', ['id' => $external->id]);
        foreach ([$harian, $mingguan, $bulanan] as $report) {
            $this->assertDokumenKosong($report);
        }
    }

    #[Test]
    public function hapus_dokumen_manual_tidak_berantai(): void
    {
        [$bulanan, $mingguan, $harian] = $this->rantai();

        app(LaporanDocumentService::class)->clearDoc($harian);

        $this->assertDokumenKosong($harian);
        $this->assertSame('completed', $mingguan->fresh()->doc_status);
        $this->assertSame('completed', $bulanan->fresh()->doc_status);
    }

    #[Test]
    public function reopen_nc_mengosongkan_mingguan_sejak_nc_dibuat_saja(): void
    {
        [, $mingguan, $harian] = $this->rantai();
        $sebelum = LaporanMingguan::where('data_kapal_id', $mingguan->data_kapal_id)
            ->whereDate('periode_selesai', '<', $mingguan->periode_mulai)
            ->orderByDesc('periode_selesai')
            ->first();
        if ($sebelum) {
            $this->beriDokumen($sebelum);
        }

        $nc = LaporanNc::create([
            'laporan_harian_id' => $harian->id,
            'data_kapal_id' => $harian->data_kapal_id,
            'nomor' => 'NC-UJI-001',
            'kendala' => 'Uji invalidasi',
            'status' => NcStatus::Closed,
            'closed_at' => now(),
        ]);
        $nc->forceFill(['created_at' => $mingguan->periode_mulai])->save();

        app(LaporanNcService::class)->reopen($nc, User::firstOrFail()->id, 'Uji');

        $this->assertDokumenKosong($harian);
        $this->assertDokumenKosong($mingguan);
        if ($sebelum) {
            $this->assertSame('completed', $sebelum->fresh()->doc_status, 'Mingguan sebelum NC dibuat tidak memuat NC.');
        }
    }
}
