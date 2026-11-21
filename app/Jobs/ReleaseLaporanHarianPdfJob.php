<?php

namespace App\Jobs;

use App\Models\LaporanHarian;
use App\Services\DocxToPdfService;
use App\Services\FilePathService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Konversi dokumen Word laporan harian menjadi PDF rilis (Release).
 * PDF ikut dibersihkan saat dokumen Word digenerate ulang sehingga
 * tombol download hanya muncul setelah Release terakhir yang valid.
 */
class ReleaseLaporanHarianPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public int $timeout = 240;

    public function __construct(
        public LaporanHarian $laporan,
    ) {}

    public function handle(DocxToPdfService $converter, QueueStatusService $queueStatusService, FilePathService $files): void
    {
        $queueStatusService->markWorkerActive();

        if (! $this->laporan->hasDoc() || ! $this->laporan->isDocCompleted()) {
            return; // Dokumen belum/tidak lagi tergenerate — abaikan rilis basi.
        }

        if (! $files->exists($this->laporan->doc_path)) {
            $this->laporan->update([
                'pdf_status' => 'failed',
                'pdf_error' => 'File dokumen Word tidak ditemukan.',
            ]);

            return;
        }

        $this->laporan->update([
            'pdf_status' => 'processing',
            'pdf_error' => null,
        ]);

        // Hapus rilis PDF lama bila ada (release ulang).
        $this->deleteOldPdf($files);

        // Word COM / soffice hanya bekerja dengan path filesystem lokal —
        // baca docx via salinan lokal, tulis pdf ke temp lalu commit ke disk.
        $docLocal = $files->localPath($this->laporan->doc_path);
        $pdfLocal = $files->tempPath('pdf');
        $pdfPath = 'laporan/pdf/'.sprintf(
            'laporan-harian-%d-%s.pdf',
            $this->laporan->id,
            now()->format('YmdHis')
        );

        try {
            $converter->convert($docLocal, $pdfLocal);
            $files->commitLocalFile($pdfLocal, $pdfPath);

            $this->laporan->update([
                'pdf_path' => $pdfPath,
                'pdf_name' => preg_replace('/\.docx$/i', '.pdf', $this->laporan->doc_name)
                    ?? ('Laporan-Harian-'.$this->laporan->id.'.pdf'),
                'pdf_status' => 'completed',
                'pdf_generated_at' => now(),
                'pdf_error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->laporan->update([
                'pdf_status' => 'failed',
                'pdf_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->laporan->update([
            'pdf_status' => 'failed',
            'pdf_error' => $exception->getMessage(),
        ]);
    }

    /**
     * Bersihkan file & metadata PDF rilis — dipakai saat release ulang
     * dan saat dokumen Word digenerate ulang/dihapus.
     */
    public static function clearPdf(LaporanHarian $laporan): void
    {
        if ($laporan->pdf_path) {
            app(FilePathService::class)->delete($laporan->pdf_path);
        }

        $laporan->update([
            'pdf_path' => null,
            'pdf_name' => null,
            'pdf_status' => null,
            'pdf_generated_at' => null,
            'pdf_error' => null,
        ]);
    }

    private function deleteOldPdf(FilePathService $files): void
    {
        if ($this->laporan->pdf_path) {
            $files->delete($this->laporan->pdf_path);
        }
    }
}
