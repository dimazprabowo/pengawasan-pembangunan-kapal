<?php

namespace App\Livewire\Laporan;

use App\Enums\LaporanTipe;
use App\Jobs\ProcessLaporanLampiran;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Models\JenisKapal;
use App\Models\Kelembaban;
use App\Models\Laporan;
use App\Services\LaporanService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Edit Laporan'])]
class LaporanEdit extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public Laporan $laporan;

    public ?int $jenis_kapal_id = null;
    public string $judul = '';
    public string $tanggal_laporan = '';
    public ?string $isi = '';
    public ?string $catatan = '';
    public ?float $suhu = null;
    public ?int $cuaca_pagi_id = null;
    public ?int $kelembaban_pagi_id = null;
    public ?int $cuaca_siang_id = null;
    public ?int $kelembaban_siang_id = null;
    public ?int $cuaca_sore_id = null;
    public ?int $kelembaban_sore_id = null;

    // Lampiran baru yang akan ditambahkan
    public array $newLampiran = [];

    // Keterangan untuk existing lampiran yang diedit
    public array $lampiranKeterangan = [];

    // Delete lampiran confirmation
    public bool $showDeleteLampiranModal = false;
    public ?int $deletingLampiranId = null;

    // Image cropper modal
    public bool $showCropperModal = false;
    public ?int $croppingLampiranIndex = null;
    public ?string $croppingImageUrl = null;
    public array $cropData = [];

    // Lampiran preview modal
    public bool $showPreviewModal = false;
    public ?int $previewLampiranId = null;

    public function mount(Laporan $laporan): void
    {
        $this->authorize('update', $laporan);

        $this->laporan = $laporan;
        $this->jenis_kapal_id = $laporan->jenis_kapal_id;
        $this->judul = $laporan->judul;
        $this->tanggal_laporan = $laporan->tanggal_laporan->format('Y-m-d');
        $this->isi = $laporan->isi ?? '';
        $this->catatan = $laporan->catatan ?? '';

        // Load existing lampiran keterangan
        foreach ($laporan->lampiran as $lampiran) {
            $this->lampiranKeterangan[$lampiran->id] = $lampiran->keterangan ?? '';
        }

        // Initialize with 1 empty new lampiran field
        $this->newLampiran = [
            [
                'file' => null,
                'keterangan' => '',
                'cropData' => null,
                'is_cropped' => false,
            ]
        ];

        if ($laporan->tipe->value === 'harian') {
            $this->suhu = $laporan->suhu;
            $this->cuaca_pagi_id = $laporan->cuaca_pagi_id;
            $this->kelembaban_pagi_id = $laporan->kelembaban_pagi_id;
            $this->cuaca_siang_id = $laporan->cuaca_siang_id;
            $this->kelembaban_siang_id = $laporan->kelembaban_siang_id;
            $this->cuaca_sore_id = $laporan->cuaca_sore_id;
            $this->kelembaban_sore_id = $laporan->kelembaban_sore_id;
        }
    }

    public function rules(): array
    {
        $rules = [
            'jenis_kapal_id' => 'required|exists:jenis_kapal,id',
            'judul' => 'required|string|max:255',
            'tanggal_laporan' => 'required|date',
            'isi' => 'nullable|string',
            'catatan' => 'nullable|string|max:1000',
            'newLampiran.*.file' => file_upload_validation_rule(),
            'newLampiran.*.keterangan' => 'nullable|string|max:1000',
            'lampiranKeterangan.*' => 'nullable|string|max:1000',
        ];

        if ($this->laporan->tipe->value === 'harian') {
            $rules['suhu'] = 'nullable|numeric|min:-50|max:100';
            $rules['cuaca_pagi_id'] = 'nullable|exists:cuaca,id';
            $rules['kelembaban_pagi_id'] = 'nullable|exists:kelembaban,id';
            $rules['cuaca_siang_id'] = 'nullable|exists:cuaca,id';
            $rules['kelembaban_siang_id'] = 'nullable|exists:kelembaban,id';
            $rules['cuaca_sore_id'] = 'nullable|exists:cuaca,id';
            $rules['kelembaban_sore_id'] = 'nullable|exists:kelembaban,id';
        }

        return $rules;
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'jenis_kapal_id' => 'jenis kapal',
            'judul' => 'judul laporan',
            'tanggal_laporan' => 'tanggal laporan',
            'isi' => 'isi laporan',
            'catatan' => 'catatan',
            'newLampiran.*.file' => 'file lampiran baru',
            'newLampiran.*.keterangan' => 'keterangan lampiran baru',
            'lampiranKeterangan.*' => 'keterangan lampiran',
        ];

        if ($this->laporan->tipe->value === 'harian') {
            $attributes['suhu'] = 'suhu';
            $attributes['cuaca_pagi_id'] = 'cuaca pagi';
            $attributes['kelembaban_pagi_id'] = 'kelembaban pagi';
            $attributes['cuaca_siang_id'] = 'cuaca siang';
            $attributes['kelembaban_siang_id'] = 'kelembaban siang';
            $attributes['cuaca_sore_id'] = 'cuaca sore';
            $attributes['kelembaban_sore_id'] = 'kelembaban sore';
        }

        return $attributes;
    }

    public function confirmDeleteLampiran(int $lampiranId): void
    {
        $this->deletingLampiranId = $lampiranId;
        $this->showDeleteLampiranModal = true;
    }

    public function deleteLampiran(LaporanService $service): void
    {
        $this->authorize('update', $this->laporan);

        try {
            $lampiran = $this->laporan->lampiran()->findOrFail($this->deletingLampiranId);
            $service->deleteLampiran($lampiran);
            $this->laporan->refresh();
            $this->showDeleteLampiranModal = false;
            $this->deletingLampiranId = null;
            $this->notifySuccess('Lampiran berhasil dihapus.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal menghapus lampiran: ' . $e->getMessage());
        }
    }

    public function addLampiran(): void
    {
        $this->newLampiran[] = [
            'file' => null,
            'keterangan' => '',
            'cropData' => null,
            'is_cropped' => false,
        ];
    }

    public function removeNewLampiran(int $index): void
    {
        if (isset($this->newLampiran[$index])) {
            unset($this->newLampiran[$index]);
            $this->newLampiran = array_values($this->newLampiran);
        }
    }

    public function openCropper(int $index): void
    {
        if (!isset($this->newLampiran[$index]['file'])) {
            return;
        }

        $file = $this->newLampiran[$index]['file'];
        if (!$file) {
            return;
        }

        // Only allow crop for images
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $this->notifyWarning('Crop hanya tersedia untuk file gambar (JPG, PNG, WEBP).');
            return;
        }

        $this->croppingLampiranIndex = $index;
        $this->croppingImageUrl = $file->temporaryUrl();
        // Load existing crop data if available to persist last crop settings
        $this->cropData = $this->newLampiran[$index]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function closeCropper(): void
    {
        $this->showCropperModal = false;
        $this->croppingLampiranIndex = null;
        $this->croppingImageUrl = null;
        // Don't reset cropData here to preserve it for re-opening
    }

    public function saveCrop(): void
    {
        if ($this->croppingLampiranIndex !== null && !empty($this->cropData)) {
            // Save crop data to the lampiran item to persist settings
            $this->newLampiran[$this->croppingLampiranIndex]['cropData'] = $this->cropData;
            $this->newLampiran[$this->croppingLampiranIndex]['is_cropped'] = true;
            $this->notifySuccess('Crop berhasil disimpan.');
        }
        $this->closeCropper();
    }

    public function updateLampiranKeterangan(int $lampiranId, string $keterangan, LaporanService $service): void
    {
        try {
            $lampiran = $this->laporan->lampiran()->findOrFail($lampiranId);
            $service->updateLampiranKeterangan($lampiran, $keterangan);
            $this->notifySuccess('Keterangan lampiran diperbarui.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal memperbarui keterangan: ' . $e->getMessage());
        }
    }

    public function save(LaporanService $service): void
    {
        $this->authorize('update', $this->laporan);
        $this->validate();

        try {
            $data = [
                'jenis_kapal_id' => $this->jenis_kapal_id,
                'judul' => $this->judul,
                'tanggal_laporan' => $this->tanggal_laporan,
                'isi' => $this->isi ?: null,
                'catatan' => $this->catatan ?: null,
            ];

            if ($this->laporan->tipe->value === 'harian') {
                $data['suhu'] = $this->suhu;
                $data['cuaca_pagi_id'] = $this->cuaca_pagi_id;
                $data['kelembaban_pagi_id'] = $this->kelembaban_pagi_id;
                $data['cuaca_siang_id'] = $this->cuaca_siang_id;
                $data['kelembaban_siang_id'] = $this->kelembaban_siang_id;
                $data['cuaca_sore_id'] = $this->cuaca_sore_id;
                $data['kelembaban_sore_id'] = $this->kelembaban_sore_id;
            }

            $service->update($this->laporan, $data);

            // Process new lampiran uploads
            foreach ($this->newLampiran as $lampiranData) {
                if (isset($lampiranData['file']) && $lampiranData['file']) {
                    $file = $lampiranData['file'];
                    $tempPath = 'laporan-temp/' . uniqid() . '_' . $file->getClientOriginalName();
                    Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));

                    // Create lampiran record
                    $lampiran = $service->addLampiran($this->laporan, [
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'keterangan' => $lampiranData['keterangan'] ?? null,
                    ]);

                    // Dispatch job with crop data
                    ProcessLaporanLampiran::dispatch(
                        $lampiran,
                        $tempPath,
                        $lampiranData['cropData'] ?? null,
                    );
                }
            }

            $tipeLabel = $this->laporan->tipe->label();
            $hasNewLampiran = collect($this->newLampiran)->filter(fn($l) => isset($l['file']) && $l['file'])->isNotEmpty();
            $message = "Laporan {$tipeLabel} berhasil diupdate!";
            if ($hasNewLampiran) {
                $message .= ' Lampiran baru sedang diproses di background.';
            }

            session()->flash('notify', [
                'type' => 'success',
                'message' => $message,
            ]);

            $this->redirect(route('laporan.index'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengupdate laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function openLampiranPreview(int $lampiranId): void
    {
        $lampiran = $this->laporan->lampiran->find($lampiranId);
        
        if (!$lampiran || !$lampiran->hasFile() || !$lampiran->isFileCompleted()) {
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
        if (!$this->previewLampiranId) {
            return null;
        }

        return $this->laporan->lampiran->find($this->previewLampiranId);
    }

    public function previewCroppedImage(int $index): void
    {
        if (!isset($this->newLampiran[$index]['file'])) {
            $this->notifyWarning('File tidak ditemukan.');
            return;
        }

        $file = $this->newLampiran[$index]['file'];
        if (!$file) {
            $this->notifyWarning('File tidak ditemukan.');
            return;
        }

        // Open cropper modal to show the cropped preview
        $this->croppingLampiranIndex = $index;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $this->newLampiran[$index]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function render(QueueStatusService $queueStatusService)
    {
        $canViewAllJenisKapal = auth()->user()->can('laporan_view_all_jenis_kapal');
        
        $jenisKapalList = JenisKapal::with(['company', 'galangan'])
            ->active()
            ->when(!$canViewAllJenisKapal, function ($q) {
                $q->whereHas('company', function ($q) {
                    $q->where('id', auth()->user()->company_id);
                });
            })
            ->orderBy('nama')
            ->get();

        $cuacaList = Cuaca::active()->orderBy('nama')->get();
        $kelembabanList = Kelembaban::active()->orderBy('nama')->get();

        return view('livewire.laporan.laporan-edit', [
            'tipeEnum' => $this->laporan->tipe,
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            'jenisKapalList' => $jenisKapalList,
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
        ]);
    }
}
