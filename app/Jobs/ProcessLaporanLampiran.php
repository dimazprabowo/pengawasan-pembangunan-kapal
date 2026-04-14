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

            // Process image if it's an image and has crop data
            if ($isImage && $this->cropData) {
                $fileContent = $this->processImageCrop($fileContent, $extension);
            }

            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $destinationPath = 'laporan-lampiran/harian/' . $fileName;

            Storage::disk('local')->put($destinationPath, $fileContent);

            $this->lampiran->update([
                'file_path' => $destinationPath,
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

    private function processImageCrop(string $fileContent, string $extension): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($fileContent);

        if ($this->cropData) {
            $x = $this->cropData['x'] ?? 0;
            $y = $this->cropData['y'] ?? 0;
            $width = $this->cropData['width'] ?? $image->width();
            $height = $this->cropData['height'] ?? $image->height();

            $image->crop($width, $height, $x, $y);
        }

        // Convert to appropriate format and return
        $quality = 90;
        return match ($extension) {
            'jpg', 'jpeg' => $image->encodeByExtension('jpg', quality: $quality),
            'png' => $image->encodeByExtension('png'),
            'gif' => $image->encodeByExtension('gif'),
            'webp' => $image->encodeByExtension('webp', quality: $quality),
            default => $image->encode(),
        };
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
