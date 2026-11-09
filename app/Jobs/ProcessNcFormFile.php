<?php

namespace App\Jobs;

use App\Models\LaporanNcForm;
use App\Services\QueueStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        $this->form->update(['file_status' => 'processing']);

        if (! Storage::disk('local')->exists($this->tempPath)) {
            $this->form->update([
                'file_status' => 'failed',
                'file_error' => 'File temporary tidak ditemukan',
            ]);

            return;
        }

        try {
            $extension = strtolower(pathinfo((string) $this->form->file_name, PATHINFO_EXTENSION));
            $destinationPath = 'laporan-nc/form/'.time().'_'.uniqid().'.'.$extension;

            Storage::disk('local')->move($this->tempPath, $destinationPath);

            $this->form->update([
                'file_path' => $destinationPath,
                'file_size' => Storage::disk('local')->size($destinationPath),
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'file_error' => null,
            ]);

            // Upload ulang: file lama dihapus setelah file baru berhasil dipindah
            if ($this->oldPath && $this->oldPath !== $destinationPath && Storage::disk('local')->exists($this->oldPath)) {
                Storage::disk('local')->delete($this->oldPath);
            }
        } catch (\Exception $e) {
            $this->form->update([
                'file_status' => 'failed',
                'file_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->form->update([
            'file_status' => 'failed',
            'file_error' => $exception->getMessage(),
        ]);

        if (Storage::disk('local')->exists($this->tempPath)) {
            Storage::disk('local')->delete($this->tempPath);
        }
    }
}
