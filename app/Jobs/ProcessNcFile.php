<?php

namespace App\Jobs;

use App\Models\LaporanNcFile;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessNcFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public LaporanNcFile $ncFile,
        public string $tempPath,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        $this->ncFile->update(['file_status' => 'processing']);

        if (! Storage::disk('local')->exists($this->tempPath)) {
            $this->ncFile->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk('local')->get($this->tempPath);
            $originalName = $this->ncFile->file_name;
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $fileName = time().'_'.uniqid().'.'.$extension;
            $destinationPath = 'laporan-nc/'.$fileName;

            Storage::disk('local')->put($destinationPath, $fileContent);

            $this->ncFile->update([
                'file_path' => $destinationPath,
                'file_name' => $originalName,
                'file_size' => strlen($fileContent),
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);

            Storage::disk('local')->delete($this->tempPath);
        } catch (\Exception $e) {
            $this->ncFile->update([
                'file_status' => 'failed',
                'file_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->ncFile->update([
            'file_status' => 'failed',
            'file_error' => $exception->getMessage(),
        ]);

        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
