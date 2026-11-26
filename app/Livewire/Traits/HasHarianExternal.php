<?php

namespace App\Livewire\Traits;

use App\Models\LaporanHarian;
use App\Services\LaporanHarianService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

trait HasHarianExternal
{
    public array $laporanExternal = [];

    public bool $showDeleteExternalModal = false;

    #[Locked]
    public ?string $deletingExternalId = null;

    #[Locked]
    public int $externalDay = 0;

    private function authorizeExternal(): void
    {
        $this->authorize(isset($this->laporan) ? 'update' : 'create', isset($this->laporan) ? $this->laporan : LaporanHarian::class);
        $this->authorizeDataKapalAccess($this->dataKapal);
    }

    public function addExternalReport(int $day = 0): void
    {
        $this->authorizeExternal();
        abort_unless(isset($this->laporan) ? $day === 0 : isset($this->items[$day]), 404);
        $this->laporanExternal[$day][] = ['id' => (string) Str::uuid(), 'judul' => '', 'deskripsi' => '', 'file' => null];
    }

    public function removeExternalFile(int $day, int $index): void
    {
        $this->authorizeExternal();
        if (isset($this->laporanExternal[$day][$index])) {
            $this->laporanExternal[$day][$index]['file'] = null;
        }
    }

    public function confirmDeleteExternal(int $day, string $id): void
    {
        $this->authorizeExternal();
        abort_unless(collect($this->laporanExternal[$day] ?? [])->contains('id', $id), 404);
        $this->externalDay = $day;
        $this->deletingExternalId = $id;
        $this->showDeleteExternalModal = true;
    }

    public function cancelDeleteExternal(): void
    {
        $this->deletingExternalId = null;
        $this->showDeleteExternalModal = false;
    }

    public function deleteExternalReport(): void
    {
        $this->authorizeExternal();
        if ($this->deletingExternalId === null) {
            return;
        }
        if (is_numeric($this->deletingExternalId)) {
            abort_unless(isset($this->laporan), 404);
            app(LaporanHarianService::class)->deleteExternal($this->laporan, (int) $this->deletingExternalId);
        }
        $this->laporanExternal[$this->externalDay] = array_values(array_filter(
            $this->laporanExternal[$this->externalDay] ?? [],
            fn ($row) => (string) $row['id'] !== $this->deletingExternalId,
        ));
        $this->cancelDeleteExternal();
        $this->notifySuccess('Dokumen pendukung dihapus.');
    }

    public function downloadExternalFile(int $day, int $index)
    {
        abort_unless(isset($this->laporan), 404);
        $this->authorize('laporan_lampiran_download');
        $this->authorize('view', $this->laporan);
        $this->authorizeDataKapalAccess($this->dataKapal);
        $id = $this->laporanExternal[$day][$index]['id'] ?? null;
        $external = $this->laporan->laporanExternal()->findOrFail($id);
        abort_unless($external->isFileCompleted() && $external->file_path, 404);

        return Storage::disk('local')->download($external->file_path, $external->file_name);
    }

    protected function loadHarianExternal(): void
    {
        $this->laporanExternal[0] = $this->laporan->laporanExternal()->get()->map(fn ($row) => [
            'id' => $row->id,
            'judul' => $row->judul,
            'deskripsi' => $row->deskripsi,
            'file' => null,
            'file_status' => $row->file_status,
            'file_error' => $row->file_error,
            'existing_file_name' => $row->file_name,
            'existing_file_size' => $row->file_size,
        ])->all();
    }

    public function hasProcessingExternalFiles(): bool
    {
        return collect($this->laporanExternal[0] ?? [])->contains(fn ($row) => in_array($row['file_status'] ?? null, ['pending', 'processing'], true));
    }

    public function refreshExternalFileStatus(): void
    {
        $this->authorizeExternal();
        abort_unless(isset($this->laporan), 404);
        $saved = $this->laporan->laporanExternal()->get()->keyBy('id');
        foreach ($this->laporanExternal[0] ?? [] as $index => $row) {
            $external = $saved->get($row['id']);
            if ($external) {
                $this->laporanExternal[0][$index] = array_replace($row, [
                    'file_status' => $external->file_status,
                    'file_error' => $external->file_error,
                    'existing_file_name' => $external->file_name,
                    'existing_file_size' => $external->file_size,
                ]);
            }
        }
    }

    protected function validateHarianExternal(): void
    {
        $rules = [
            'laporanExternal.*.*.judul' => 'required|string|max:255',
            'laporanExternal.*.*.deskripsi' => 'nullable|string|max:1000',
            'laporanExternal.*.*.file' => file_upload_validation_rule('laporan_external', false),
        ];
        foreach ($this->laporanExternal as $day => $rows) {
            abort_unless(isset($this->laporan) ? $day === 0 : isset($this->items[$day]), 422);
            foreach ($rows as $index => $row) {
                if (! is_numeric($row['id'] ?? null)) {
                    $rules["laporanExternal.{$day}.{$index}.file"] = file_upload_validation_rule('laporan_external', true);
                }
            }
        }
        $this->validate($rules, [], [
            'laporanExternal.*.*.judul' => 'judul dokumen pendukung',
            'laporanExternal.*.*.deskripsi' => 'deskripsi dokumen pendukung',
            'laporanExternal.*.*.file' => 'file dokumen pendukung',
        ]);
    }
}
