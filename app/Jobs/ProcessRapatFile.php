<?php

namespace App\Jobs;

use App\Models\RapatFile;
use App\Services\FileStorageService;
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

class ProcessRapatFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public RapatFile $rapatFile,
        public string $tempPath,
        public ?array $cropData = null,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        // Decode + resize gambar resolusi tinggi bisa melampaui memory_limit default
        @ini_set('memory_limit', '512M');

        $files = app(FileStorageService::class);
        $current = $this->rapatFile->fresh();
        if (! $current || $current->file_status === 'completed') {
            $files->deleteTemp($this->tempPath);

            return;
        }
        $this->rapatFile = $current;
        $oldPath = $current->file_path;
        $stored = null;
        $this->rapatFile->update(['file_status' => 'processing']);

        if (! Storage::disk()->exists($this->tempPath)) {
            $this->rapatFile->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk()->get($this->tempPath);
            $originalName = $this->rapatFile->file_name;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($fileContent);
            $extension = $mime === 'application/pdf' ? 'pdf' : 'webp';
            $isImage = str_starts_with((string) $mime, 'image/');
            if (! $isImage && $mime !== 'application/pdf') {
                throw new \RuntimeException('Tipe file rapat tidak didukung.');
            }

            // Gambar: apply crop (bila ada) lalu konversi WebP — pola ProcessNcFoto
            if ($isImage) {
                $fileContent = $this->processImage($fileContent);
                $extension = 'webp';
            }

            $stored = $files->storeProcessed('proyek/rapat', $fileContent, $originalName, $extension);
            if (! $this->rapatFile->fresh()) {
                $files->deleteFiles([$stored['path']]);
                $files->deleteTemp($this->tempPath);

                return;
            }

            if (! DB::transaction(fn () => $this->rapatFile->update([
                'file_path' => $stored['path'],
                'file_name' => $stored['name'],
                'file_size' => $stored['size'],
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]))) {
                throw new \RuntimeException('Gagal memperbarui metadata file rapat.');
            }
        } catch (\Throwable $e) {
            $files->deleteFiles([$stored['path'] ?? null]);
            $this->rapatFile->fresh()?->update([
                'file_status' => 'failed',
                'file_error' => 'File rapat gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }

        $files->deleteFiles([$oldPath]);
        $files->deleteTemp($this->tempPath);
    }

    /**
     * Apply crop (bila crop data ada), downscale sisi terpanjang ke maks 1600px,
     * lalu konversi WebP q85 — pola yang sama dengan ProcessNcFoto.
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
        Log::error('ProcessRapatFile gagal', ['file_id' => $this->rapatFile->id, 'error' => $exception->getMessage()]);
        $current = $this->rapatFile->fresh();
        if ($current && $current->file_status !== 'completed') {
            $current->update([
                'file_status' => 'failed',
                'file_error' => 'File rapat gagal diproses. Silakan unggah ulang.',
            ]);
        }

        app(FileStorageService::class)->deleteTemp($this->tempPath);
    }
}
