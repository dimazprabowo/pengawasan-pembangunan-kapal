<?php

namespace Tests\Feature;

use App\Services\PdfPengesahanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Penggantian halaman LEMBAR PENGESAHAN pada PDF rilis bulanan dengan
 * PDF tanda tangan basah yang diupload — memakai poppler-utils.
 */
class PdfPengesahanServiceTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function makePdf(array $pages): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ppk-').'.pdf';
        Pdf::loadHTML(implode('', array_map(
            fn (string $text) => '<div style="page-break-after:always">'.$text.'</div>',
            $pages
        )))->save($path);
        $this->files[] = $path;

        return $path;
    }

    private function text(string $pdf): string
    {
        $process = new Process([config('services.poppler.pdftotext'), $pdf, '-']);
        $process->mustRun();

        return mb_strtoupper($process->getOutput());
    }

    public function test_mengganti_halaman_pengesahan_dengan_pdf_upload(): void
    {
        $target = $this->makePdf(['COVER', 'LEMBAR PENGESAHAN KOSONG', 'BAB 1']);
        $pengesahan = $this->makePdf(['LEMBAR PENGESAHAN DITANDATANGANI']);

        app(PdfPengesahanService::class)->replace($target, $pengesahan);

        $text = $this->text($target);
        $this->assertStringContainsString('DITANDATANGANI', $text);
        $this->assertStringNotContainsString('KOSONG', $text);
        $this->assertStringContainsString('BAB 1', $text);
    }

    public function test_pengesahan_multi_halaman_menggantikan_semua(): void
    {
        $target = $this->makePdf(['COVER', 'LEMBAR PENGESAHAN GENERATE', 'BAB 1']);
        $pengesahan = $this->makePdf(['LEMBAR PENGESAHAN HALAMAN 1', 'LANJUTAN HALAMAN 2']);

        app(PdfPengesahanService::class)->replace($target, $pengesahan);

        $text = $this->text($target);
        $this->assertStringContainsString('LANJUTAN HALAMAN 2', $text);
        $this->assertStringNotContainsString('GENERATE', $text);
    }

    public function test_laporan_126_halaman_mempertahankan_urutan_dan_pengesahan_multi_halaman(): void
    {
        $pages = array_map(fn (int $page) => sprintf('HALAMAN-%03d', $page), range(1, 126));
        $pages[1] = 'LEMBAR PENGESAHAN KOSONG';
        $target = $this->makePdf($pages);
        $pengesahan = $this->makePdf(['TANDA TANGAN PERTAMA', 'TANDA TANGAN KEDUA']);

        app(PdfPengesahanService::class)->replace($target, $pengesahan);

        $text = $this->text($target);
        $this->assertStringNotContainsString('KOSONG', $text);
        $expected = array_merge(['HALAMAN-001', 'TANDA TANGAN PERTAMA', 'TANDA TANGAN KEDUA'], array_slice($pages, 2));
        $offset = 0;
        foreach ($expected as $label) {
            $position = strpos($text, $label, $offset);
            $this->assertNotFalse($position, $label);
            $offset = $position + strlen($label);
        }
    }

    public function test_gagal_bila_halaman_pengesahan_tidak_ditemukan(): void
    {
        $target = $this->makePdf(['COVER', 'BAB 1']);
        $pengesahan = $this->makePdf(['LEMBAR PENGESAHAN']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LEMBAR PENGESAHAN');
        app(PdfPengesahanService::class)->replace($target, $pengesahan);
    }
}
