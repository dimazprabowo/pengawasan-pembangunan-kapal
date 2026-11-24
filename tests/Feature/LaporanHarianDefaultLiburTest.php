<?php

namespace Tests\Feature;

use App\Enums\LaporanHariStatus;
use App\Exports\LaporanHarianExport;
use App\Jobs\GenerateLaporanHarianJob;
use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanHarian\LaporanHarianEdit;
use App\Livewire\LaporanHarian\LaporanHarianShow;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\Owner;
use App\Models\User;
use App\Services\LaporanHarianService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LaporanHarianDefaultLiburTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DataKapal $proyek;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 5)->startOfDay());
        $this->seed(PermissionSeeder::class);
        Role::findOrCreate('super admin', 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole('super admin');
        $this->actingAs($this->user);
        $this->proyek = DataKapal::create([
            'nama' => 'Proyek Prefill', 'status' => 'active',
            'tanggal_mulai_pelaksanaan' => '2026-10-28', 'waktu_pelaksanaan_hari' => 10,
        ]);
    }

    private function report(string $tanggal, array $data = []): LaporanHarian
    {
        return LaporanHarian::create(array_replace([
            'user_id' => $this->user->id,
            'data_kapal_id' => $this->proyek->id,
            'tanggal_laporan' => $tanggal,
        ], $data));
    }

    #[Test]
    public function prefill_celah_29_oktober_mengambil_28_bukan_laporan_terakhir_tanpa_menyalin_id_atau_foto(): void
    {
        $source = $this->report('2026-10-28', ['suhu' => 28]);
        $source->personel()->create(['jabatan' => 'Surveyor', 'jumlah' => 2]);
        $source->peralatan()->create(['jenis' => 'Crane', 'jumlah' => 1]);
        $source->consumable()->create(['jenis' => 'Cat', 'jumlah' => 3]);
        $aktivitas = $source->aktivitas()->create(['aktivitas' => 'Inspeksi lambung', 'keterangan' => 'Blok A']);
        $aktivitas->lampiran()->create(['laporan_harian_id' => $source->id, 'file_status' => 'completed', 'file_name' => 'lama.webp']);
        $this->report('2026-10-30', ['suhu' => 33]);
        $this->report('2026-10-31', ['suhu' => 34]);

        $component = Livewire::test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek])
            ->assertSet('items.0.tanggal_laporan', '2026-10-29')
            ->assertSet('items.0.personel.0.jabatan', 'Surveyor')
            ->assertSet('items.0.peralatan.0.jenis', 'Crane')
            ->assertSet('items.0.consumable.0.jenis', 'Cat')
            ->assertSet('items.0.aktivitas.0.aktivitas', 'Inspeksi lambung')
            ->assertSet('items.0.aktivitas.0.id', null)
            ->assertSet('lampiran.0', [])
            ->assertSee('Default dari laporan 28 Oktober 2026');
        $this->assertEquals(28, $component->get('items.0.suhu'));
        $component->call('save')->assertHasNoErrors();
        $created = $this->proyek->laporanHarian()->whereDate('tanggal_laporan', '2026-10-29')->sole();
        $this->assertNotSame($aktivitas->id, $created->aktivitas()->sole()->id);
        $this->assertSame(0, $created->lampiran()->count());
        $this->assertSame(1, $source->lampiran()->count());
    }

    #[Test]
    public function pergantian_bulan_menggunakan_31_oktober_dan_tidak_melewati_tanggal_kosong(): void
    {
        $this->report('2026-10-31', ['suhu' => 31]);
        $service = app(LaporanHarianService::class);
        $result = $service->previousDayDefaults($this->proyek, '2026-11-01');
        $this->assertEquals(31, $result['values']['suhu']);
        $this->assertStringContainsString('31 Oktober 2026', $result['info']);
        $missing = $service->previousDayDefaults($this->proyek, '2026-11-02');
        $this->assertSame([], $missing['values']);
        $this->assertStringContainsString('belum diisi', $missing['info']);
        $this->assertStringContainsString('Hari Ke - 5', $missing['info']);
    }

    #[Test]
    public function hari_pertama_manual_dan_tab_berikutnya_mengambil_draft_hari_sebelumnya(): void
    {
        Livewire::test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek])
            ->assertSet('items.0.personel', [])
            ->assertSee('Isi data secara manual')
            ->set('items.0.personel', [['jabatan' => 'Pengawas', 'jumlah' => 1, 'keterangan' => '']])
            ->call('addItem')
            ->assertSet('items.1.personel.0.jabatan', 'Pengawas')
            ->assertSee('tab belum disimpan');
    }

    #[Test]
    public function rangkaian_libur_dilewati_tetapi_tanggal_yang_belum_dilaporkan_tidak_dilewati(): void
    {
        $this->report('2026-10-28', ['suhu' => 29]);
        $this->report('2026-10-29', ['is_libur' => true, 'alasan_libur' => 'Libur']);
        $this->report('2026-10-30', ['is_libur' => true, 'alasan_libur' => 'Libur']);
        $service = app(LaporanHarianService::class);
        $result = $service->previousDayDefaults($this->proyek, '2026-10-31');
        $this->assertEquals(29, $result['values']['suhu']);
        $this->assertStringContainsString('28 Oktober', $result['info']);
        $this->assertSame([], $service->previousDayDefaults($this->proyek, '2026-11-01')['values']);
    }

    #[Test]
    public function toggle_libur_merah_saat_aktif_dan_abu_abu_saat_nonaktif_pada_create_dan_edit(): void
    {
        $report = $this->report('2026-10-28');
        $forms = [
            [Livewire::test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek]), [0]],
            [Livewire::test(LaporanHarianEdit::class, ['dataKapal' => $this->proyek, 'laporanHarian' => $report]), []],
        ];

        foreach ($forms as [$form, $args]) {
            $this->assertMatchesRegularExpression('/<button\b(?=[^>]*role="switch")(?=[^>]*aria-checked="false")(?=[^>]*bg-gray-200)[^>]*>/s', $form->html());
            $form->call('toggleLibur', ...$args);
            $this->assertMatchesRegularExpression('/<button\b(?=[^>]*role="switch")(?=[^>]*aria-checked="true")(?=[^>]*bg-red-600)[^>]*>/s', $form->html());
            $form->call('toggleLibur', ...$args);
            $this->assertMatchesRegularExpression('/<button\b(?=[^>]*role="switch")(?=[^>]*aria-checked="false")(?=[^>]*bg-gray-200)[^>]*>/s', $form->html());
        }
    }

    #[Test]
    public function seluruh_tab_operasional_hilang_saat_libur_dan_kembali_tanpa_kehilangan_isian(): void
    {
        $report = $this->report('2026-10-28');
        $forms = [
            [Livewire::test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek]), [0], 'items.0.personel'],
            [Livewire::test(LaporanHarianEdit::class, ['dataKapal' => $this->proyek, 'laporanHarian' => $report]), [], 'personel'],
        ];
        $rows = [['jabatan' => 'Petugas jaga', 'jumlah' => 3, 'keterangan' => 'Tetap tersimpan']];

        foreach ($forms as [$form, $args, $path]) {
            $form->set($path, $rows);
            foreach (['cuaca', 'personel', 'peralatan', 'consumable', 'aktivitas', 'lampiran', 'nc'] as $section) {
                $form->assertSeeHtml('x-show="section === \''.$section.'\'"');
            }
            $form->call('toggleLibur', ...$args)->assertSet($path, $rows)->assertSee('Alasan Libur');
            foreach (['cuaca', 'personel', 'peralatan', 'consumable', 'aktivitas', 'lampiran', 'nc'] as $section) {
                $form->assertDontSeeHtml('x-show="section === \''.$section.'\'"');
                $form->assertDontSeeHtml('@click="section = \''.$section.'\';');
            }
            $form->call('toggleLibur', ...$args)->assertSet($path, $rows);
            foreach (['cuaca', 'personel', 'peralatan', 'consumable', 'aktivitas', 'lampiran', 'nc'] as $section) {
                $form->assertSeeHtml('x-show="section === \''.$section.'\'"');
            }
        }
    }

    #[Test]
    public function show_libur_menampilkan_panel_alasan_tanpa_rincian_operasional_dan_tidak_menghapus_data(): void
    {
        $report = $this->report('2026-10-28', ['is_libur' => true, 'alasan_libur' => 'Libur nasional & cuti bersama']);
        $report->personel()->create(['jabatan' => 'Petugas tetap tersimpan', 'jumlah' => 3]);
        Livewire::test(LaporanHarianShow::class, ['dataKapal' => $this->proyek, 'laporanHarian' => $report])
            ->assertSee('Keterangan Hari Libur')->assertSee('Libur nasional & cuti bersama')
            ->assertDontSee('A. Informasi Cuaca')->assertDontSee('B. Personel')->assertDontSee('C. Peralatan')
            ->assertDontSee('D. Consumable')->assertDontSee('E. Aktivitas')->assertDontSee('F. Lampiran')
            ->assertDontSee('Non-Conformity (NC)')->assertDontSee('Petugas tetap tersimpan');
        $this->assertSame(1, $report->personel()->count());
        $report->update(['is_libur' => false, 'alasan_libur' => null]);
        Livewire::test(LaporanHarianShow::class, ['dataKapal' => $this->proyek, 'laporanHarian' => $report->fresh()])
            ->assertDontSee('Keterangan Hari Libur')->assertSee('A. Informasi Cuaca')->assertSee('Petugas tetap tersimpan');
    }

    #[Test]
    public function job_generate_libur_mempertahankan_format_harian_dengan_keterangan_libur(): void
    {
        $template = 'templates/laporan-harian/template-laporan-harian.docx';
        Storage::disk('local')->put($template, file_get_contents(storage_path('app/private/'.$template)));
        $reason = 'Libur nasional & cuti bersama';
        $report = $this->report('2026-10-28', ['is_libur' => true, 'alasan_libur' => $reason]);
        $report->personel()->create(['jabatan' => 'Data operasional tersimpan', 'jumlah' => 3]);
        $report->aktivitas()->create(['aktivitas' => 'Aktivitas tidak ditampilkan']);
        GenerateLaporanHarianJob::dispatchSync($report);
        $report->refresh();
        $this->assertSame('completed', $report->doc_status);
        Storage::disk('local')->assertExists($report->doc_path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($report->doc_path)));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($xml));
        $text = $document->textContent;
        foreach (['Libur', $reason, 'Proyek Prefill', $report->tanggal_laporan->translatedFormat('d F Y'), 'Konsultan Pengawas'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertTrue($zip->open(Storage::disk('local')->path($template)));
        $source = new \DOMDocument;
        $this->assertTrue($source->loadXML($zip->getFromName('word/document.xml')));
        $zip->close();
        $xpath = new \DOMXPath($source);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $headings = [];
        foreach ($xpath->query('//w:p') as $paragraph) {
            if (preg_match('/^[A-F]\.\s/u', trim($paragraph->textContent))) {
                $headings[] = trim($paragraph->textContent);
            }
        }
        $this->assertNotEmpty($headings);
        foreach ($headings as $heading) {
            $this->assertStringContainsString($heading, $text);
        }
        foreach (['Data operasional tersimpan', 'Aktivitas tidak ditampilkan', '${'] as $unexpected) {
            $this->assertStringNotContainsString($unexpected, $text);
        }
        $this->assertSame(1, $report->personel()->count());
        $this->assertSame(1, $report->aktivitas()->count());
    }

    #[Test]
    public function laporan_libur_tetap_memakai_template_harian_custom_kapal(): void
    {
        $path = 'templates/kapal/custom-harian.docx';
        Storage::disk('local')->put($path, file_get_contents(storage_path('app/private/templates/laporan-harian/template-laporan-harian.docx')));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($path)));
        $source = $zip->getFromName('word/document.xml');
        $custom = str_replace('<w:body>', '<w:body><w:p><w:r><w:t>FORMAT HARIAN KHUSUS KAPAL</w:t></w:r></w:p>', $source);
        $this->assertNotSame($source, $custom);
        $zip->addFromString('word/document.xml', $custom);
        $zip->close();
        $this->proyek->update(['template_path_harian' => $path]);
        $report = $this->report('2026-10-28', ['is_libur' => true, 'alasan_libur' => 'Hari libur nasional']);
        GenerateLaporanHarianJob::dispatchSync($report);
        $report->refresh();
        $this->assertSame('completed', $report->doc_status);
        $this->assertTrue($zip->open(Storage::disk('local')->path($report->doc_path)));
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($zip->getFromName('word/document.xml')));
        $zip->close();
        $this->assertStringContainsString('FORMAT HARIAN KHUSUS KAPAL', $document->textContent);
        $this->assertStringContainsString('Libur: Hari libur nasional', $document->textContent);
    }

    #[Test]
    public function hari_libur_dapat_disimpan_tanpa_data_operasional_dan_tercatat_di_audit(): void
    {
        Livewire::test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek])
            ->call('toggleLibur', 0)
            ->set('items.0.alasan_libur', '  Libur nasional  ')
            ->call('save')->assertHasNoErrors();
        $report = $this->proyek->laporanHarian()->sole();
        $this->assertTrue($report->is_libur);
        $this->assertSame('Libur nasional', $report->alasan_libur);
        $this->assertSame(LaporanHariStatus::Libur, $report->status_hari);
        $this->assertTrue(Activity::where('log_name', 'laporan_harian')->where('subject_id', $report->id)->exists());
        $this->assertContains('Libur', (new LaporanHarianExport)->map($report));
        $this->assertContains('Libur nasional', (new LaporanHarianExport)->map($report));
    }

    #[Test]
    public function alasan_libur_wajib_dan_bisa_kembali_menjadi_hari_kerja_tanpa_menghapus_data(): void
    {
        Livewire::test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek])
            ->call('toggleLibur', 0)->call('save')->assertHasErrors('items.0.alasan_libur');
        $report = $this->report('2026-10-28', ['is_libur' => true, 'alasan_libur' => 'Libur']);
        $report->personel()->create(['jabatan' => 'Petugas jaga', 'jumlah' => 1]);
        Livewire::test(LaporanHarianEdit::class, ['dataKapal' => $this->proyek, 'laporanHarian' => $report])
            ->assertSet('is_libur', true)->call('toggleLibur')->call('save')->assertHasNoErrors();
        $this->assertFalse($report->fresh()->is_libur);
        $this->assertNull($report->fresh()->alasan_libur);
        $this->assertSame('Petugas jaga', $report->personel()->sole()->jabatan);
    }

    #[Test]
    public function create_dan_prefill_tidak_bisa_mengakses_proyek_owner_lain(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('laporan_create');
        $other = Owner::create(['name' => 'Owner lain', 'code' => 'OTHER']);
        $this->proyek->update(['owner_id' => $other->id]);
        Livewire::actingAs($user)->test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek])->assertForbidden();
        $this->actingAs($user);
        $this->expectException(HttpException::class);
        app(LaporanHarianService::class)->previousDayDefaults($this->proyek, '2026-10-29');
    }

    #[Test]
    public function tanpa_permission_create_ditolak(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->proyek])->assertForbidden();
    }

    #[Test]
    public function kegagalan_detail_membatalkan_pembuatan_induk_dalam_transaksi(): void
    {
        $service = app(LaporanHarianService::class);
        try {
            $service->saveFormBatch([
                ['user_id' => $this->user->id, 'data_kapal_id' => $this->proyek->id, 'tanggal_laporan' => '2026-10-28'],
                ['user_id' => -1, 'data_kapal_id' => $this->proyek->id, 'tanggal_laporan' => '2026-10-29'],
            ], [[], []], [], []);
            $this->fail('Penyimpanan detail seharusnya gagal.');
        } catch (QueryException) {
            $this->assertSame(0, $this->proyek->laporanHarian()->count());
        }
    }
}
