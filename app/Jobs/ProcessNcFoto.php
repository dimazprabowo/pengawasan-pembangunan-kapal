<?php

namespace App\Jobs;

use App\Models\LaporanNcFoto;
use App\Services\PdfPageService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $this->ncFoto->update(['file_status' => 'processing']);

        if (! Storage::disk('local')->exists($this->tempPath)) {
            $this->ncFoto->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk('local')->get($this->tempPath);
            $originalName = $this->ncFoto->file_name;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($fileContent);
            $extension = $mime === 'application/pdf' ? 'pdf' : 'webp';
            $isImage = str_starts_with((string) $mime, 'image/');
            if (! $isImage && $mime !== 'application/pdf') {
                throw new \RuntimeException('Tipe file NC tidak didukung.');
            }

            $baseFileName = pathinfo($originalName, PATHINFO_FILENAME);

            // Gambar: apply crop (bila ada) lalu konversi WebP (konsisten lampiran aktivitas)
            if ($isImage) {
                $fileContent = $this->processImage($fileContent);
                $extension = 'webp';
            }

            $fileName = time().'_'.uniqid().'.'.$extension;
            $destinationPath = 'laporan-nc/foto/'.$fileName;

            $pages = $isImage ? [] : app(PdfPageService::class)->render($this->tempPath);
            if (! Storage::disk('local')->put($destinationPath, $fileContent)) {
                Storage::disk('local')->delete($pages);
                throw new \RuntimeException('Gagal menyimpan file NC.');
            }
            if (! $this->ncFoto->fresh()) {
                Storage::disk('local')->delete(array_merge([$destinationPath], $pages));
                Storage::disk('local')->delete($this->tempPath);

                return;
            }
            Storage::disk('local')->delete($this->ncFoto->rendered_pages ?? []);

            $finalFileName = $baseFileName.'.'.$extension;

            $this->ncFoto->update([
                'file_path' => $destinationPath,
                'rendered_pages' => $pages,
                'file_name' => $finalFileName,
                'file_size' => strlen($fileContent),
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);

            Storage::disk('local')->delete($this->tempPath);
        } catch (\Exception $e) {
            $this->ncFoto->update([
                'file_status' => 'failed',
                'file_error' => 'File NC gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }
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
        $this->ncFoto->update([
            'file_status' => 'failed',
            'file_error' => 'File NC gagal diproses. Silakan unggah ulang.',
        ]);

        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
