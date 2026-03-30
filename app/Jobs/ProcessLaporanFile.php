<?php

namespace App\Jobs;

use App\Models\Laporan;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessLaporanFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public Laporan $laporan,
        public string $tempPath,
        public string $originalName,
        public int $fileSize,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        // Mark queue worker as active
        $queueStatusService->markWorkerActive();

        // Update status to processing
        $this->laporan->update([
            'file_status' => 'processing',
        ]);

        if (!Storage::disk('local')->exists($this->tempPath)) {
            $this->laporan->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);
            return;
        }

        try {
            $extension = pathinfo($this->originalName, PATHINFO_EXTENSION);
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $destinationPath = 'laporan/' . $this->laporan->tipe->value . '/' . $fileName;

            $fileContent = Storage::disk('local')->get($this->tempPath);
            Storage::disk('local')->put($destinationPath, $fileContent);

            $this->laporan->update([
                'file_path' => $destinationPath,
                'file_name' => $this->originalName,
                'file_size' => $this->fileSize,
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);

            // Clean up temp file
            Storage::disk('local')->delete($this->tempPath);
        } catch (\Exception $e) {
            $this->laporan->update([
                'file_status' => 'failed',
                'file_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Update status to failed
        $this->laporan->update([
            'file_status' => 'failed',
            'file_error' => $exception->getMessage(),
        ]);

        // Clean up temp file on failure
        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
