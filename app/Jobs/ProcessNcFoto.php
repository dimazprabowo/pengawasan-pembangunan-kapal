<?php

namespace App\Jobs;

use App\Models\LaporanNcFoto;
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

class ProcessNcFoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public LaporanNcFoto $ncFoto,
        public string $tempPath,
        public ?array $cropData = null,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        // Decode + resize foto resolusi tinggi bisa melampaui memory_limit default 128M
        @ini_set('memory_limit', '512M');

        $files = app(FileStorageService::class);
        $current = $this->ncFoto->fresh();
        if (! $current || $current->file_status === 'completed') {
            $files->deleteTemp($this->tempPath);

            return;
        }
        $this->ncFoto = $current;
        $oldPaths = array_merge([$current->file_path], $current->rendered_pages ?? []);
        $stored = null;
        $pages = [];
        $this->ncFoto->update(['file_status' => 'processing']);

        if (! Storage::disk()->exists($this->tempPath)) {
            $this->ncFoto->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk()->get($this->tempPath);
            $originalName = $this->ncFoto->file_name;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($fileContent);
            $extension = $mime === 'application/pdf' ? 'pdf' : 'webp';
            $isImage = str_starts_with((string) $mime, 'image/');
            if (! $isImage && $mime !== 'application/pdf') {
                throw new \RuntimeException('Tipe file NC tidak didukung.');
            }

            // Gambar: apply crop (bila ada) lalu konversi WebP (konsisten lampiran aktivitas)
            if ($isImage) {
                $fileContent = $this->processImage($fileContent);
                $extension = 'webp';
            }

            $pages = $isImage ? [] : app(PdfPageService::class)->render($this->tempPath);
            $stored = $files->storeProcessed('laporan-nc/foto', $fileContent, $originalName, $extension);
            if (! $this->ncFoto->fresh()) {
                $files->deleteFiles(array_merge([$stored['path']], $pages));
                $files->deleteTemp($this->tempPath);

                return;
            }

            if (! DB::transaction(fn () => $this->ncFoto->update([
                'file_path' => $stored['path'],
                'rendered_pages' => $pages,
                'file_name' => $stored['name'],
                'file_size' => $stored['size'],
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]))) {
                throw new \RuntimeException('Gagal memperbarui metadata file NC.');
            }
        } catch (\Throwable $e) {
            $files->deleteFiles(array_merge([$stored['path'] ?? null], $pages));
            $this->ncFoto->fresh()?->update([
                'file_status' => 'failed',
                'file_error' => 'File NC gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }

        $files->deleteFiles($oldPaths);
        $files->deleteTemp($this->tempPath);
    }

    /**
     * Apply crop (bila crop data ada), downscale sisi terpanjang ke maks 1600px,
     * lalu konversi WebP q85 — pola yang sama dengan ProcessLaporanLampiran.
     */
    private function processImage(string $fileContent): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($fileContent);

        if ($this->cropData) {
            $x = (int) ($this->cropData['x'] ?? 0);
            $y = (int) ($this->cropData['y'] ?? 0);
            $width = (int) ($this->cropData['width'] ?? $image->width());
            $height = (int) ($this->cropData['height'] ?? $image->height());

            $image->crop($width, $height, $x, $y);
        }

        $image->scaleDown(width: 1600, height: 1600);

        return $image->toWebp(85);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessNcFoto gagal', ['file_id' => $this->ncFoto->id, 'error' => $exception->getMessage()]);
        $current = $this->ncFoto->fresh();
        if ($current && $current->file_status !== 'completed') {
            $current->update([
                'file_status' => 'failed',
                'file_error' => 'File NC gagal diproses. Silakan unggah ulang.',
            ]);
        }

        app(FileStorageService::class)->deleteTemp($this->tempPath);
    }
}
