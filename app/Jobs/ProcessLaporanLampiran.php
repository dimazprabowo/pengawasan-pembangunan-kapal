<?php

namespace App\Jobs;

use App\Models\LaporanLampiran;
use App\Services\FilePathService;
use App\Services\FileStorageService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProcessLaporanLampiran implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public LaporanLampiran $lampiran,
        public string $tempPath,
        public ?array $cropData = null,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        // Decode + resize foto resolusi tinggi bisa melampaui memory_limit default 128M
        @ini_set('memory_limit', '512M');

        $files = app(FileStorageService::class);
        $current = $this->lampiran->fresh();
        if (! $current || $current->file_status === 'completed') {
            $files->deleteTemp($this->tempPath);

            return;
        }
        $this->lampiran = $current;
        $oldPath = $current->file_path;
        $stored = null;
        $this->lampiran->update(['file_status' => 'processing']);

        if (! Storage::disk()->exists($this->tempPath)) {
            $this->lampiran->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk()->get($this->tempPath);
            $originalName = $this->lampiran->file_name;
            $extension = (new File(app(FilePathService::class)->localPath($this->tempPath)))->extension() ?? 'bin';
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);

            // Store original filename without extension for later use
            $baseFileName = pathinfo(basename(str_replace('\\', '/', $originalName)), PATHINFO_FILENAME);

            // Process image: crop (if needed) and convert to WebP
            if ($isImage) {
                $fileContent = $this->processImage($fileContent);
                $extension = 'webp';
            }

            // Generate unique filename
            $stored = $files->storeProcessed('laporan-lampiran/harian', $fileContent, $baseFileName.'.'.$extension, $extension);
            if (! $this->lampiran->fresh()) {
                $files->deleteFiles([$stored['path']]);
                $files->deleteTemp($this->tempPath);

                return;
            }

            // Update file_name to reflect WebP conversion for proper Word generation
            $finalFileName = $stored['name'];

            if (! DB::transaction(fn () => $this->lampiran->update([
                'file_path' => $stored['path'],
                'file_name' => $finalFileName,
                'file_size' => $stored['size'],
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]))) {
                throw new \RuntimeException('Gagal memperbarui metadata lampiran.');
            }
        } catch (\Throwable $e) {
            $files->deleteFiles([$stored['path'] ?? null]);
            $this->lampiran->fresh()?->update([
                'file_status' => 'failed',
                'file_error' => 'Lampiran gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }

        $files->deleteFiles([$oldPath]);
        $files->deleteTemp($this->tempPath);
    }

    /**
     * Process image: apply crop (if crop data exists) and convert to WebP format with compression.
     * This ensures optimal file size while maintaining quality for Word document generation.
     */
    private function processImage(string $fileContent): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($fileContent);

        // Apply crop if crop data is provided
        if ($this->cropData) {
            $x = (int) ($this->cropData['x'] ?? 0);
            $y = (int) ($this->cropData['y'] ?? 0);
            $width = (int) ($this->cropData['width'] ?? $image->width());
            $height = (int) ($this->cropData['height'] ?? $image->height());

            $image->crop($width, $height, $x, $y);
        }

        // Kompresi ala WhatsApp: downscale sisi terpanjang ke maks 1600px.
        // scaleDown hanya mengecilkan bila melebihi, rasio tetap terjaga.
        $image->scaleDown(width: 1600, height: 1600);

        // Convert to WebP format with quality compression
        // Quality 85 provides excellent balance between file size and visual quality
        // WebP is fully supported by PhpOffice\PhpWord for Word document generation
        $quality = 85;

        return $image->toWebp($quality);
    }

    public function failed(\Throwable $exception): void
    {
        $current = $this->lampiran->fresh();
        if ($current && $current->file_status !== 'completed') {
            $current->update([
                'file_status' => 'failed',
                'file_error' => 'Lampiran gagal diproses. Silakan unggah ulang.',
            ]);
        }

        app(FileStorageService::class)->deleteTemp($this->tempPath);
    }
}
