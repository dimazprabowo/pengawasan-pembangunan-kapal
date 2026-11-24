<?php

namespace Tests\Feature;

use App\Models\LaporanNc;
use App\Models\LaporanNcFoto;
use App\Services\PdfPageService;
use App\Services\WordAttachmentService;
use App\Services\WordStyleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Tests\TestCase;
use ZipArchive;

class NcAttachmentProcessingTest extends TestCase
{
    #[Test]
    public function pdf_hasil_scan_dirender_semua_halaman_dan_disisipkan_ke_word(): void
    {
        if (! is_executable(config('services.poppler.pdftoppm')) || ! is_executable(config('services.poppler.pdfinfo'))) {
            $this->markTestSkipped('Poppler belum tersedia pada path konfigurasi.');
        }
        Storage::fake('local');
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 10, 40, 'SCAN TTD NC', imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $data = base64_encode(ob_get_clean());
        $html = '<img src="data:image/png;base64,'.$data.'"><div style="page-break-before:always"></div><img src="data:image/png;base64,'.$data.'">';
        Storage::disk('local')->put('scan.pdf', Pdf::loadHTML($html)->output());
        $pages = app(PdfPageService::class)->render('scan.pdf');
        $this->assertCount(2, $pages);
        foreach ($pages as $page) {
            Storage::disk('local')->assertExists($page);
            $this->assertSame(IMAGETYPE_WEBP, getimagesize(Storage::disk('local')->path($page))[2]);
        }
        $file = new LaporanNcFoto(['kategori' => 'close', 'file_name' => 'scan.pdf', 'file_path' => 'scan.pdf', 'file_status' => 'completed', 'rendered_pages' => $pages]);
        $nc = new LaporanNc(['nomor' => 'NC-001', 'kendala' => 'Las & sambungan']);
        $nc->setRelation('foto', collect([$file]));
        $document = new PhpWord;
        $document->addSection()->addText('Form NC');
        WordStyleService::prepare($document);
        $path = Storage::disk('local')->path('output.docx');
        IOFactory::createWriter($document, 'Word2007')->save($path);
        app(WordAttachmentService::class)->append($path, collect([$nc]));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $xml = $zip->getFromName('word/document.xml');
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));
            $xpath = new \DOMXPath($dom);
            $this->assertSame(2, $xpath->query('//*[local-name()="drawing"]')->length);
            $this->assertSame(1, $xpath->query('//*[local-name()="pageBreakBefore"]')->length);
            $this->assertStringNotContainsString('Halaman 1', $xml);
            $this->assertStringNotContainsString('Halaman 2', $xml);
            $this->assertStringNotContainsString('Close', $xml);
            $this->assertStringNotContainsString('Las &amp; sambungan', $xml);
            $this->assertStringNotContainsString('scan.pdf', $dom->textContent);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $this->assertSame('0', $xpath->evaluate('string(/w:document/w:body/w:sectPr/w:pgMar/@w:left)'));
            $this->assertSame(3, $xpath->query('/w:document/w:body/w:sectPr/w:footerReference')->length);
            $this->assertStringNotContainsString('altChunk', $xml);
            $this->assertSame(2, count(array_filter(range(0, $zip->numFiles - 1), fn ($i) => str_starts_with($zip->getNameIndex($i), 'word/media/att_'))));
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function renderer_tidak_tersedia_tidak_menghasilkan_lampiran_kosong(): void
    {
        Storage::fake('local');
        config(['services.poppler.pdfinfo' => 'missing-pdfinfo-binary-for-test']);
        Storage::disk('local')->put('scan.pdf', '%PDF-1.4');
        $this->expectException(ProcessFailedException::class);
        app(PdfPageService::class)->render('scan.pdf');
    }
}
