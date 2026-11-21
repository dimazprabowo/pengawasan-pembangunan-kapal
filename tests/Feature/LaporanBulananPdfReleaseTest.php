<?php

namespace Tests\Feature;

use App\Jobs\ReleaseLaporanBulananPdfJob;
use App\Livewire\LaporanBulanan\LaporanBulananIndex;
use App\Livewire\LaporanBulanan\LaporanBulananShow;
use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\User;
use App\Services\DocxToPdfService;
use App\Services\FilePathService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaporanBulananPdfReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    private function admin(): User
    {
        return User::firstOrFail();
    }

    private function dataKapal(): DataKapal
    {
        return DataKapal::firstOrFail();
    }

    private function runJob(LaporanBulanan $laporan, DocxToPdfService $converter): void
    {
        (new ReleaseLaporanBulananPdfJob($laporan))->handle(
            $converter,
            app(QueueStatusService::class),
            app(FilePathService::class),
        );
    }

    private function laporan(array $attributes = []): LaporanBulanan
    {
        return LaporanBulanan::create(array_merge([
            'user_id' => $this->admin()->id,
            'data_kapal_id' => $this->dataKapal()->id,
            'tanggal_laporan' => now()->toDateString(),
            'bulan_ke' => 1,
        ], $attributes));
    }

    private function laporanWithDoc(): LaporanBulanan
    {
        Storage::disk()->put('laporan/word/laporan-bulanan-test.docx', 'fake-docx');

        return $this->laporan([
            'doc_path' => 'laporan/word/laporan-bulanan-test.docx',
            'doc_name' => 'BKI-Laporan-Bulanan-Bulan-ke-1.docx',
            'doc_status' => 'completed',
            'doc_generated_at' => now(),
        ]);
    }

    #[Test]
    public function release_requires_generated_word_document(): void
    {
        Bus::fake();

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananShow::class, [
                'dataKapal' => $this->dataKapal(),
                'laporanBulanan' => $this->laporan(),
            ])
            ->call('releasePdf')
            ->assertDispatched('notify');

        Bus::assertNotDispatched(ReleaseLaporanBulananPdfJob::class);
    }

    #[Test]
    public function release_dispatches_job_and_marks_pending(): void
    {
        Bus::fake();
        $laporan = $this->laporanWithDoc();

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananShow::class, [
                'dataKapal' => $this->dataKapal(),
                'laporanBulanan' => $laporan,
            ])
            ->call('releasePdf');

        Bus::assertDispatched(ReleaseLaporanBulananPdfJob::class);
        $this->assertSame('pending', $laporan->fresh()->pdf_status);
    }

    #[Test]
    public function job_converts_docx_and_marks_pdf_completed(): void
    {
        $laporan = $this->laporanWithDoc();

        $converter = new class extends DocxToPdfService
        {
            public string $targetPath = '';

            public function convert(string $docxAbsolutePath, string $pdfAbsolutePath): void
            {
                $this->targetPath = $pdfAbsolutePath;
                file_put_contents($pdfAbsolutePath, '%PDF-1.4 fake');
            }
        };

        $this->runJob($laporan, $converter);

        $laporan->refresh();
        $this->assertTrue($laporan->isPdfCompleted());
        $this->assertSame('BKI-Laporan-Bulanan-Bulan-ke-1.pdf', $laporan->pdf_name);
        Storage::disk()->assertExists($laporan->pdf_path);
        $this->assertSame('%PDF-1.4 fake', Storage::disk()->get($laporan->pdf_path));
    }

    #[Test]
    public function regenerating_word_clears_released_pdf(): void
    {
        Storage::disk()->put('laporan/pdf/old.pdf', '%PDF old');

        $laporan = $this->laporanWithDoc();
        $laporan->update([
            'pdf_path' => 'laporan/pdf/old.pdf',
            'pdf_name' => 'old.pdf',
            'pdf_status' => 'completed',
            'pdf_generated_at' => now(),
        ]);

        ReleaseLaporanBulananPdfJob::clearPdf($laporan);

        $laporan->refresh();
        $this->assertFalse($laporan->hasPdf());
        $this->assertNull($laporan->pdf_status);
        Storage::disk()->assertMissing('laporan/pdf/old.pdf');
    }

    #[Test]
    public function job_ignores_release_when_doc_no_longer_completed(): void
    {
        $converter = new class extends DocxToPdfService
        {
            public function convert(string $docxAbsolutePath, string $pdfAbsolutePath): void
            {
                throw new \LogicException('Tidak boleh dipanggil.');
            }
        };

        $this->runJob($this->laporan(), $converter); // tanpa doc

        $this->assertNull($this->laporan()->pdf_status);
    }

    #[Test]
    public function job_marks_failed_when_doc_file_missing_on_disk(): void
    {
        // doc_* lengkap di DB tetapi file fisik tidak ada di disk.
        $laporan = $this->laporan([
            'doc_path' => 'laporan/word/tidak-ada.docx',
            'doc_name' => 'tidak-ada.docx',
            'doc_status' => 'completed',
        ]);

        $converter = new class extends DocxToPdfService
        {
            public function convert(string $docxAbsolutePath, string $pdfAbsolutePath): void
            {
                throw new \LogicException('Tidak boleh dipanggil.');
            }
        };

        $this->runJob($laporan, $converter);

        $laporan->refresh();
        $this->assertTrue($laporan->isPdfFailed());
        $this->assertStringContainsString('tidak ditemukan', $laporan->pdf_error);
    }

    #[Test]
    public function download_pdf_from_index_requires_completed_release(): void
    {
        $laporan = $this->laporanWithDoc();

        // Belum release → warning, tidak ada response download.
        Livewire::actingAs($this->admin())
            ->test(LaporanBulananIndex::class, ['dataKapal' => $this->dataKapal()])
            ->call('downloadPdf', $laporan->id)
            ->assertDispatched('notify');

        // Setelah completed → file ter-download.
        Storage::disk()->put('laporan/pdf/rilis.pdf', '%PDF rilis');
        $laporan->update([
            'pdf_path' => 'laporan/pdf/rilis.pdf',
            'pdf_name' => 'rilis.pdf',
            'pdf_status' => 'completed',
            'pdf_generated_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(LaporanBulananIndex::class, ['dataKapal' => $this->dataKapal()])
            ->call('downloadPdf', $laporan->id)
            ->assertFileDownloaded('rilis.pdf');
    }
}
