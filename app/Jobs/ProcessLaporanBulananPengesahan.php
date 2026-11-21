<?php

namespace App\Jobs;

use App\Models\LaporanBulanan;
use App\Services\FileStorageService;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan Lembar Pengesahan bertanda tangan basah (PDF) dari temp ke
 * folder final. File disimpan apa adanya (dokumen resmi). Saat upload
 * selesai dan rilis PDF sudah ada, rilis dijadwalkan ulang agar halaman
 * pengesahan memakai file baru.
 */
class ProcessLaporanBulananPengesahan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public LaporanBulanan $laporan,
        public string $tempPath,
        public ?string $oldPath = null,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        $files = app(FileStorageService::class);
        $current = $this->laporan->fresh();
        if (! $current || $current->pengesahan_status === 'completed') {
            $files->deleteTemp($this->tempPath);

            return;
        }
        $this->laporan = $current;
        $stored = null;
        $this->laporan->update(['pengesahan_status' => 'processing']);

        if (! Storage::disk()->exists($this->tempPath)) {
            $this->laporan->update([
                'pengesahan_status' => 'failed',
                'pengesahan_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            if (Storage::disk()->mimeType($this->tempPath) !== 'application/pdf') {
                throw new \RuntimeException('Lembar pengesahan wajib berformat PDF.');
            }
            $stored = $files->copyFromTemp($this->tempPath, 'laporan/pengesahan', (string) $this->laporan->pengesahan_name, 'pdf');
            if (! $this->laporan->fresh()) {
                $files->deleteFiles([$stored['path']]);
                $files->deleteTemp($this->tempPath);

                return;
            }

            if (! DB::transaction(fn () => $this->laporan->update([
                'pengesahan_path' => $stored['path'],
                'pengesahan_name' => $stored['name'],
                'pengesahan_size' => $stored['size'],
                'pengesahan_status' => 'completed',
                'pengesahan_processed_at' => now(),
                'pengesahan_error' => null,
            ]))) {
                throw new \RuntimeException('Gagal memperbarui metadata lembar pengesahan.');
            }

        } catch (\Throwable $e) {
            $files->deleteFiles([$stored['path'] ?? null]);
            $this->laporan->fresh()?->update([
                'pengesahan_status' => 'failed',
                'pengesahan_error' => 'Lembar pengesahan gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }

        // Upload ulang: file lama dihapus setelah file baru berhasil dipindah
        $files->deleteFiles([$this->oldPath]);
        $files->deleteTemp($this->tempPath);

        // PDF rilis yang sudah ada diganti agar halaman pengesahan
        // memakai file basah yang baru diupload.
        if ($this->laporan->fresh()?->pdf_status === 'completed') {
            ReleaseLaporanBulananPdfJob::dispatch($this->laporan);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $current = $this->laporan->fresh();
        if ($current && $current->pengesahan_status !== 'completed') {
            $current->update([
                'pengesahan_status' => 'failed',
                'pengesahan_error' => 'Lembar pengesahan gagal diproses. Silakan unggah ulang.',
            ]);
        }

        app(FileStorageService::class)->deleteTemp($this->tempPath);
    }
}
