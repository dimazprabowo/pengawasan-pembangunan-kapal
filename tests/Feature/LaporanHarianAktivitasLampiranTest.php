<?php

namespace Tests\Feature;

use App\Jobs\ProcessLaporanExternal;
use App\Jobs\ProcessLaporanLampiran;
use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanHarian\LaporanHarianEdit;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanAktivitas;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\User;
use App\Services\DocxToPdfService;
use App\Services\LaporanHarianService;
use App\Services\LaporanHarianWordService;
use App\Services\WordAttachmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LaporanHarianAktivitasLampiranTest extends TestCase
{
    use RefreshDatabase;

    private DataKapal $dataKapal;

    private KurvaSWorkGroup $wgLambung;

    private KurvaSWorkGroup $wgMesin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->dataKapal = DataKapal::create(['nama' => 'Kapal Uji Aktivitas', 'status' => 'active']);
        $this->wgLambung = KurvaSWorkGroup::create(['data_kapal_id' => $this->dataKapal->id, 'nama' => 'Konstruksi Lambung', 'bobot' => 60, 'sort_order' => 0]);
        $this->wgMesin = KurvaSWorkGroup::create(['data_kapal_id' => $this->dataKapal->id, 'nama' => 'Permesinan', 'bobot' => 40, 'sort_order' => 1]);
    }

    private function admin(): User
    {
        return User::firstOrFail();
    }

    private function laporan(): LaporanHarian
    {
        return LaporanHarian::create([
            'user_id' => $this->admin()->id,
            'data_kapal_id' => $this->dataKapal->id,
            'tanggal_laporan' => now()->subDays(2)->format('Y-m-d'),
        ]);
    }

    #[Test]
    public function laporan_tanpa_foto_hanya_memuat_pesan_kosong_di_daftar_dokumentasi(): void
    {
        $path = app(LaporanHarianWordService::class)->generate($this->laporan());
        try {
            $zip = new \ZipArchive;
            $zip->open(Storage::disk('local')->path($path));
            $text = strip_tags($zip->getFromName('word/document.xml'));
            $zip->close();
            $this->assertSame(1, substr_count($text, 'Tidak ada dokumentasi foto.'));
            $this->assertStringNotContainsString('Tidak ada dokumentasi foto.', substr($text, strpos($text, 'F. Lampiran'), strpos($text, '1. Dokumen Pendukung') - strpos($text, 'F. Lampiran')));
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public static function kondisiDokumenTanpaFoto(): array
    {
        return ['tanpa dokumen' => [false], 'dengan dokumen' => [true]];
    }

    #[Test]
    #[DataProvider('kondisiDokumenTanpaFoto')]
    public function render_harian_tanpa_foto_tidak_menambah_halaman_kosong(bool $withDocument): void
    {
        $binary = dirname(config('services.poppler.pdfinfo')).'/pdftotext'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        if (! is_executable($binary) || (! class_exists(\COM::class) && ! is_executable((string) config('services.libreoffice.binary')))) {
            $this->markTestSkipped('Engine konversi dan pdftotext diperlukan.');
        }
        $report = $this->laporan();
        $source = null;
        if ($withDocument) {
            $source = UploadedFile::fake()->image('dokumen.png', 600, 850)->store('laporan-test', 'local');
            $report->laporanExternal()->create(['judul' => 'Dokumen inspeksi', 'file_name' => 'dokumen.png', 'file_path' => $source, 'file_status' => 'completed']);
        }
        $path = app(LaporanHarianWordService::class)->generate($report);
        $local = Storage::disk('local')->path($path);
        $pdf = $local.'.pdf';
        try {
            app(DocxToPdfService::class)->convert($local, $pdf);
            $process = new Process([$binary, '-layout', $pdf, '-']);
            $process->mustRun();
            $output = rtrim($process->getOutput(), "\r\n");
            $pages = explode("\f", str_ends_with($output, "\f") ? substr($output, 0, -1) : $output);
            $this->assertCount($withDocument ? 4 : 2, $pages, json_encode($pages));
            $this->assertStringContainsString('F. Lampiran', $pages[1]);
            $this->assertStringContainsString('2. Dokumentasi', $pages[1]);
        } finally {
            Storage::disk('local')->delete(array_filter([$path, $path.'.pdf', $source]));
        }
    }

    #[Test]
    public function dokumen_pendukung_harian_disimpan_dan_diproses_async(): void
    {
        Queue::fake();
        Storage::fake('local');
        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->dataKapal])
            ->assertSee('Dokumen Pendukung')
            ->call('addExternalReport', 0)
            ->set('laporanExternal.0.0.judul', 'Dokumen inspeksi')
            ->set('laporanExternal.0.0.file', UploadedFile::fake()->image('bukti.jpg'))
            ->call('save')->assertHasNoErrors();
        $report = LaporanHarian::where('data_kapal_id', $this->dataKapal->id)->sole();
        $external = $report->laporanExternal()->sole();
        $this->assertSame('Dokumen inspeksi', $external->judul);
        $this->assertSame('pending', $external->file_status);
        $this->assertNull($external->laporan_mingguan_id);
        Queue::assertPushed(ProcessLaporanExternal::class, fn ($job) => $job->laporanExternalId === $external->id);
    }

    #[Test]
    public function edit_harian_menambah_dokumen_dan_mewajibkan_file(): void
    {
        Queue::fake();
        Storage::fake('local');
        $report = $this->laporan();
        $component = Livewire::actingAs($this->admin())->test(LaporanHarianEdit::class, [
            'dataKapal' => $this->dataKapal, 'laporanHarian' => $report,
        ])->call('addExternalReport', 0)->set('laporanExternal.0.0.judul', 'Inspeksi');
        $component->call('save')->assertHasErrors(['laporanExternal.0.0.file']);
        $component->set('laporanExternal.0.0.file', UploadedFile::fake()->image('inspeksi.jpg'))
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Inspeksi', $report->laporanExternal()->sole()->judul);
    }

    #[Test]
    public function dokumen_pending_menggagalkan_generate_harian(): void
    {
        $report = $this->laporan();
        $report->laporanExternal()->create(['judul' => 'Belum siap', 'file_status' => 'pending']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum selesai diproses');
        app(LaporanHarianWordService::class)->generate($report);
    }

    #[Test]
    public function hapus_dokumen_harian_tidak_boleh_mengakses_laporan_lain(): void
    {
        $report = $this->laporan();
        $other = LaporanHarian::create(['user_id' => $this->admin()->id, 'data_kapal_id' => $this->dataKapal->id, 'tanggal_laporan' => now()->subDays(3)]);
        $external = $other->laporanExternal()->create(['judul' => 'Rahasia', 'file_status' => 'pending']);
        $this->expectException(ModelNotFoundException::class);
        app(LaporanHarianService::class)->deleteExternal($report, $external->id);
    }

    #[Test]
    public function template_harian_memiliki_daftar_sebelum_isi_lampiran(): void
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/private/templates/laporan-harian/template-laporan-harian.docx')));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertStringContainsString('F. Lampiran', $xml);
        $this->assertStringNotContainsString('F. Lampiran: Dokumentasi', $xml);
        foreach (['1. Dokumen Pendukung', '${tabel_external}', '2. Dokumentasi', '${tabel_dokumentasi}', '${lampiran_external}', '${pemisah_dokumentasi}', '${blok_lampiran}'] as $marker) {
            $position = strpos($xml, $marker);
            $this->assertNotFalse($position, $marker);
            $positions[] = $position;
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_kategori_options_work_group_terurut_dan_lainnya_terakhir(): void
    {
        $options = app(LaporanHarianService::class)->kategoriOptions($this->dataKapal->id);

        $this->assertSame(['Konstruksi Lambung', 'Permesinan', LaporanHarianService::KATEGORI_LAINNYA], array_column($options, 'nama'));
        $this->assertNull(end($options)['work_group_id']);
    }

    public function test_create_menyimpan_aktivitas_per_kategori_dan_foto_ke_antrian(): void
    {
        Queue::fake();
        Storage::fake('local');

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->dataKapal])
            ->call('addAktivitas', 0, $this->wgLambung->id)
            ->call('addAktivitas', 0, null);

        $rowKey = $component->get('items.0.aktivitas.0.row_key');

        $component
            ->set('items.0.aktivitas.0.aktivitas', 'Fit up blok lambung')
            ->set('items.0.aktivitas.1.aktivitas', 'Rapat koordinasi')
            ->set("lampiran.0.{$rowKey}.file", UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $laporan = LaporanHarian::where('data_kapal_id', $this->dataKapal->id)->sole();
        $aktivitas = $laporan->aktivitas()->get();

        $this->assertCount(2, $aktivitas);
        $this->assertSame($this->wgLambung->id, $aktivitas[0]->kurva_s_work_group_id);
        $this->assertSame('Konstruksi Lambung', $aktivitas[0]->kategori);
        $this->assertNull($aktivitas[1]->kurva_s_work_group_id);

        $lampiran = LaporanLampiran::where('laporan_harian_id', $laporan->id)->sole();
        $this->assertSame($aktivitas[0]->id, $lampiran->laporan_aktivitas_id);
        $this->assertSame($laporan->id, $lampiran->laporan_harian_id);
        $this->assertSame('Fit up blok lambung', $lampiran->keterangan);
        Queue::assertPushed(ProcessLaporanLampiran::class, 1);
    }

    public function test_foto_tanpa_uraian_aktivitas_ditolak(): void
    {
        Storage::fake('local');

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->dataKapal])
            ->call('addAktivitas', 0, $this->wgMesin->id);

        $rowKey = $component->get('items.0.aktivitas.0.row_key');

        $component
            ->set("lampiran.0.{$rowKey}.file", UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasErrors(["lampiran.0.{$rowKey}.file"]);

        $this->assertFalse(LaporanHarian::where('data_kapal_id', $this->dataKapal->id)->exists());
    }

    public function test_work_group_milik_kapal_lain_dinormalisasi_ke_lainnya(): void
    {
        $laporan = $this->laporan();
        $wgLain = KurvaSWorkGroup::where('data_kapal_id', '!=', $this->dataKapal->id)->firstOrFail();

        app(LaporanHarianService::class)->syncAktivitas($laporan, [
            ['row_key' => 'n1', 'id' => null, 'work_group_id' => $wgLain->id, 'aktivitas' => 'Aktivitas X', 'keterangan' => ''],
        ]);

        $aktivitas = $laporan->aktivitas()->sole();
        $this->assertNull($aktivitas->kurva_s_work_group_id);
        $this->assertNull($aktivitas->kategori);
    }

    public function test_edit_update_aktivitas_tetap_id_dan_hapus_aktivitas_beserta_foto(): void
    {
        $laporan = $this->laporan();
        $tetap = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgLambung->id, 'kategori' => 'Konstruksi Lambung', 'aktivitas' => 'Awal']);
        $dihapus = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgMesin->id, 'kategori' => 'Permesinan', 'aktivitas' => 'Dengan foto']);
        $dihapus->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_name' => 'a.webp', 'file_status' => 'completed']);

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['dataKapal' => $this->dataKapal, 'laporanHarian' => $laporan])
            ->set('aktivitas.0.aktivitas', 'Diubah')
            ->call('confirmRemoveAktivitas', 1)
            ->assertSet('deletingAktivitasHasFoto', true)
            ->call('removeAktivitasConfirmed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Diubah', $tetap->fresh()->aktivitas);
        $this->assertNull(LaporanAktivitas::find($dihapus->id));
        $this->assertSame(0, LaporanLampiran::where('laporan_aktivitas_id', $dihapus->id)->count());
    }

    public function test_edit_uraian_aktivitas_berfoto_tidak_boleh_dikosongkan(): void
    {
        $laporan = $this->laporan();
        $aktivitas = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgLambung->id, 'kategori' => 'Konstruksi Lambung', 'aktivitas' => 'Ada foto']);
        $aktivitas->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_name' => 'a.webp', 'file_status' => 'completed']);

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['dataKapal' => $this->dataKapal, 'laporanHarian' => $laporan])
            ->set('aktivitas.0.aktivitas', '')
            ->call('save')
            ->assertHasErrors(['aktivitas.0.aktivitas']);
    }

    public function test_aktivitas_per_kategori_hanya_kategori_berisi_urut_sort_order(): void
    {
        $laporan = $this->laporan();
        $laporan->aktivitas()->create(['aktivitas' => 'Umum']);
        $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgMesin->id, 'kategori' => 'Permesinan', 'aktivitas' => 'Mesin']);

        $groups = app(LaporanHarianService::class)->aktivitasPerKategori($laporan->fresh());

        $this->assertSame(['Permesinan', LaporanHarianService::KATEGORI_LAINNYA], $groups->pluck('nama')->all());
    }

    public function test_generate_word_tidak_menyertakan_kategori_kosong_dan_grid_lampiran(): void
    {
        $laporan = $this->laporan();
        $aktivitas = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgMesin->id, 'kategori' => 'Permesinan', 'aktivitas' => 'Overhaul mesin induk']);

        $path = 'laporan-lampiran/harian/test_'.uniqid().'.png';
        $img = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($img);
        Storage::disk('local')->put($path, ob_get_clean());
        $aktivitas->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_path' => $path, 'file_name' => 'foto.png', 'file_status' => 'completed']);

        $laporan->laporanExternal()->create(['judul' => 'Dokumen Inspeksi', 'deskripsi' => 'Hasil pemeriksaan', 'file_path' => $path, 'file_name' => 'dokumen.png', 'file_status' => 'completed']);
        $docPath = app(LaporanHarianWordService::class)->generate($laporan->fresh());

        try {
            $zip = new \ZipArchive;
            $zip->open(storage_path('app/private/'.$docPath));
            $text = strip_tags($zip->getFromName('word/document.xml'));
            $this->assertStringContainsString('Dokumen Inspeksi', $text);
            $this->assertLessThan(strpos($text, '1. DOKUMEN PENDUKUNG'), strpos($text, '2. Dokumentasi'));
            $this->assertLessThan(strpos($text, '2. DOKUMENTASI'), strpos($text, '1. DOKUMEN PENDUKUNG'));
            $zip->close();

            $this->assertStringContainsString('Permesinan', $text);
            $this->assertStringNotContainsString('Konstruksi Lambung', $text);
            $this->assertStringContainsString('Overhaul mesin induk', $text);
            $this->assertStringNotContainsString('${', $text);
        } finally {
            @unlink(storage_path('app/private/'.$docPath));
            Storage::disk('local')->delete($path);
        }
    }

    public function test_generate_word_tidak_menyisakan_paragraf_page_break_berdiri_sendiri(): void
    {
        $laporan = $this->laporan();
        $path = 'laporan-lampiran/harian/test_'.uniqid().'.png';
        $img = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($img);
        Storage::disk('local')->put($path, ob_get_clean());
        foreach (range(1, 5) as $i) {
            $a = $laporan->aktivitas()->create(['kategori' => 'Permesinan', 'aktivitas' => 'Aktivitas '.$i]);
            $a->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_path' => $path, 'file_name' => 'foto.png', 'file_status' => 'completed']);
        }

        $docPath = app(LaporanHarianWordService::class)->generate($laporan->fresh());

        try {
            $zip = new \ZipArchive;
            $zip->open(storage_path('app/private/'.$docPath));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            $doc = new \DOMDocument;
            $doc->loadXML($xml);
            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            // Tidak ada paragraf kosong berisi page break sebelum paragraf lain (penyebab halaman kosong)
            $standalone = $xp->query('/w:document/w:body/w:p[.//w:br[@w:type="page"]][not(normalize-space())][following-sibling::*[1][self::w:p]]');
            $this->assertSame(0, $standalone->length, $standalone->length ? $doc->saveXML($standalone->item(0)->nextSibling) : '');

            // Break sebelum judul F dipindah menjadi pageBreakBefore
            $lampiran = $xp->query('/w:document/w:body/w:p[contains(.,"F. Lampiran")]/w:pPr/w:pageBreakBefore');
            $this->assertGreaterThan(0, $lampiran->length);

            // Pemisah antar-halaman grid foto tetap ada tetapi dikempeskan (line exact 20)
            $pemisah = $xp->query('/w:document/w:body/w:p[.//w:br[@w:type="page"]][following-sibling::w:tbl]/w:pPr/w:spacing[@w:line="20"][@w:lineRule="exact"]');
            $this->assertGreaterThan(0, $pemisah->length);

            // Signature normalisasi diset agar dokumen lama dikenali stale oleh mingguan/bulanan
            $this->assertTrue(app(WordAttachmentService::class)
                ->isBreakNormalized(storage_path('app/private/'.$docPath)));
        } finally {
            @unlink(storage_path('app/private/'.$docPath));
            Storage::disk('local')->delete($path);
        }
    }
}
