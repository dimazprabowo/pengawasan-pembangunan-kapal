<?php

namespace App\Jobs;

use App\Models\LaporanExternal;
use App\Services\FileStorageService;
use App\Services\PdfPageService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProcessLaporanExternal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public int $laporanExternalId,
        public string $tempPath
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        // Decode + resize gambar resolusi tinggi bisa melampaui memory_limit default 128M
        @ini_set('memory_limit', '512M');

        $laporanExternal = LaporanExternal::find($this->laporanExternalId);

        $files = app(FileStorageService::class);
        if (! $laporanExternal || $laporanExternal->file_status === 'completed') {
            $files->deleteTemp($this->tempPath);

            return;
        }
        $oldPaths = array_merge([$laporanExternal->file_path], $laporanExternal->rendered_pages ?? []);
        $stored = null;
        $pages = [];
        $laporanExternal->update(['file_status' => 'processing']);

        if (! Storage::disk()->exists($this->tempPath)) {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk()->get($this->tempPath);
            $originalName = $laporanExternal->file_name;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($fileContent);
            $isImage = str_starts_with((string) $mime, 'image/');
            if (! $isImage && $mime !== 'application/pdf') {
                throw new \RuntimeException('Tipe dokumen eksternal tidak didukung.');
            }
            $extension = $isImage ? 'webp' : 'pdf';

            // Gambar: downscale sisi terpanjang ke maks 1600px lalu konversi WebP
            // (konsisten dengan lampiran NC agar kompatibel export Word)
            if ($isImage) {
                $fileContent = $this->processImage($fileContent);
            }

            $pages = $isImage ? [] : app(PdfPageService::class)->render($this->tempPath, 'laporan-external/pages');
            $stored = $files->storeProcessed('laporan-external', $fileContent, (string) $originalName, $extension);
            if (! $laporanExternal->fresh()) {
                $files->deleteFiles(array_merge([$stored['path']], $pages));
                $files->deleteTemp($this->tempPath);

                return;
            }

            if (! DB::transaction(fn () => $laporanExternal->update([
                'file_path' => $stored['path'],
                'rendered_pages' => $pages,
                'file_name' => $stored['name'],
                'file_size' => $stored['size'],
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]))) {
                throw new \RuntimeException('Gagal memperbarui metadata dokumen eksternal.');
            }
        } catch (\Throwable $e) {
            $files->deleteFiles(array_merge([$stored['path'] ?? null], $pages));
            $laporanExternal->fresh()?->update([
                'file_status' => 'failed',
                'file_error' => 'Dokumen eksternal gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }

        $files->deleteFiles($oldPaths);
        $files->deleteTemp($this->tempPath);
    }

    private function processImage(string $fileContent): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($fileContent);
        $image->scaleDown(width: 1600, height: 1600);

        return $image->toWebp(85);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessLaporanExternal gagal', ['external_id' => $this->laporanExternalId, 'error' => $exception->getMessage()]);

        $laporanExternal = LaporanExternal::find($this->laporanExternalId);

        if ($laporanExternal && $laporanExternal->file_status !== 'completed') {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => 'Dokumen eksternal gagal diproses. Silakan unggah ulang.',
            ]);
        }

        app(FileStorageService::class)->deleteTemp($this->tempPath);
    }
}
