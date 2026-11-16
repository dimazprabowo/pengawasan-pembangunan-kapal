<?php

namespace App\Jobs;

use App\Models\LaporanExternal;
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

        if (! $laporanExternal) {
            return;
        }

        $laporanExternal->update(['file_status' => 'processing']);

        if (! Storage::disk('local')->exists($this->tempPath)) {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $fileContent = Storage::disk('local')->get($this->tempPath);
            $originalName = $laporanExternal->file_name;
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($fileContent);
            $isImage = str_starts_with((string) $mime, 'image/');
            if (! $isImage && $mime !== 'application/pdf') {
                throw new \RuntimeException('Tipe dokumen eksternal tidak didukung.');
            }
            $extension = $isImage ? 'webp' : 'pdf';

            $baseFileName = pathinfo((string) $originalName, PATHINFO_FILENAME);

            // Gambar: downscale sisi terpanjang ke maks 1600px lalu konversi WebP
            // (konsisten dengan lampiran NC agar kompatibel export Word)
            if ($isImage) {
                $fileContent = $this->processImage($fileContent);
            }

            $fileName = time().'_'.uniqid().'.'.$extension;
            $destinationPath = 'laporan-external/'.$fileName;

            $pages = $isImage ? [] : app(PdfPageService::class)->render($this->tempPath, 'laporan-external/pages');
            if (! Storage::disk('local')->put($destinationPath, $fileContent)) {
                Storage::disk('local')->delete($pages);
                throw new \RuntimeException('Gagal menyimpan dokumen eksternal.');
            }
            if (! $laporanExternal->fresh()) {
                Storage::disk('local')->delete(array_merge([$destinationPath], $pages));
                Storage::disk('local')->delete($this->tempPath);

                return;
            }
            Storage::disk('local')->delete($laporanExternal->rendered_pages ?? []);

            $laporanExternal->update([
                'file_path' => $destinationPath,
                'rendered_pages' => $pages,
                'file_name' => $baseFileName.'.'.$extension,
                'file_size' => strlen($fileContent),
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);

            Storage::disk('local')->delete($this->tempPath);
        } catch (\Exception $e) {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => 'Dokumen eksternal gagal diproses. Silakan unggah ulang.',
            ]);

            if (Storage::disk('local')->exists($this->tempPath)) {
                Storage::disk('local')->delete($this->tempPath);
            }

            throw $e;
        }
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

        if ($laporanExternal) {
            $laporanExternal->update([
                'file_status' => 'failed',
                'file_error' => 'Dokumen eksternal gagal diproses. Silakan unggah ulang.',
            ]);
        }

        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
