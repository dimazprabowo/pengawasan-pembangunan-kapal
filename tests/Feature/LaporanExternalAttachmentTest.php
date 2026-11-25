<?php

namespace Tests\Feature;

use App\Models\LaporanBulanan;
use App\Models\LaporanExternal;
use App\Models\LaporanMingguan;
use App\Models\LaporanNc;
use App\Models\LaporanNcFoto;
use App\Services\DocxToPdfService;
use App\Services\LaporanBulananWordService;
use App\Services\LaporanDocumentService;
use App\Services\LaporanMingguanWordService;
use App\Services\PdfPageService;
use App\Services\WordAttachmentService;
use App\Services\WordStyleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class LaporanExternalAttachmentTest extends TestCase
{
    private function makeDocxWithExternalMarker(string $path): void
    {
        $document = new PhpWord;
        $section = $document->addSection();
        $section->addHeader()->addText('HEADER UTAMA');
        $section->addFooter()->addText('FOOTER UTAMA');
        $section->addText('TABEL DOKUMEN EKSTERNAL');
        $section->addText('${lampiran_external}');
        $section->addText('SECTION SETELAH MARKER');
        WordStyleService::prepare($document);
        IOFactory::createWriter($document, 'Word2007')->save($path);
    }

    #[Test]
    public function pdf_eksternal_dirender_per_halaman_dan_disisipkan_di_bawah_tabel(): void
    {
        if (! is_executable(config('services.poppler.pdftoppm')) || ! is_executable(config('services.poppler.pdfinfo'))) {
            $this->markTestSkipped('Poppler belum tersedia pada path konfigurasi.');
        }
        Storage::fake('local');
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 10, 40, 'DOKUMEN EKSTERNAL', imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $data = base64_encode(ob_get_clean());
        $html = '<img src="data:image/png;base64,'.$data.'"><div style="page-break-before:always"></div><img src="data:image/png;base64,'.$data.'">';
        Storage::disk('local')->put('ext.pdf', Pdf::loadHTML($html)->output());

        $pages = app(PdfPageService::class)->render('ext.pdf', 'laporan-external/pages');
        $this->assertCount(2, $pages);
        foreach ($pages as $page) {
            $this->assertStringStartsWith('laporan-external/pages/', $page);
            Storage::disk('local')->assertExists($page);
        }

        $external = new LaporanExternal([
            'judul' => 'Sertifikat Uji',
            'file_name' => 'ext.pdf',
            'file_path' => 'ext.pdf',
            'file_status' => 'completed',
            'rendered_pages' => $pages,
        ]);

        $path = Storage::disk('local')->path('output.docx');
        $this->makeDocxWithExternalMarker($path);
        app(WordAttachmentService::class)->embedExternals($path, collect([$external]));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $xml = $zip->getFromName('word/document.xml');
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));
            $xpath = new \DOMXPath($dom);
            $this->assertSame(2, $xpath->query('//*[local-name()="drawing"]')->length);
            $this->assertStringNotContainsString('Sertifikat Uji', $xml);
            $this->assertStringNotContainsString('Halaman 1', $xml);
            $this->assertStringNotContainsString('ext.pdf', $dom->textContent);
            $this->assertStringNotContainsString('${lampiran_external}', $xml);
            // Konten embed harus berada di bawah tabel, sebelum section berikutnya
            $this->assertLessThan(
                strpos($xml, 'SECTION SETELAH MARKER'),
                strpos($xml, '<w:drawing>')
            );
            $this->assertSame(2, count(array_filter(range(0, $zip->numFiles - 1), fn ($i) => str_starts_with($zip->getNameIndex($i), 'word/media/att_'))));
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function gambar_eksternal_disisipkan_sebagai_halaman_penuh(): void
    {
        Storage::fake('local');
        $image = imagecreatetruecolor(400, 300);
        ob_start();
        imagepng($image);
        Storage::disk('local')->put('dokumen.png', ob_get_clean());

        $external = new LaporanExternal([
            'judul' => 'Foto Dokumen',
            'file_name' => 'dokumen.webp',
            'file_path' => 'dokumen.png',
            'file_status' => 'completed',
        ]);

        $path = Storage::disk('local')->path('output.docx');
        $this->makeDocxWithExternalMarker($path);
        app(WordAttachmentService::class)->embedExternals($path, collect([$external]));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $xml = $zip->getFromName('word/document.xml');
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//*[local-name()="drawing"]')->length);
            $this->assertStringNotContainsString('Foto Dokumen', $xml);
            $this->assertStringNotContainsString('${lampiran_external}', $xml);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sections = $xpath->query('//w:sectPr');
            $this->assertSame(4, $sections->length);
            $this->assertSame('0', $xpath->evaluate('string(w:pgMar/@w:left)', $sections->item(2)));
            foreach (['top', 'right', 'bottom', 'header', 'footer', 'gutter'] as $margin) {
                $this->assertSame('0', $xpath->evaluate('string(w:pgMar/@w:'.$margin.')', $sections->item(2)));
            }
            foreach ([1, 3] as $normalIndex) {
                $this->assertSame(
                    $xpath->evaluate('string(w:pgMar/@w:left)', $sections->item(0)),
                    $xpath->evaluate('string(w:pgMar/@w:left)', $sections->item($normalIndex))
                );
            }
            $this->assertSame(0, $xpath->query('//w:br[@w:type="page"] | //w:pageBreakBefore')->length);
            foreach (['header', 'footer'] as $kind) {
                $original = $xpath->evaluate('string(w:'.$kind.'Reference[@w:type="default"]/@r:id)', $sections->item(0));
                $this->assertSame($original, $xpath->evaluate('string(w:'.$kind.'Reference[@w:type="default"]/@r:id)', $sections->item(3)));
                $relations = new \DOMDocument;
                $relations->loadXML($zip->getFromName('word/_rels/document.xml.rels'), LIBXML_NONET);
                $relationXpath = new \DOMXPath($relations);
                foreach ([1, 2] as $attachmentIndex) {
                    $this->assertNotSame($original, $xpath->evaluate('string(w:'.$kind.'Reference[@w:type="default"]/@r:id)', $sections->item($attachmentIndex)));
                    $this->assertSame(3, $xpath->query('w:'.$kind.'Reference', $sections->item($attachmentIndex))->length);
                    foreach ($xpath->query('w:'.$kind.'Reference', $sections->item($attachmentIndex)) as $reference) {
                        $id = $reference->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                        $target = $relationXpath->evaluate('string(//*[local-name()="Relationship"][@Id="'.$id.'"]/@Target)');
                        $part = new \DOMDocument;
                        $this->assertTrue($part->loadXML($zip->getFromName('word/'.$target), LIBXML_NONET));
                        $this->assertSame('', trim($part->textContent));
                    }
                }
            }
            $this->assertSame(1, $xpath->query('//w:p[w:r/w:drawing]/w:pPr/w:sectPr')->length);
            $pageWidth = (int) $xpath->evaluate('string(w:pgSz/@w:w)', $sections->item(2));
            $imageWidth = (int) $xpath->evaluate('string(//*[local-name()="extent"]/@cx)');
            $this->assertSame($pageWidth * 635, $imageWidth);
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function daftar_dokumen_pendukung_hanya_memuat_judul_dan_deskripsi(): void
    {
        Storage::fake('local');
        foreach ([
            [LaporanMingguanWordService::class, LaporanMingguan::class],
            [LaporanBulananWordService::class, LaporanBulanan::class],
        ] as [$service, $model]) {
            $path = Storage::disk('local')->path('daftar.docx');
            $document = new PhpWord;
            $document->addSection()->addText('${tabel_external}');
            WordStyleService::prepare($document);
            IOFactory::createWriter($document, 'Word2007')->save($path);
            $processor = new TemplateProcessor($path);
            $laporan = new $model;
            $laporan->setRelation('laporanExternal', collect([
                new LaporanExternal(['judul' => 'Berita Acara Uji', 'deskripsi' => 'Hasil pemeriksaan tangki', 'file_name' => 'rahasia-nama-file.pdf', 'file_path' => 'dokumen.pdf']),
                new LaporanExternal(['judul' => 'Dokumen Tanpa Deskripsi', 'file_name' => 'lain.pdf', 'file_path' => 'lain.pdf']),
            ]));
            (new \ReflectionMethod($service, 'fillExternal'))->invoke(app($service), $processor, $laporan);
            $processor->saveAs($path);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            try {
                $dom = new \DOMDocument;
                $this->assertTrue($dom->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET));
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                foreach ($xpath->query('//w:tbl/w:tr') as $row) {
                    $this->assertSame(2, $xpath->query('w:tc', $row)->length);
                }
                $this->assertSame('Judul', $xpath->evaluate('string((//w:tbl/w:tr)[1]/w:tc[1]//w:t)'));
                $this->assertSame('Deskripsi', $xpath->evaluate('string((//w:tbl/w:tr)[1]/w:tc[2]//w:t)'));
                $this->assertStringContainsString('Hasil pemeriksaan tangki', $dom->textContent);
                $this->assertSame('-', $xpath->evaluate('string((//w:tbl/w:tr)[3]/w:tc[2]//w:t)'));
                $this->assertStringNotContainsString('rahasia-nama-file.pdf', $dom->textContent);
                $this->assertStringNotContainsString('lain.pdf', $dom->textContent);
            } finally {
                $zip->close();
            }
        }
    }

    #[Test]
    public function sampul_tanpa_header_footer_tetapi_isi_tetap_memilikinya(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('sampul.docx');
        $document = new PhpWord;
        $section = $document->addSection();
        $section->addHeader()->addText('HEADER ISI');
        $section->addFooter()->addText('FOOTER ISI');
        $section->addHeader('first')->addText('HEADER SAMPUL LAMA');
        $section->addFooter('first')->addText('FOOTER SAMPUL LAMA');
        $section->addText('SAMPUL');
        $section->addPageBreak();
        $section->addText('ISI');
        WordStyleService::prepare($document);
        IOFactory::createWriter($document, 'Word2007')->save($path);

        WordStyleService::hideCoverHeaderFooter($path);
        WordStyleService::hideCoverHeaderFooter($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET));
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $this->assertSame(1, $xpath->query('//w:sectPr/w:titlePg')->length);
            $this->assertSame(0, $xpath->query('//w:sectPr/*[@w:type="first"]')->length);
            $this->assertSame(1, $xpath->query('//w:sectPr/w:headerReference[@w:type="default"]')->length);
            $this->assertSame(1, $xpath->query('//w:sectPr/w:footerReference[@w:type="default"]')->length);
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function lampiran_laporan_di_akhir_dokumen_memakai_section_nol_margin_tanpa_halaman_pemulihan(): void
    {
        Storage::fake('local');
        $image = imagecreatetruecolor(600, 850);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 100, 300, 'ISI LAPORAN', imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        Storage::disk('local')->put('halaman.png', ob_get_clean());
        $path = Storage::disk('local')->path('laporan.docx');
        $document = new PhpWord;
        $section = $document->addSection();
        $section->addText('ISI UTAMA');
        $section->addPageBreak();
        $section->addText('${lampiran_harian}');
        WordStyleService::prepare($document);
        IOFactory::createWriter($document, 'Word2007')->save($path);

        app(WordAttachmentService::class)->embedReportPages($path, [
            ['halaman.png', '', true], ['halaman.png', '', true],
        ], '${lampiran_harian}', 'LAPORAN HARIAN');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET));
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $this->assertSame(3, $xpath->query('//w:sectPr')->length);
            $this->assertSame('0', $xpath->evaluate('string(/w:document/w:body/w:sectPr/w:pgMar/@w:left)'));
            $this->assertSame(2, $xpath->query('//w:drawing')->length);
            $this->assertSame(1, $xpath->query('//w:pageBreakBefore')->length);
            $this->assertStringNotContainsString('${lampiran_harian}', $dom->textContent);
            $media = $zip->getFromName('word/media/'.$xpath->evaluate('string(//*[local-name()="cNvPr"]/@name)'));
            $size = getimagesizefromstring($media);
            $this->assertEqualsWithDelta(600 / 850, $size[0] / $size[1], 0.002);
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function hasil_render_pemisah_tanpa_footer_dan_tanpa_halaman_kosong(): void
    {
        $textBinary = dirname(config('services.poppler.pdfinfo')).'/pdftotext'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        if (! is_executable($textBinary) || (! class_exists(\COM::class) && ! is_executable((string) config('services.libreoffice.binary')))) {
            $this->markTestSkipped('Engine Word/LibreOffice dan pdftotext diperlukan untuk verifikasi pagination.');
        }
        Storage::fake('local');
        $image = imagecreatetruecolor(600, 850);
        ob_start();
        imagepng($image);
        Storage::disk('local')->put('halaman.png', ob_get_clean());
        $path = Storage::disk('local')->path('render.docx');
        $document = new PhpWord;
        $section = $document->addSection();
        $section->addHeader()->addText('HEADER UTAMA');
        $section->addFooter()->addText('FOOTER UTAMA');
        $section->addText('ISI UTAMA');
        $section->addPageBreak();
        $section->addText('${lampiran_harian}');
        $section->addText('ISI SETELAH LAMPIRAN');
        WordStyleService::prepare($document);
        IOFactory::createWriter($document, 'Word2007')->save($path);
        app(WordAttachmentService::class)->embedReportPages($path, [
            ['halaman.png', '', true],
        ], '${lampiran_harian}', 'LAPORAN HARIAN');
        $pdf = Storage::disk('local')->path('render.pdf');
        app(DocxToPdfService::class)->convert($path, $pdf);
        $process = new Process([$textBinary, '-layout', $pdf, '-']);
        $process->mustRun();
        $pages = explode("\f", rtrim($process->getOutput(), "\f\r\n"));
        $this->assertCount(4, $pages, 'Hanya boleh ada halaman utama, pemisah, lampiran, dan konten lanjutan.');
        $this->assertStringContainsString('FOOTER UTAMA', $pages[0]);
        $this->assertStringContainsString('LAPORAN HARIAN', $pages[1]);
        foreach ([1, 2] as $index) {
            $this->assertStringNotContainsString('FOOTER UTAMA', $pages[$index]);
            $this->assertStringNotContainsString('HEADER UTAMA', $pages[$index]);
        }
        $this->assertStringContainsString('ISI SETELAH LAMPIRAN', $pages[3]);
        $this->assertStringContainsString('FOOTER UTAMA', $pages[3]);
    }

    #[Test]
    public function footer_utama_tetap_ada_setelah_nc_harian_dan_eksternal_disisipkan_berurutan(): void
    {
        Storage::fake('local');
        $image = imagecreatetruecolor(600, 850);
        ob_start();
        imagepng($image);
        Storage::disk('local')->put('halaman.png', ob_get_clean());
        $path = Storage::disk('local')->path('gabungan.docx');
        $document = new PhpWord;
        $section = $document->addSection();
        $section->addFooter()->addText('FOOTER UTAMA');
        $section->addText('ISI UTAMA');
        $section->addPageBreak();
        $section->addText('E. LAMPIRAN');
        $section->addText('Daftar dokumen eksternal, laporan harian, dan NC');
        $section->addText('${lampiran_external}');
        $section->addText('${lampiran_harian}');
        WordStyleService::prepare($document);
        IOFactory::createWriter($document, 'Word2007')->save($path);
        $file = new LaporanNcFoto([
            'file_name' => 'halaman.png', 'file_path' => 'halaman.png', 'file_status' => 'completed',
        ]);
        $nc = new LaporanNc;
        $nc->setRelation('foto', collect([$file]));
        $service = app(WordAttachmentService::class);
        $service->append($path, collect([$nc]), true);
        $service->embedReportPages($path, [['halaman.png', '', true]], '${lampiran_harian}', 'LAPORAN HARIAN');
        $service->embedExternals($path, collect([new LaporanExternal([
            'judul' => 'Dokumen Uji', 'file_name' => 'halaman.png', 'file_path' => 'halaman.png', 'file_status' => 'completed',
        ])]));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET));
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $id = $xpath->evaluate('string((//w:sectPr)[1]/w:footerReference[@w:type="default"]/@r:id)');
            $relations = new \DOMDocument;
            $relations->loadXML($zip->getFromName('word/_rels/document.xml.rels'), LIBXML_NONET);
            $relationXpath = new \DOMXPath($relations);
            $target = $relationXpath->evaluate('string(//*[local-name()="Relationship"][@Id="'.$id.'"]/@Target)');
            $this->assertStringContainsString('FOOTER UTAMA', $zip->getFromName('word/'.$target));
            $this->assertStringNotContainsString('${lampiran_', $dom->textContent);
        } finally {
            $zip->close();
        }
        $textBinary = dirname(config('services.poppler.pdfinfo')).'/pdftotext'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        if (is_executable($textBinary) && class_exists(\COM::class)) {
            $pdf = Storage::disk('local')->path('gabungan.pdf');
            app(DocxToPdfService::class)->convert($path, $pdf);
            $process = new Process([$textBinary, '-layout', $pdf, '-']);
            $process->mustRun();
            $pages = explode("\f", $process->getOutput());
            array_pop($pages);
            $this->assertCount(8, $pages, json_encode($pages, JSON_UNESCAPED_UNICODE));
            foreach ([0, 1] as $index) {
                $this->assertStringContainsString('FOOTER UTAMA', $pages[$index]);
            }
            $this->assertStringContainsString('E. LAMPIRAN', $pages[1]);
            foreach (range(2, 7) as $index) {
                $this->assertStringNotContainsString('FOOTER UTAMA', $pages[$index]);
            }
            foreach ([2 => 'DOKUMEN EKSTERNAL', 4 => 'LAPORAN HARIAN', 6 => 'LAMPIRAN NON-CONFORMANCE'] as $index => $title) {
                $this->assertStringContainsString($title, $pages[$index]);
            }
        }
    }

    #[Test]
    public function marker_tetap_dibersihkan_walau_tanpa_file(): void
    {
        Storage::fake('local');
        $external = new LaporanExternal(['judul' => 'Tanpa File']);

        $path = Storage::disk('local')->path('output.docx');
        $this->makeDocxWithExternalMarker($path);
        app(WordAttachmentService::class)->embedExternals($path, collect([$external]));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $this->assertStringNotContainsString('${lampiran_external}', $zip->getFromName('word/document.xml'));
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function dokumen_belum_selesai_diproses_menggagalkan_embed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ext.pdf', '%PDF-1.4');
        $external = new LaporanExternal([
            'judul' => 'Pending',
            'file_name' => 'ext.pdf',
            'file_path' => 'ext.pdf',
            'file_status' => 'processing',
        ]);

        $path = Storage::disk('local')->path('output.docx');
        $this->makeDocxWithExternalMarker($path);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum selesai diproses');
        app(WordAttachmentService::class)->embedExternals($path, collect([$external]));
    }

    #[Test]
    public function gambar_halaman_laporan_dicache_per_doc_path_dan_dibuang_saat_dihapus(): void
    {
        if (! is_executable(config('services.poppler.pdftoppm')) || ! is_executable(config('services.poppler.pdfinfo'))) {
            $this->markTestSkipped('Poppler belum tersedia pada path konfigurasi.');
        }
        Storage::fake('local');
        $docPath = 'laporan/word/harian.docx';
        $this->makeDocxWithExternalMarker(Storage::disk('local')->path('harian.docx'));
        Storage::disk('local')->put($docPath, (string) file_get_contents(Storage::disk('local')->path('harian.docx')));

        $conversions = 0;
        $pdf = Pdf::loadHTML('<p>HALAMAN 1</p><div style="page-break-before:always"></div><p>HALAMAN 2</p>')->output();
        $this->mock(DocxToPdfService::class, function ($mock) use (&$conversions, $pdf): void {
            $mock->shouldReceive('convert')->andReturnUsing(function (string $source, string $target) use (&$conversions, $pdf): void {
                $conversions++;
                file_put_contents($target, $pdf);
            });
        });

        $service = app(WordAttachmentService::class);
        $first = $service->reportPageImages($docPath);
        $second = $service->reportPageImages($docPath);

        $this->assertCount(2, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $conversions, 'Render kedua harus memakai cache, bukan konversi ulang.');
        foreach ($first as [$page, $caption, $fullPage]) {
            $this->assertStringStartsWith('laporan/lampiran-pages/'.sha1($docPath).'/', $page);
            $this->assertSame('', $caption);
            $this->assertTrue($fullPage);
            Storage::disk('local')->assertExists($page);
        }

        // Halaman cache hilang → dirender ulang, bukan mengembalikan path mati.
        Storage::disk('local')->delete($first[0][0]);
        $this->assertCount(2, $service->reportPageImages($docPath));
        $this->assertSame(2, $conversions);

        app(LaporanDocumentService::class)->deleteDocFile($docPath);
        Storage::disk('local')->assertMissing($docPath);
        Storage::disk('local')->assertMissing('laporan/lampiran-pages/'.sha1($docPath));
    }
}
