<?php

namespace App\Jobs;

use App\Models\LaporanMingguan;
use App\Services\LaporanCompletenessService;
use App\Services\LaporanDocumentService;
use App\Services\LaporanMingguanWordService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;

class GenerateLaporanMingguanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 600;

    public function __construct(
        public LaporanMingguan $laporan,
    ) {}

    public function handle(LaporanMingguanWordService $wordService, QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        // Load dataKapal relationship for template detection
        $this->laporan->load('dataKapal');
        try {
            app(LaporanCompletenessService::class)->prepareForGeneration($this->laporan);
        } catch (ValidationException $e) {
            $this->laporan->update(['doc_status' => 'failed', 'doc_error' => $e->validator->errors()->first()]);

            return;
        }

        // Delete old generated doc if re-generating
        app(LaporanDocumentService::class)->deleteDocFile($this->laporan->doc_path);

        // Rilis PDF ikut tidak valid saat Word digenerate ulang —
        // tombol download PDF hilang sampai Release diklik lagi.
        ReleaseLaporanMingguanPdfJob::clearPdf($this->laporan);

        $this->laporan->update([
            'doc_status' => 'processing',
            'doc_error' => null,
        ]);

        try {
            $storagePath = $wordService->generate($this->laporan);

            $this->laporan->update([
                'doc_path' => $storagePath,
                'doc_name' => ($this->laporan->dataKapal?->kode ?? 'BKI')
                    .'-Laporan-Mingguan-Minggu-ke-'.($this->laporan->minggu_ke ?? $this->laporan->id).'.docx',
                'doc_status' => 'completed',
                'doc_generated_at' => now(),
                'doc_error' => null,
            ]);

            $queueStatusService->markWorkerActive();
        } catch (\Exception $e) {
            $this->laporan->update([
                'doc_status' => 'failed',
                'doc_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->laporan->update([
            'doc_status' => 'failed',
            'doc_error' => $exception->getMessage(),
        ]);
    }
}
