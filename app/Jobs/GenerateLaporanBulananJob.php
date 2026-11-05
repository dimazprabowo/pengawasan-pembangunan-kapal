<?php

namespace App\Jobs;

use App\Models\LaporanBulanan;
use App\Services\LaporanBulananWordService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLaporanBulananJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function __construct(
        public LaporanBulanan $laporan,
    ) {}

    public function handle(LaporanBulananWordService $wordService, QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        // Load dataKapal relationship for template detection
        $this->laporan->load('dataKapal');

        // Delete old generated doc if re-generating
        if ($this->laporan->doc_path) {
            $oldPath = storage_path('app/'.$this->laporan->doc_path);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $this->laporan->update([
            'doc_status' => 'processing',
            'doc_error' => null,
        ]);

        try {
            $storagePath = $wordService->generate($this->laporan);

            $this->laporan->update([
                'doc_path' => $storagePath,
                'doc_name' => 'Laporan-Bulanan-'.$this->laporan->id.'-'.now()->format('YmdHis').'.docx',
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
