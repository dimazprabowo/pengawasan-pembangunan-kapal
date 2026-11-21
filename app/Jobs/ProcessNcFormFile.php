<?php

namespace App\Jobs;

use App\Models\LaporanNcForm;
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
 * Pindahkan scan Form NC bertanda tangan (PDF/gambar) dari temp ke folder final.
 * File disimpan apa adanya (dokumen resmi — tidak dikompres/di-crop).
 */
class ProcessNcFormFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public LaporanNcForm $form,
        public string $tempPath,
        public ?string $oldPath = null,
    ) {}

    public function handle(QueueStatusService $queueStatusService): void
    {
        $queueStatusService->markWorkerActive();

        $files = app(FileStorageService::class);
        $current = $this->form->fresh();
        if (! $current || $current->file_status === 'completed') {
            $files->deleteTemp($this->tempPath);

            return;
        }
        $this->form = $current;
        $stored = null;
        $this->form->update(['file_status' => 'processing']);

        if (! Storage::disk()->exists($this->tempPath)) {
            $this->form->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $extension = match (Storage::disk()->mimeType($this->tempPath)) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                default => throw new \RuntimeException('Tipe scan form NC tidak didukung.'),
            };
            $stored = $files->copyFromTemp($this->tempPath, 'laporan-nc/form', (string) $this->form->file_name, $extension);
            if (! $this->form->fresh()) {
                $files->deleteFiles([$stored['path']]);
                $files->deleteTemp($this->tempPath);

                return;
            }

            if (! DB::transaction(fn () => $this->form->update([
                'file_path' => $stored['path'],
                'file_name' => $stored['name'],
                'file_size' => $stored['size'],
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]))) {
                throw new \RuntimeException('Gagal memperbarui metadata scan form NC.');
            }

        } catch (\Throwable $e) {
            $files->deleteFiles([$stored['path'] ?? null]);
            $this->form->fresh()?->update([
                'file_status' => 'failed',
                'file_error' => 'Scan form NC gagal diproses. Silakan unggah ulang.',
            ]);
            throw $e;
        }

        // Upload ulang: file lama dihapus setelah file baru berhasil dipindah
        $files->deleteFiles([$this->oldPath]);
        $files->deleteTemp($this->tempPath);
    }

    public function failed(\Throwable $exception): void
    {
        $current = $this->form->fresh();
        if ($current && $current->file_status !== 'completed') {
            $current->update([
                'file_status' => 'failed',
                'file_error' => 'Scan form NC gagal diproses. Silakan unggah ulang.',
            ]);
        }

        app(FileStorageService::class)->deleteTemp($this->tempPath);
    }
}
