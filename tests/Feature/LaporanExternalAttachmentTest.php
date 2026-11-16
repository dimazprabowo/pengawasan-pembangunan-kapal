<?php

namespace Tests\Feature;

use App\Models\LaporanExternal;
use App\Services\PdfPageService;
use App\Services\WordAttachmentService;
use App\Services\WordStyleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class LaporanExternalAttachmentTest extends TestCase
{
    private function makeDocxWithExternalMarker(string $path): void
    {
        $document = new PhpWord;
        $section = $document->addSection();
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
            $this->assertStringContainsString('Sertifikat Uji', $xml);
            $this->assertStringContainsString('Halaman 1', $xml);
            $this->assertStringNotContainsString('${lampiran_external}', $xml);
            // Konten embed harus berada di bawah tabel, sebelum section berikutnya
            $this->assertLessThan(
                strpos($xml, 'SECTION SETELAH MARKER'),
                strpos($xml, 'Sertifikat Uji')
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
            $this->assertStringContainsString('Foto Dokumen', $xml);
            $this->assertStringNotContainsString('${lampiran_external}', $xml);
        } finally {
            $zip->close();
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
    public function signature_is_current_mencakup_file_eksternal(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('output.docx');
        $this->makeDocxWithExternalMarker($path);

        $external = new LaporanExternal([
            'judul' => 'Dok A',
            'file_name' => 'a.pdf',
            'file_path' => 'a.pdf',
            'file_status' => 'completed',
            'rendered_pages' => ['laporan-external/pages/x/page-1.webp'],
        ]);
        $service = app(WordAttachmentService::class);
        $service->append($path, collect(), false, collect([$external]));

        $this->assertTrue($service->isCurrent($path, collect(), collect([$external])));
        $this->assertFalse($service->isCurrent($path, collect(), collect()));
        $this->assertFalse($service->isCurrent($path, collect()));

        $external->rendered_pages = ['laporan-external/pages/x/page-1.webp', 'laporan-external/pages/x/page-2.webp'];
        $this->assertFalse($service->isCurrent($path, collect(), collect([$external])));
    }
}
