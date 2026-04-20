<?php

namespace App\Jobs;

use App\Models\LaporanLampiran;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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

        $this->lampiran->update(['file_status' => 'processing']);

        if (!Storage::disk('local')->exists($this->tempPath)) {
            $this->lampiran->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);
            return;
        }

        try {
            $fileContent = Storage::disk('local')->get($this->tempPath);
            $originalName = $this->lampiran->file_name;
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);

            // Store original filename without extension for later use
            $baseFileName = pathinfo($originalName, PATHINFO_FILENAME);

            // Process image: crop (if needed) and convert to WebP
            if ($isImage) {
                $fileContent = $this->processImage($fileContent, $extension);
                $extension = 'webp';
            }

            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $destinationPath = 'laporan-lampiran/harian/' . $fileName;

            Storage::disk('local')->put($destinationPath, $fileContent);

            // Update file_name to reflect WebP conversion for proper Word generation
            $finalFileName = $isImage ? $baseFileName . '.webp' : $originalName;

            $this->lampiran->update([
                'file_path' => $destinationPath,
                'file_name' => $finalFileName,
                'file_size' => strlen($fileContent),
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);

            Storage::disk('local')->delete($this->tempPath);
        } catch (\Exception $e) {
            $this->lampiran->update([
                'file_status' => 'failed',
                'file_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process image: apply crop (if crop data exists) and convert to WebP format with compression.
     * This ensures optimal file size while maintaining quality for Word document generation.
     */
    private function processImage(string $fileContent, string $extension): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($fileContent);

        // Apply crop if crop data is provided
        if ($this->cropData) {
            $x = (int) ($this->cropData['x'] ?? 0);
            $y = (int) ($this->cropData['y'] ?? 0);
            $width = (int) ($this->cropData['width'] ?? $image->width());
            $height = (int) ($this->cropData['height'] ?? $image->height());

            $image->crop($width, $height, $x, $y);
        }

        // Convert to WebP format with quality compression
        // Quality 85 provides excellent balance between file size and visual quality
        // WebP is fully supported by PhpOffice\PhpWord for Word document generation
        $quality = 85;
        
        return $image->toWebp($quality);
    }

    public function failed(\Throwable $exception): void
    {
        $this->lampiran->update([
            'file_status' => 'failed',
            'file_error' => $exception->getMessage(),
        ]);

        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
