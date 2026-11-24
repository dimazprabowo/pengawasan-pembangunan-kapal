<?php

namespace Tests\Feature;

use App\Livewire\LaporanBulanan\LaporanBulananCreate;
use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use App\Models\LaporanExternal;
use App\Models\LaporanHarian;
use App\Models\User;
use App\Services\KontrakPeriodeService;
use App\Services\KurvaSService;
use App\Services\LaporanBulananService;
use App\Services\LaporanBulananWordService;
use App\Services\LaporanMingguanService;
use App\Services\WordStyleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
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

    private function dataKapal(): DataKapal
    {
        return DataKapal::whereNotNull('tanggal_mulai_pelaksanaan')->firstOrFail();
    }

    public function test_phpword_writer_tanpa_patch_tidak_memicu_deprecation(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ($severity === E_DEPRECATED) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });
        try {
            $word = new PhpWord;
            $word->addParagraphStyle('Php85Style', ['spaceAfter' => 0]);
            $section = $word->addSection();
            $text = $section->addText('Verifikasi paragraf biasa.');
            $section->addHeader()->addText('Header');
            $section->addFooter()->addText('Footer');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell()->addText('Isi tabel', [], ['alignment' => 'center']);
            $word->addNumberingStyle('Php85Numbering', ['type' => 'singleLevel', 'levels' => [['format' => 'decimal', 'text' => '%1.']]]);
            $section->addListItem('Poin bernomor', 0, [], 'Php85Numbering');
            WordStyleService::prepare($word);
            $this->assertInstanceOf(Paragraph::class, Style::getStyle('Php85Style'));
            $this->assertSame('', Style::getStyle('Php85Style')->getNumStyle());
            $this->assertSame('', $text->getParagraphStyle()->getNumStyle());
            $writer = IOFactory::createWriter($word, 'Word2007');
            $xml = $writer->getWriterPart('Document')->write();
            $this->assertStringContainsString('Isi tabel', $xml);
            $this->assertStringContainsString('<w:numPr>', $xml);
            $this->assertStringContainsString('Php85Style', $writer->getWriterPart('Styles')->write());
            $block = new Table;
            $block->addRow();
            $block->addCell()->addText('Tabel dinamis & aman', [], ['alignment' => 'center']);
            Settings::setOutputEscapingEnabled(true);
            $processor = new TemplateProcessor(base_path('storage/app/private/templates/laporan-bulanan/template-laporan-bulanan.docx'));
            WordStyleService::setComplexBlock($processor, 'tabel_exec_progress', $block);
            $path = tempnam(sys_get_temp_dir(), 'word-style-test-');
            try {
                $processor->saveAs($path);
                $zip = new \ZipArchive;
                $this->assertTrue($zip->open($path));
                try {
                    $document = new \DOMDocument;
                    $this->assertTrue($document->loadXML($zip->getFromName('word/document.xml')));
                    $this->assertStringContainsString('Tabel dinamis & aman', $document->textContent);
                } finally {
                    $zip->close();
                }
            } finally {
                unlink($path);
            }
        } finally {
            restore_error_handler();
        }
    }

    public function test_generate_bulanan_tanpa_patch_tidak_memicu_deprecation(): void
    {
        $storage = storage_path();
        $isolatedStorage = sys_get_temp_dir().'/monthly-word-'.bin2hex(random_bytes(8));
        mkdir($isolatedStorage.'/app/private/templates/laporan-bulanan', 0755, true);
        copy(base_path('storage/app/private/templates/laporan-bulanan/template-laporan-bulanan.docx'), $isolatedStorage.'/app/private/templates/laporan-bulanan/template-laporan-bulanan.docx');
        $this->partialMock(LaporanBulananService::class, function ($mock): void {
            $mock->shouldReceive('getMingguanBulan')->andReturn(collect());
        });
        app()->useStoragePath($isolatedStorage);
        config(['filesystems.disks.local.root' => $isolatedStorage.'/app/private']);
        Storage::forgetDisk('local');
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ($severity === E_DEPRECATED) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });
        try {
            $laporan = LaporanBulanan::firstOrFail();
            $laporan->laporanExternal()->delete();
            $path = app(LaporanBulananWordService::class)->generate($laporan);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open(storage_path('app/private/'.$path)));
            try {
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($zip->getFromName('word/document.xml')));
                $this->assertStringContainsString($laporan->dataKapal->nama, $document->textContent);
                $this->assertStringNotContainsString('${tabel_', $document->textContent);
            } finally {
                $zip->close();
            }
        } finally {
            restore_error_handler();
            app()->useStoragePath($storage);
            File::deleteDirectory($isolatedStorage);
        }
    }

    #[Test]
    public function bulanan_memakai_ulang_mingguan_dan_generate_bila_dokumen_dikosongkan(): void
    {
        $storage = storage_path();
        $isolatedStorage = sys_get_temp_dir().'/monthly-attachments-'.bin2hex(random_bytes(8));
        foreach (['bulanan', 'mingguan', 'harian'] as $type) {
            mkdir($isolatedStorage.'/app/private/templates/laporan-'.$type, 0755, true);
            copy(base_path('storage/app/private/templates/laporan-'.$type.'/template-laporan-'.$type.'.docx'), $isolatedStorage.'/app/private/templates/laporan-'.$type.'/template-laporan-'.$type.'.docx');
        }
        app()->useStoragePath($isolatedStorage);
        config(['filesystems.disks.local.root' => $isolatedStorage.'/app/private']);
        Storage::forgetDisk('local');
        try {
            $laporan = LaporanBulanan::firstOrFail();
            LaporanExternal::query()->delete();
            $mingguan = app(LaporanBulananService::class)->getMingguanBulan($laporan)->firstOrFail();
            $harian = LaporanHarian::where('data_kapal_id', $laporan->data_kapal_id)->orderBy('tanggal_laporan')->limit(2)->get();
            $this->assertCount(2, $harian);
            $harian->each(fn ($item) => $item->update(['doc_path' => null, 'doc_status' => null]));
            mkdir($isolatedStorage.'/app/private/laporan/word', 0755, true);
            $oldPath = 'laporan/word/old-weekly.docx';
            $word = new PhpWord;
            $word->addSection()->addText('Mingguan lama tanpa lampiran harian');
            WordStyleService::prepare($word);
            $word->save(storage_path('app/private/'.$oldPath), 'Word2007');
            $mingguan->update(['doc_path' => $oldPath, 'doc_status' => 'completed']);

            // Perubahan isi mingguan lewat service mengosongkan docx-nya (invalidasi eksplisit).
            app(LaporanMingguanService::class)->update($mingguan, ['laporan_harian_ids' => $harian->modelKeys()]);
            $mingguan->refresh()->loadCount('laporanHarian');
            $this->assertNull($mingguan->doc_path);
            $this->assertFileDoesNotExist(storage_path('app/private/'.$oldPath));
            $this->partialMock(LaporanBulananService::class, function ($mock) use ($mingguan): void {
                $mock->shouldReceive('getMingguanBulan')->andReturn(new Collection([$mingguan]));
            });
            $relativePath = app(LaporanBulananWordService::class)->generate($laporan);
            $mingguan->refresh();
            $this->assertSame('completed', $mingguan->doc_status);
            $this->assertNotSame($oldPath, $mingguan->doc_path);
            $this->assertTrue(Storage::disk('local')->exists('laporan/lampiran-pages/'.sha1($mingguan->doc_path).'/pages.json'));
            $weeklyHash = hash_file('sha256', storage_path('app/private/'.$mingguan->doc_path));
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open(storage_path('app/private/'.$relativePath)));
            try {
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($zip->getFromName('word/document.xml')));
                $xpath = new \DOMXPath($document);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                // Lampiran disisipkan sebagai gambar halaman (bukan altChunk):
                // tidak ada chunk bertingkat dan konten ter-render untuk semua viewer.
                $this->assertSame(0, $xpath->query('//w:altChunk')->length);
                $this->assertGreaterThan(0, $xpath->query('//w:drawing')->length);
                $media = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (str_starts_with((string) $zip->getNameIndex($i), 'word/media/')) {
                        $media++;
                    }
                }
                $this->assertGreaterThan(0, $media);
                foreach ($harian->fresh() as $item) {
                    $this->assertSame('completed', $item->doc_status);
                }
            } finally {
                $zip->close();
            }
            $this->assertSame($weeklyHash, hash_file('sha256', storage_path('app/private/'.$mingguan->doc_path)));

            // Generate ulang bulanan memakai docx mingguan yang sudah ada (tanpa generate ulang).
            $reusedPath = $mingguan->doc_path;
            app(LaporanBulananWordService::class)->generate($laporan);
            $this->assertSame($reusedPath, $mingguan->fresh()->doc_path);
            $this->assertSame($weeklyHash, hash_file('sha256', storage_path('app/private/'.$reusedPath)));
            $weekly = new \ZipArchive;
            $this->assertTrue($weekly->open(storage_path('app/private/'.$mingguan->doc_path)));
            try {
                $weeklyDocument = (string) $weekly->getFromName('word/document.xml');
                $this->assertStringContainsString('LAPORAN HARIAN', $weeklyDocument);
                $this->assertStringNotContainsString('altChunk', $weeklyDocument);
            } finally {
                $weekly->close();
            }
        } finally {
            app()->useStoragePath($storage);
            File::deleteDirectory($isolatedStorage);
        }
    }

    public function test_template_toc_dan_numbering_bulanan(): void
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(base_path('storage/app/private/templates/laporan-bulanan/template-laporan-bulanan.docx')));
        try {
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($zip->getFromName('word/document.xml')));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $instructions = [];
            foreach ($xpath->query('//w:instrText') as $instruction) {
                $instructions[] = $instruction->textContent;
            }
            $this->assertContains(' TOC \\t "JudulBab0;1;JudulBab0C;1;JudulBab1;1;JudulBab2;2" \\h \\z ', $instructions);
            $this->assertSame(24, $xpath->query('//w:fldChar[@w:fldCharType="begin"][@w:dirty="true"]')->length);
            $this->assertSame(23, $xpath->query('//w:bookmarkStart[starts-with(@w:name, "_MonthlyToc")]')->length);
            $this->assertSame(23, $xpath->query('//w:hyperlink[starts-with(@w:anchor, "_MonthlyToc")]')->length);
            $pageReferences = array_filter($instructions, fn ($instruction) => str_contains($instruction, 'PAGEREF'));
            $this->assertCount(23, $pageReferences);
            foreach ($xpath->query('//w:hyperlink[starts-with(@w:anchor, "_MonthlyToc")]') as $link) {
                $anchor = $link->getAttribute('w:anchor');
                $this->assertSame(1, $xpath->query('//w:bookmarkStart[@w:name="'.$anchor.'"]')->length);
                $this->assertStringContainsString('PAGEREF '.$anchor.' \\h', $link->textContent);
                $this->assertSame('—', $xpath->query('w:r[w:fldChar[@w:fldCharType="end"]]/preceding-sibling::w:r[1]/w:t', $link)->item(0)?->textContent);
            }
            $this->assertSame(4, $xpath->query('//w:p[w:pPr/w:pStyle[@w:val="JudulBab1"]]/w:r/w:br')->length);
            $this->assertStringContainsString('<w:view w:val="print"', $zip->getFromName('word/settings.xml'));
            $this->assertStringContainsString('<w:sz w:val="40"', $zip->getFromName('word/styles.xml'));
            foreach (['styles', 'numbering', 'settings'] as $part) {
                $this->assertTrue($document->loadXML($zip->getFromName("word/{$part}.xml")));
            }
            $styles = $zip->getFromName('word/styles.xml');
            $this->assertStringContainsString('<w:outlineLvl w:val="0"', $styles);
            $this->assertStringContainsString('<w:outlineLvl w:val="1"', $styles);
            $this->assertStringContainsString('BAB %1.', $zip->getFromName('word/numbering.xml'));
            $this->assertStringContainsString('<w:updateFields w:val="true"', $zip->getFromName('word/settings.xml'));
            $numbering = new \DOMDocument;
            $numbering->loadXML($zip->getFromName('word/numbering.xml'));
            $numberingXPath = new \DOMXPath($numbering);
            $numberingXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $this->assertSame(2, $numberingXPath->query('//w:lvl[w:pStyle]/w:rPr/w:color[@w:val="1F4E79"]')->length);
            $this->assertSame(1, $numberingXPath->query('//w:lvl[w:pStyle[@w:val="JudulBab2"]]/w:pPr/w:ind[@w:left="720"][@w:hanging="720"]')->length);
            $this->assertSame(2, $numberingXPath->query('//w:lvl[not(w:pStyle)]/w:pPr/w:ind[@w:left="720"][@w:hanging="360"]')->length);
            $this->assertSame(2, $numberingXPath->query('//w:lvl[not(w:pStyle)]/w:pPr/w:tabs/w:tab[@w:pos="720"]')->length);
            $styleDocument = new \DOMDocument;
            $styleDocument->loadXML($styles);
            $styleXPath = new \DOMXPath($styleDocument);
            $styleXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $this->assertSame(2, $styleXPath->query('//w:style[@w:styleId="JudulBab1" or @w:styleId="JudulBab2"]/w:rPr/w:color[@w:val="1F4E79"]')->length);
            $this->assertSame(1, $styleXPath->query('//w:style[@w:styleId="TOC2"]/w:pPr/w:ind[@w:left="360"]')->length);
        } finally {
            $zip->close();
        }
    }

    public function test_pengantar_tabel_memiliki_spacing_konsisten(): void
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(base_path('storage/app/private/templates/laporan-bulanan/template-laporan-bulanan.docx')));
        try {
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($zip->getFromName('word/document.xml')));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ([
                'Secara umum spesifikasi dan ukuran utama',
                'Nomor Kontrak Gambar:',
                'Presentase Bobot penyelesaian',
                'Total progress yang telah dicapai',
            ] as $text) {
                $paragraphs = $xpath->query('//w:body/w:p[starts-with(string(.), "'.$text.'")]');
                $this->assertSame(1, $paragraphs->length, $text);
                $paragraph = $paragraphs->item(0);
                $this->assertSame(1, $xpath->query('w:pPr/w:spacing[@w:line="276"][@w:lineRule="auto"][@w:before="120"][@w:after="120"]', $paragraph)->length, $text);
                $this->assertSame(1, $xpath->query('w:pPr/w:keepNext', $paragraph)->length, $text);
            }
        } finally {
            $zip->close();
        }
    }

    public function test_bulanan_index_renders_in_data_kapal_context(): void
    {
        $dataKapal = $this->dataKapal();

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.index', $dataKapal))
            ->assertOk()
            ->assertSee($dataKapal->nama)
            ->assertSee('Laporan Bulanan');
    }

    public function test_bulanan_create_show_edit_render(): void
    {
        $laporan = LaporanBulanan::firstOrFail();
        $dataKapal = $laporan->dataKapal;

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.create', $dataKapal))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.show', [$dataKapal, $laporan]))
            ->assertOk()
            ->assertSee($laporan->judul)
            ->assertSee('mt-6 mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden', false);

        $this->actingAs($this->admin())
            ->get(route('laporan-bulanan.edit', [$dataKapal, $laporan]))
            ->assertOk();
    }

    public function test_monthly_chart_menjumlahkan_realisasi_mingguan(): void
    {
        $dataKapal = $this->dataKapal();
        $service = app(KurvaSService::class);

        $monthly = $service->getMonthlyChartData($dataKapal);
        $this->assertNotEmpty($monthly['labels']);
        $this->assertSame('Bulan', $monthly['unit_label']);
        $this->assertSame($monthly['total_unit'], count($monthly['labels']));

        // Kumulatif aktual terakhir chart = kumulatif terakhir history
        $history = $service->getMonthlyProgressHistory($dataKapal);
        $this->assertNotEmpty($history);

        $lastHistoryCum = end($history)['cumulative_actual'];
        $aktualValues = array_values(array_filter($monthly['aktual'], fn ($v) => $v !== null));
        $this->assertNotEmpty($aktualValues);
        $this->assertEqualsWithDelta($lastHistoryCum, end($aktualValues), 0.01);
    }

    public function test_monthly_rencana_sesuai_kurva_s_rencana(): void
    {
        $dataKapal = $this->dataKapal();
        $service = app(KurvaSService::class);

        $workGroupIds = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->pluck('id');

        foreach ($service->getMonthlyProgressHistory($dataKapal) as $entry) {
            // Rencana bulan = Σ pct_rencana bulan_ke tsb (langsung, tidak dibagi)
            $expected = (float) KurvaSRencana::where('bulan_ke', $entry['bulan_ke'])
                ->whereIn('work_group_id', $workGroupIds)
                ->sum('pct_rencana');

            $this->assertEqualsWithDelta($expected, $entry['week_plan'], 0.01, "Bulan {$entry['bulan_ke']}");
        }
    }

    public function test_get_bulan_options_mengecualikan_bulan_terpakai(): void
    {
        $dataKapal = $this->dataKapal();
        $used = LaporanBulanan::where('data_kapal_id', $dataKapal->id)->pluck('bulan_ke')->toArray();
        $this->assertNotEmpty($used);

        $options = app(KontrakPeriodeService::class)->getBulanOptions($dataKapal);
        $optionValues = array_column($options, 'value');

        foreach ($used as $bulanKe) {
            $this->assertNotContains($bulanKe, $optionValues);
        }
    }

    public function test_create_bulanan_persists_with_derived_periode(): void
    {
        // Kedua kapal seeded sudah punya laporan bulanan — buat kapal
        // baru berkontrak yang belum punya laporan bulanan.
        $dataKapal = DataKapal::create([
            'nama' => 'Kapal Uji Bulanan',
            'status' => 'active',
            'tanggal_mulai_pelaksanaan' => now()->subDays(60)->toDateString(),
            'waktu_pelaksanaan_hari' => 100,
        ]);

        $options = app(KontrakPeriodeService::class)->getBulanOptions($dataKapal);
        $this->assertNotEmpty($options);
        $bulanKe = $options[0]['value'];

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananCreate::class, ['dataKapal' => $dataKapal])
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('bulan_ke', $bulanKe)
            ->set('ringkasan', 'Uji laporan bulanan')
            ->call('save')
            ->assertRedirect(); // URL berisi encrypted route key (non-deterministik)

        $laporan = LaporanBulanan::where('data_kapal_id', $dataKapal->id)
            ->where('bulan_ke', $bulanKe)
            ->firstOrFail();

        // Periode snapshot mengikuti kontrak
        $bulan = app(KontrakPeriodeService::class)->getBulanPeriode($dataKapal, $bulanKe);
        $this->assertEquals($bulan['mulai']->format('Y-m-d'), $laporan->periode_mulai->format('Y-m-d'));
        $this->assertEquals($bulan['selesai']->format('Y-m-d'), $laporan->periode_selesai->format('Y-m-d'));
    }

    public function test_executive_summary_wajib_diisi(): void
    {
        $dataKapal = DataKapal::create([
            'nama' => 'Kapal Uji Bulanan',
            'status' => 'active',
            'tanggal_mulai_pelaksanaan' => now()->subDays(60)->toDateString(),
            'waktu_pelaksanaan_hari' => 100,
        ]);

        $bulanKe = app(KontrakPeriodeService::class)->getBulanOptions($dataKapal)[0]['value'];

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananCreate::class, ['dataKapal' => $dataKapal])
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('bulan_ke', $bulanKe)
            ->call('save')
            ->assertHasErrors(['ringkasan' => 'required']);

        $this->assertSame(0, LaporanBulanan::where('data_kapal_id', $dataKapal->id)->count());
    }

    public function test_bulan_ke_ganda_ditolak(): void
    {
        $dataKapal = $this->dataKapal();
        $existing = LaporanBulanan::where('data_kapal_id', $dataKapal->id)->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananCreate::class, ['dataKapal' => $dataKapal])
            ->set('tanggal_laporan', now()->format('Y-m-d'))
            ->set('bulan_ke', $existing->bulan_ke)
            ->call('save');

        $this->assertSame(
            1,
            LaporanBulanan::where('data_kapal_id', $dataKapal->id)
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
        $dataKapal = $this->dataKapal();

        $this->actingAs($user)
            ->get(route('laporan-bulanan.index', $dataKapal))
            ->assertForbidden();
    }
}
