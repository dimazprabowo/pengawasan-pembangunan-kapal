<?php

namespace App\Livewire\LaporanHarian;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Models\DataKapal;
use App\Models\Kelembaban;
use App\Models\LaporanHarian;
use App\Services\LaporanHarianService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Edit Laporan Harian'])]
class LaporanHarianEdit extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithFileUploads;

    public LaporanHarian $laporan;

    public DataKapal $dataKapal;

    public ?int $data_kapal_id = null;

    public string $tanggal_laporan = '';

    public ?float $suhu = null;

    public ?int $cuaca_pagi_id = null;

    public ?int $kelembaban_pagi_id = null;

    public ?int $cuaca_siang_id = null;

    public ?int $kelembaban_siang_id = null;

    public ?int $cuaca_sore_id = null;

    public ?int $kelembaban_sore_id = null;

    public array $personel = [];

    public array $peralatan = [];

    public array $consumable = [];

    public array $aktivitas = [];

    // Foto baru per uraian aktivitas - struktur: [row_key => ['file' => null, 'cropData' => null, 'is_cropped' => false]]
    public array $foto = [];

    // Preferensi autosave draft per user (persisten via cache)
    public bool $autoSave = true;

    // Delete lampiran (foto tersimpan) confirmation
    public bool $showDeleteLampiranModal = false;

    public ?int $deletingLampiranId = null;

    // Delete personel confirmation
    public bool $showDeletePersonelModal = false;

    public ?int $deletingPersonelIndex = null;

    // Delete peralatan confirmation
    public bool $showDeletePeralatanModal = false;

    public ?int $deletingPeralatanIndex = null;

    // Delete consumable confirmation
    public bool $showDeleteConsumableModal = false;

    public ?int $deletingConsumableIndex = null;

    // Delete aktivitas confirmation
    public bool $showDeleteAktivitasModal = false;

    public ?int $deletingAktivitasIndex = null;

    // Image cropper modal
    public bool $showCropperModal = false;

    public ?string $croppingRowKey = null;

    public ?string $croppingImageUrl = null;

    public array $cropData = [];

    // Lampiran preview modal
    public bool $showPreviewModal = false;

    public ?int $previewLampiranId = null;

    public function mount(DataKapal $dataKapal, LaporanHarian $laporanHarian): void
    {
        $this->authorize('update', $laporanHarian);
        $this->authorizeDataKapalAccess($dataKapal);
        abort_unless($laporanHarian->data_kapal_id === $dataKapal->id, 404);

        $this->dataKapal = $dataKapal;
        $this->laporan = $laporanHarian;
        $this->data_kapal_id = $laporanHarian->data_kapal_id;
        $this->autoSave = Cache::get('lh_draft_autosave:'.auth()->id(), true);
        $this->tanggal_laporan = $laporanHarian->tanggal_laporan->format('Y-m-d');

        $this->suhu = $laporanHarian->suhu;
        $this->cuaca_pagi_id = $laporanHarian->cuaca_pagi_id;
        $this->kelembaban_pagi_id = $laporanHarian->kelembaban_pagi_id;
        $this->cuaca_siang_id = $laporanHarian->cuaca_siang_id;
        $this->kelembaban_siang_id = $laporanHarian->kelembaban_siang_id;
        $this->cuaca_sore_id = $laporanHarian->cuaca_sore_id;
        $this->kelembaban_sore_id = $laporanHarian->kelembaban_sore_id;

        // Load existing dynamic data
        $this->personel = $laporanHarian->personel->map(fn ($p) => [
            'id' => $p->id,
            'jabatan' => $p->jabatan,
            'status' => $p->status,
            'keterangan' => $p->keterangan ?? '',
        ])->toArray();

        $this->peralatan = $laporanHarian->peralatan->map(fn ($p) => [
            'id' => $p->id,
            'jenis' => $p->jenis,
            'jumlah' => $p->jumlah,
            'keterangan' => $p->keterangan ?? '',
        ])->toArray();

        $this->consumable = $laporanHarian->consumable->map(fn ($c) => [
            'id' => $c->id,
            'jenis' => $c->jenis,
            'jumlah' => $c->jumlah,
            'keterangan' => $c->keterangan ?? '',
        ])->toArray();

        $this->aktivitas = app(LaporanHarianService::class)->aktivitasRowsFrom($laporanHarian);
        foreach ($this->aktivitas as $row) {
            $this->foto[$row['row_key']] = $this->emptyFoto();
        }
    }

    private function emptyFoto(): array
    {
        return ['file' => null, 'preview' => null, 'cropData' => null, 'is_cropped' => false];
    }

    /**
     * Foto tersimpan per id aktivitas (untuk tab Lampiran).
     */
    private function lampiranByAktivitas(): \Illuminate\Support\Collection
    {
        return $this->laporan->lampiran->keyBy('laporan_aktivitas_id');
    }

    public function toggleAutoSave(): void
    {
        $this->autoSave = ! $this->autoSave;
        Cache::forever('lh_draft_autosave:'.auth()->id(), $this->autoSave);
    }

    public function rules(): array
    {
        $rules = [
            'data_kapal_id' => 'required|exists:data_kapal,id',
            'tanggal_laporan' => [
                'required',
                'date',
                'before_or_equal:today',
                // whereDate (bukan Rule::unique) agar perbandingan tanggal konsisten lintas driver DB
                function ($attribute, $value, $fail) {
                    if ($value && LaporanHarian::where('data_kapal_id', $this->data_kapal_id)
                        ->where('id', '!=', $this->laporan->id)
                        ->whereDate('tanggal_laporan', $value)
                        ->exists()) {
                        $fail('Tanggal ini sudah memiliki laporan lain untuk kapal ini.');
                    }
                },
            ],
            'foto.*.file' => [
                ...file_upload_validation_rule('foto_kapal'),
                // Foto wajib punya uraian aktivitas (keterangan foto = uraian aktivitas)
                function ($attribute, $value, $fail) {
                    $row = collect($this->aktivitas)->firstWhere('row_key', explode('.', $attribute)[1]);
                    if ($value && trim((string) ($row['aktivitas'] ?? '')) === '') {
                        $fail('Uraian aktivitas untuk foto ini wajib diisi terlebih dahulu di tab Aktivitas.');
                    }
                },
            ],
            'foto.*.preview' => 'nullable|image|max:5120',
        ];

        $rules['suhu'] = 'nullable|numeric|min:-50|max:100';
        $rules['cuaca_pagi_id'] = 'nullable|exists:cuaca,id';
        $rules['kelembaban_pagi_id'] = 'nullable|exists:kelembaban,id';
        $rules['cuaca_siang_id'] = 'nullable|exists:cuaca,id';
        $rules['kelembaban_siang_id'] = 'nullable|exists:kelembaban,id';
        $rules['cuaca_sore_id'] = 'nullable|exists:cuaca,id';
        $rules['kelembaban_sore_id'] = 'nullable|exists:kelembaban,id';
        $rules['personel'] = 'nullable|array';
        $rules['personel.*.jabatan'] = 'nullable|string|max:255';
        $rules['personel.*.status'] = 'nullable|string|max:255';
        $rules['personel.*.keterangan'] = 'nullable|string|max:1000';
        $rules['peralatan'] = 'nullable|array';
        $rules['peralatan.*.jenis'] = 'nullable|string|max:255';
        $rules['peralatan.*.jumlah'] = 'nullable|integer|min:1';
        $rules['peralatan.*.keterangan'] = 'nullable|string|max:1000';
        $rules['consumable'] = 'nullable|array';
        $rules['consumable.*.jenis'] = 'nullable|string|max:255';
        $rules['consumable.*.jumlah'] = 'nullable|integer|min:1';
        $rules['consumable.*.keterangan'] = 'nullable|string|max:1000';
        $rules['aktivitas'] = 'nullable|array';
        $rules['aktivitas.*.work_group_id'] = 'nullable|integer';
        $rules['aktivitas.*.aktivitas'] = 'nullable|string';
        // Aktivitas yang sudah memiliki foto tersimpan tidak boleh dikosongkan uraiannya
        $lampiranByAktivitas = $this->lampiranByAktivitas();
        foreach ($this->aktivitas as $i => $row) {
            if (! empty($row['id']) && $lampiranByAktivitas->has($row['id'])) {
                $rules["aktivitas.{$i}.aktivitas"] = 'required|string';
            }
        }
        $rules['aktivitas.*.pic'] = 'nullable|string|max:255';

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'tanggal_laporan.before_or_equal' => 'Tanggal laporan tidak boleh melebihi hari ini.',
            'aktivitas.*.aktivitas.required' => 'Uraian aktivitas wajib diisi karena aktivitas ini memiliki foto lampiran.',
        ];
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'data_kapal_id' => 'data kapal',
            'tanggal_laporan' => 'tanggal laporan',
            'foto.*.file' => 'foto lampiran',
            'foto.*.preview' => 'pratinjau foto',
            'aktivitas.*.aktivitas' => 'uraian aktivitas',
        ];

        $attributes['suhu'] = 'suhu';
        $attributes['cuaca_pagi_id'] = 'cuaca pagi';
        $attributes['kelembaban_pagi_id'] = 'kelembaban pagi';
        $attributes['cuaca_siang_id'] = 'cuaca siang';
        $attributes['kelembaban_siang_id'] = 'kelembaban siang';
        $attributes['cuaca_sore_id'] = 'cuaca sore';
        $attributes['kelembaban_sore_id'] = 'kelembaban sore';

        return $attributes;
    }

    public function confirmDeleteLampiran(int $lampiranId): void
    {
        $this->deletingLampiranId = $lampiranId;
        $this->showDeleteLampiranModal = true;
    }

    public function deleteLampiran(LaporanHarianService $service): void
    {
        $this->authorize('update', $this->laporan);

        try {
            $lampiran = $this->laporan->lampiran()->findOrFail($this->deletingLampiranId);
            $service->deleteLampiran($lampiran);
            $this->laporan->refresh();
            $this->showDeleteLampiranModal = false;
            $this->deletingLampiranId = null;
            $this->notifySuccess('Foto lampiran berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('LaporanHarianEdit: gagal menghapus lampiran', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function removeFoto(string $rowKey): void
    {
        $this->foto[$rowKey] = $this->emptyFoto();
    }

    /**
     * File foto baru menggantikan yang lama → pratinjau crop & data crop lama tidak berlaku lagi.
     */
    public function updatedFoto($value, ?string $key = null): void
    {
        if (! $key || ! str_ends_with($key, '.file')) {
            return;
        }

        $rowKey = explode('.', $key)[0];
        if (isset($this->foto[$rowKey])) {
            $this->foto[$rowKey]['preview'] = null;
            $this->foto[$rowKey]['cropData'] = null;
            $this->foto[$rowKey]['is_cropped'] = false;
        }
    }

    public function openCropper(string $rowKey): void
    {
        $file = $this->foto[$rowKey]['file'] ?? null;
        if (! $file || ! is_object($file)) {
            $this->notifyWarning('File tidak valid, silakan upload ulang.');

            return;
        }

        // Only allow crop for images
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $this->notifyWarning('Crop hanya tersedia untuk file gambar (JPG, PNG, WEBP).');

            return;
        }

        $this->croppingRowKey = $rowKey;
        $this->croppingImageUrl = $file->temporaryUrl();
        // Load existing crop data if available to persist last crop settings
        $this->cropData = $this->foto[$rowKey]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function closeCropper(): void
    {
        $this->showCropperModal = false;
        $this->croppingRowKey = null;
        $this->croppingImageUrl = null;
        // Don't reset cropData here to preserve it for re-opening
    }

    public function saveCrop(): void
    {
        if ($this->croppingRowKey !== null && ! empty($this->cropData)) {
            // Save crop data to the foto item to persist settings
            $this->foto[$this->croppingRowKey]['cropData'] = $this->cropData;
            $this->foto[$this->croppingRowKey]['is_cropped'] = true;
            $this->notifySuccess('Crop berhasil disimpan.');
        }
        $this->closeCropper();
    }

    public function refreshLampiranStatus(): void
    {
        $this->laporan->load('lampiran');
        $this->dispatch('lh-reload-thumbs');
    }

    /**
     * Data URL foto lampiran selesai (id => dataUrl) — dimuat lazy dari Alpine
     * agar file tidak dibaca ulang pada tiap render Livewire.
     *
     * @return array<int, string>
     */
    public function lampiranImageUrls(): array
    {
        if (! \Illuminate\Support\Facades\Gate::allows('lampiranPreview', LaporanHarian::class)) {
            return [];
        }

        return $this->laporan->lampiran
            ->filter(fn ($l) => $l->hasFile() && $l->isFileCompleted() && $l->isImage())
            ->mapWithKeys(fn ($l) => [$l->id => $this->getLampiranImageDataUrl($l->id)])
            ->all();
    }

    public function hasProcessingLampiran(): bool
    {
        return $this->laporan->lampiran->contains(function ($lampiran) {
            return $lampiran->isFileProcessing();
        });
    }

    public function save(LaporanHarianService $service): void
    {
        $this->authorize('update', $this->laporan);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'data_kapal_id' => $this->data_kapal_id,
                'tanggal_laporan' => $this->tanggal_laporan,
            ];

            $data['suhu'] = $this->suhu;
            $data['cuaca_pagi_id'] = $this->cuaca_pagi_id;
            $data['kelembaban_pagi_id'] = $this->kelembaban_pagi_id;
            $data['cuaca_siang_id'] = $this->cuaca_siang_id;
            $data['kelembaban_siang_id'] = $this->kelembaban_siang_id;
            $data['cuaca_sore_id'] = $this->cuaca_sore_id;
            $data['kelembaban_sore_id'] = $this->kelembaban_sore_id;

            $service->update($this->laporan, $data);

            // Update dynamic inputs

            // Sync personel - allow partial data (any field filled)
            $this->laporan->personel()->delete();
            foreach ($this->personel as $personelData) {
                $hasData = ! empty($personelData['jabatan']) ||
                          ! empty($personelData['status']) ||
                          ! empty($personelData['keterangan']);

                if ($hasData) {
                    $this->laporan->personel()->create([
                        'jabatan' => $personelData['jabatan'] ?: null,
                        'status' => $personelData['status'] ?: null,
                        'keterangan' => $personelData['keterangan'] ?: null,
                    ]);
                }
            }

            // Sync peralatan - allow partial data (any field filled)
            $this->laporan->peralatan()->delete();
            foreach ($this->peralatan as $peralatanData) {
                $hasData = ! empty($peralatanData['jenis']) ||
                          ! empty($peralatanData['jumlah']) ||
                          ! empty($peralatanData['keterangan']);

                if ($hasData) {
                    $this->laporan->peralatan()->create([
                        'jenis' => $peralatanData['jenis'] ?: null,
                        'jumlah' => $peralatanData['jumlah'] ?: null,
                        'keterangan' => $peralatanData['keterangan'] ?: null,
                    ]);
                }
            }

            // Sync consumable - allow partial data (any field filled)
            $this->laporan->consumable()->delete();
            foreach ($this->consumable as $consumableData) {
                $hasData = ! empty($consumableData['jenis']) ||
                          ! empty($consumableData['jumlah']) ||
                          ! empty($consumableData['keterangan']);

                if ($hasData) {
                    $this->laporan->consumable()->create([
                        'jenis' => $consumableData['jenis'] ?: null,
                        'jumlah' => $consumableData['jumlah'] ?: null,
                        'keterangan' => $consumableData['keterangan'] ?: null,
                    ]);
                }
            }

            // Uraian aktivitas per kategori + foto lampirannya (update/insert/hapus by id)
            $fotoCount = $service->syncAktivitas($this->laporan, $this->aktivitas, $this->foto);

            $message = 'Laporan Harian berhasil diupdate!';
            if ($fotoCount > 0) {
                $message .= ' Foto lampiran baru sedang diproses di background.';
            }

            $this->notifySuccess($message);

            $this->redirect(route('laporan-harian.show', [$this->dataKapal, $this->laporan]), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengupdate laporan ini.');
        } catch (\Exception $e) {
            Log::error('LaporanHarianEdit: gagal menyimpan', ['laporan_id' => $this->laporan->id, 'error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function cancel()
    {
        // Beri tahu Alpine untuk menghapus draft lokal sebelum navigate
        $this->dispatch('lh-clear-draft');

        return $this->redirect(route('laporan-harian.index', $this->dataKapal), navigate: true);
    }

    public function openLampiranPreview(int $lampiranId): void
    {
        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (! $lampiran || ! $lampiran->hasFile() || ! $lampiran->isFileCompleted()) {
            $this->notifyWarning('File lampiran tidak tersedia.');

            return;
        }

        $this->previewLampiranId = $lampiranId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewLampiranId = null;
    }

    public function getPreviewLampiranProperty()
    {
        if (! $this->previewLampiranId) {
            return null;
        }

        return $this->laporan->lampiran->find($this->previewLampiranId);
    }

    public function previewLampiran(int $lampiranId)
    {
        $this->authorize('view', $this->laporan);

        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (! $lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
            $this->notifyError('Lampiran tidak ditemukan.');

            return;
        }

        if (! $lampiran->file_path || ! Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response(Storage::disk('local')->get($lampiran->file_path))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="'.$lampiran->file_name.'"');
    }

    public function getLampiranImageDataUrl(int $lampiranId): ?string
    {
        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (! $lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
            return null;
        }

        if (! $lampiran->file_path || ! Storage::disk('local')->exists($lampiran->file_path)) {
            return null;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        if (! isset($mimeTypes[$extension])) {
            return null;
        }

        $fileContent = Storage::disk('local')->get($lampiran->file_path);
        $base64 = base64_encode($fileContent);
        $mime = $mimeTypes[$extension];

        return "data:{$mime};base64,{$base64}";
    }

    public function getPreviewLampiranImageUrlProperty(): ?string
    {
        if (! $this->previewLampiran) {
            return null;
        }

        return $this->getLampiranImageDataUrl($this->previewLampiran->id);
    }

    public function downloadLampiran(int $lampiranId)
    {
        $this->authorize('view', $this->laporan);

        $lampiran = $this->laporan->lampiran->find($lampiranId);

        if (! $lampiran || $lampiran->laporan_harian_id !== $this->laporan->id) {
            $this->notifyError('Lampiran tidak ditemukan.');

            return;
        }

        if (! $lampiran->file_path || ! Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');

            return;
        }

        return Storage::disk('local')->download($lampiran->file_path, $lampiran->file_name);
    }

    // Dynamic input management methods
    public function addPersonel(): void
    {
        $this->personel[] = [
            'jabatan' => '',
            'status' => '',
            'keterangan' => '',
        ];
    }

    public function confirmRemovePersonel(int $index): void
    {
        $this->deletingPersonelIndex = $index;
        $this->showDeletePersonelModal = true;
    }

    public function removePersonelConfirmed(): void
    {
        if ($this->deletingPersonelIndex !== null) {
            $this->removePersonel($this->deletingPersonelIndex);
        }
        $this->showDeletePersonelModal = false;
        $this->deletingPersonelIndex = null;
        $this->notifySuccess('Personel berhasil dihapus');
    }

    public function removePersonel(int $index): void
    {
        if (isset($this->personel[$index])) {
            unset($this->personel[$index]);
            $this->personel = array_values($this->personel);
        }
    }

    public function addPeralatan(): void
    {
        $this->peralatan[] = [
            'jenis' => '',
            'jumlah' => '',
            'keterangan' => '',
        ];
    }

    public function confirmRemovePeralatan(int $index): void
    {
        $this->deletingPeralatanIndex = $index;
        $this->showDeletePeralatanModal = true;
    }

    public function removePeralatanConfirmed(): void
    {
        if ($this->deletingPeralatanIndex !== null) {
            $this->removePeralatan($this->deletingPeralatanIndex);
        }
        $this->showDeletePeralatanModal = false;
        $this->deletingPeralatanIndex = null;
        $this->notifySuccess('Peralatan berhasil dihapus');
    }

    public function removePeralatan(int $index): void
    {
        if (isset($this->peralatan[$index])) {
            unset($this->peralatan[$index]);
            $this->peralatan = array_values($this->peralatan);
        }
    }

    public function addConsumable(): void
    {
        $this->consumable[] = [
            'jenis' => '',
            'jumlah' => '',
            'keterangan' => '',
        ];
    }

    public function confirmRemoveConsumable(int $index): void
    {
        $this->deletingConsumableIndex = $index;
        $this->showDeleteConsumableModal = true;
    }

    public function removeConsumableConfirmed(): void
    {
        if ($this->deletingConsumableIndex !== null) {
            $this->removeConsumable($this->deletingConsumableIndex);
        }
        $this->showDeleteConsumableModal = false;
        $this->deletingConsumableIndex = null;
        $this->notifySuccess('Consumable berhasil dihapus');
    }

    public function removeConsumable(int $index): void
    {
        if (isset($this->consumable[$index])) {
            unset($this->consumable[$index]);
            $this->consumable = array_values($this->consumable);
        }
    }

    public function addAktivitas(?int $workGroupId = null): void
    {
        $row = app(LaporanHarianService::class)->newAktivitasRow($workGroupId);
        $this->aktivitas[] = $row;
        $this->foto[$row['row_key']] = $this->emptyFoto();
    }

    public function getDeletingAktivitasHasFotoProperty(): bool
    {
        $row = $this->aktivitas[$this->deletingAktivitasIndex] ?? null;

        return $row && (! empty($this->foto[$row['row_key']]['file'])
            || ($row['id'] && $this->lampiranByAktivitas()->has($row['id'])));
    }

    public function confirmRemoveAktivitas(int $index): void
    {
        $this->deletingAktivitasIndex = $index;
        $this->showDeleteAktivitasModal = true;
    }

    public function removeAktivitasConfirmed(): void
    {
        if ($this->deletingAktivitasIndex !== null) {
            $this->removeAktivitas($this->deletingAktivitasIndex);
        }
        $this->showDeleteAktivitasModal = false;
        $this->deletingAktivitasIndex = null;
        $this->notifySuccess('Aktivitas berhasil dihapus');
    }

    public function removeAktivitas(int $index): void
    {
        if (isset($this->aktivitas[$index])) {
            unset($this->foto[$this->aktivitas[$index]['row_key'] ?? '']);
            unset($this->aktivitas[$index]);
            $this->aktivitas = array_values($this->aktivitas);
        }
    }

    public function render(QueueStatusService $queueStatusService, LaporanHarianService $service)
    {
        $this->laporan->loadMissing(['user', 'updatedBy']);
        $cuacaList = Cuaca::active()->orderBy('nama')->get();
        $kelembabanList = Kelembaban::active()->orderBy('nama')->get();

        return view('livewire.laporan-harian.laporan-harian-edit', [
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
            'kategoriGroups' => $service->groupRowsByKategori($this->aktivitas, $service->kategoriOptions($this->data_kapal_id)),
            'lampiranByAktivitas' => $this->lampiranByAktivitas(),
        ]);
    }
}
