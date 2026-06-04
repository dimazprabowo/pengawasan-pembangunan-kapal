<?php

namespace App\Jobs;

use App\Models\LaporanExternal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessLaporanExternal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public int $laporanExternalId,
        public string $tempPath
    ) {}

    public function handle(): void
    {
        $laporanExternal = LaporanExternal::find($this->laporanExternalId);

        if (!$laporanExternal) {
            return;
        }

        try {
            if (!Storage::disk('local')->exists($this->tempPath)) {
                $laporanExternal->update([
                    'file_status' => 'failed',
                    'file_error' => 'Temporary file not found',
                ]);
                return;
            }

            $fileContent = Storage::disk('local')->get($this->tempPath);
            $fileSize = strlen($fileContent);

            $extension = strtolower(pathinfo($laporanExternal->file_name, PATHINFO_EXTENSION));
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $destinationPath = 'laporan-external/' . $fileName;

            Storage::disk('local')->put($destinationPath, $fileContent);
            Storage::disk('local')->delete($this->tempPath);

            $laporanExternal->update([
                'file_path' => $destinationPath,
                'file_size' => $fileSize,
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);
        } catch (\Exception $e) {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => $e->getMessage(),
            ]);

            // Clean up temp file if it exists
            if (Storage::disk('local')->exists($this->tempPath)) {
                Storage::disk('local')->delete($this->tempPath);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $laporanExternal = LaporanExternal::find($this->laporanExternalId);

        if ($laporanExternal) {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => $exception->getMessage(),
            ]);
        }

        // Clean up temp file if it exists
        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
