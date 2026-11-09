<?php

namespace App\Livewire\Traits;

use App\Models\LaporanHarian;
use App\Models\LaporanNcFoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Foto bukti temuan NC pada halaman laporan harian (Edit & Show): thumbnail
 * lazy (URL route, bukan base64), status processing, preview & download.
 * Komponen wajib punya properti publik LaporanHarian $laporan dengan relasi ncs.foto.
 */
trait HasNcFotoPreview
{
    public bool $showNcPreviewModal = false;

    public ?int $previewNcFotoId = null;

    /**
     * @return Collection<int, LaporanNcFoto>
     */
    private function ncFotos(): Collection
    {
        $this->laporan->loadMissing('ncs.foto');

        return $this->laporan->ncs->pluck('foto')->filter()->values();
    }

    /**
     * URL foto bukti NC selesai (id => route file) — dimuat lazy dari Alpine.
     *
     * @return array<int, string>
     */
    public function ncFotoImageUrls(): array
    {
        if (! Gate::allows('lampiranPreview', LaporanHarian::class)) {
            return [];
        }

        return $this->ncFotos()
            ->filter(fn (LaporanNcFoto $f) => $f->hasFile() && $f->isFileCompleted() && $f->isImage())
            ->mapWithKeys(fn (LaporanNcFoto $f) => [$f->id => route('laporan-nc-foto.file', $f)])
            ->all();
    }

    public function hasProcessingNcFoto(): bool
    {
        return $this->ncFotos()->contains(fn (LaporanNcFoto $f) => $f->isFileProcessing());
    }

    public function openNcFotoPreview(int $fotoId): void
    {
        $foto = $this->ncFotos()->firstWhere('id', $fotoId);

        if (! $foto || ! $foto->hasFile() || ! $foto->isFileCompleted()) {
            $this->notifyWarning('Foto bukti NC tidak tersedia.');

            return;
        }

        $this->previewNcFotoId = $fotoId;
        $this->showNcPreviewModal = true;
    }

    public function closeNcPreviewModal(): void
    {
        $this->showNcPreviewModal = false;
        $this->previewNcFotoId = null;
    }

    public function getPreviewNcFotoProperty(): ?LaporanNcFoto
    {
        return $this->previewNcFotoId ? $this->ncFotos()->firstWhere('id', $this->previewNcFotoId) : null;
    }

    public function getPreviewNcFotoImageUrlProperty(): ?string
    {
        return $this->previewNcFoto?->isPreviewable()
            ? route('laporan-nc-foto.file', $this->previewNcFoto)
            : null;
    }

    public function downloadNcFoto(int $fotoId)
    {
        $this->authorize('lampiranDownload', LaporanHarian::class);

        $foto = $this->ncFotos()->firstWhere('id', $fotoId);

        if (! $foto || ! $foto->file_path || ! Storage::disk('local')->exists($foto->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return null;
        }

        return Storage::disk('local')->download($foto->file_path, $foto->file_name);
    }
}
