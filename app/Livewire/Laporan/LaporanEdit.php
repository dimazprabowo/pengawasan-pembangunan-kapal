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

    // Lampiran baru yang akan ditambahkan
    public array $newLampiran = [];

    // Keterangan untuk existing lampiran yang diedit
    public array $lampiranKeterangan = [];

    // Delete lampiran confirmation
    public bool $showDeleteLampiranModal = false;
    public ?int $deletingLampiranId = null;

    // Delete new (unsaved) lampiran confirmation
    public bool $showDeleteNewLampiranModal = false;
    public ?int $deletingNewLampiranIndex = null;

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

            // Load existing dynamic data
            $this->personel = $laporan->personel->map(fn($p) => [
                'id' => $p->id,
                'jabatan' => $p->jabatan,
                'status' => $p->status,
                'keterangan' => $p->keterangan ?? '',
            ])->toArray();

            $this->peralatan = $laporan->peralatan->map(fn($p) => [
                'id' => $p->id,
                'jenis' => $p->jenis,
                'jumlah' => $p->jumlah,
                'keterangan' => $p->keterangan ?? '',
            ])->toArray();

            $this->consumable = $laporan->consumable->map(fn($c) => [
                'id' => $c->id,
                'jenis' => $c->jenis,
                'jumlah' => $c->jumlah,
                'keterangan' => $c->keterangan ?? '',
            ])->toArray();

            $this->aktivitas = $laporan->aktivitas->map(fn($a) => [
                'id' => $a->id,
                'kategori' => $a->kategori,
                'aktivitas' => $a->aktivitas,
                'pic' => $a->pic,
            ])->toArray();

            // Sections A-E are optional - no default empty rows
        }
    }

    public function rules(): array
    {
        $rules = [
            'jenis_kapal_id' => 'required|exists:jenis_kapal,id',
            'judul' => 'required|string|max:255',
            'tanggal_laporan' => 'required|date',
            'newLampiran.*.file' => file_upload_validation_rule('foto_kapal'),
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
            $rules['aktivitas.*.kategori'] = 'nullable|string|max:255';
            $rules['aktivitas.*.aktivitas'] = 'nullable|string';
            $rules['aktivitas.*.pic'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'jenis_kapal_id' => 'jenis kapal',
            'judul' => 'judul laporan',
            'tanggal_laporan' => 'tanggal laporan',
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

    public function confirmRemoveNewLampiran(int $index): void
    {
        $this->deletingNewLampiranIndex = $index;
        $this->showDeleteNewLampiranModal = true;
    }

    public function removeNewLampiranConfirmed(): void
    {
        if ($this->deletingNewLampiranIndex !== null) {
            $this->removeNewLampiran($this->deletingNewLampiranIndex);
        }
        $this->showDeleteNewLampiranModal = false;
        $this->deletingNewLampiranIndex = null;
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
        
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'jenis_kapal_id' => $this->jenis_kapal_id,
                'judul' => $this->judul,
                'tanggal_laporan' => $this->tanggal_laporan,
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

            // Update dynamic inputs for harian only
            if ($this->laporan->tipe->value === 'harian') {
                // Sync personel - allow partial data (any field filled)
                $this->laporan->personel()->delete();
                foreach ($this->personel as $personelData) {
                    $hasData = !empty($personelData['jabatan']) || 
                              !empty($personelData['status']) || 
                              !empty($personelData['keterangan']);
                    
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
                    $hasData = !empty($peralatanData['jenis']) || 
                              !empty($peralatanData['jumlah']) || 
                              !empty($peralatanData['keterangan']);
                    
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
                    $hasData = !empty($consumableData['jenis']) || 
                              !empty($consumableData['jumlah']) || 
                              !empty($consumableData['keterangan']);
                    
                    if ($hasData) {
                        $this->laporan->consumable()->create([
                            'jenis' => $consumableData['jenis'] ?: null,
                            'jumlah' => $consumableData['jumlah'] ?: null,
                            'keterangan' => $consumableData['keterangan'] ?: null,
                        ]);
                    }
                }

                // Sync aktivitas - allow partial data (any field filled)
                $this->laporan->aktivitas()->delete();
                foreach ($this->aktivitas as $aktivitasData) {
                    $hasData = !empty($aktivitasData['kategori']) || 
                              !empty($aktivitasData['aktivitas']) || 
                              !empty($aktivitasData['pic']);
                    
                    if ($hasData) {
                        $this->laporan->aktivitas()->create([
                            'kategori' => $aktivitasData['kategori'] ?: null,
                            'aktivitas' => $aktivitasData['aktivitas'] ?: null,
                            'pic' => $aktivitasData['pic'] ?: null,
                        ]);
                    }
                }
            }

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

    // Dynamic input management methods
    public function addPersonel(): void
    {
        $this->personel[] = [
            'jabatan' => '',
            'status' => '',
            'keterangan' => ''
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
            'keterangan' => ''
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
            'keterangan' => ''
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
    }

    public function removeConsumable(int $index): void
    {
        if (isset($this->consumable[$index])) {
            unset($this->consumable[$index]);
            $this->consumable = array_values($this->consumable);
        }
    }

    public function addAktivitas(): void
    {
        $this->aktivitas[] = [
            'kategori' => 'New Building',
            'aktivitas' => '',
            'pic' => ''
        ];
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
    }

    public function removeAktivitas(int $index): void
    {
        if (isset($this->aktivitas[$index])) {
            unset($this->aktivitas[$index]);
            $this->aktivitas = array_values($this->aktivitas);
        }
    }

    /**
     * Daftar nama hari dalam bahasa Indonesia (key = English, value = Indonesian)
     */
    protected array $indonesianDayNames = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    /**
     * Get list of Indonesian day names for regex matching
     */
    protected function getIndonesianDayNameList(): array
    {
        return array_values($this->indonesianDayNames);
    }

    /**
     * Generate default judul based on tanggal_laporan
     * Format: "Laporan Hari [NamaHari]"
     */
    protected function generateDefaultJudul(string $tanggal): string
    {
        $englishDayName = date('l', strtotime($tanggal));
        $indonesianDayName = $this->indonesianDayNames[$englishDayName] ?? $englishDayName;

        return 'Laporan Hari ' . $indonesianDayName;
    }

    /**
     * Find and replace day name in existing judul
     * If current judul contains any Indonesian day name, replace it with new day name
     * If not, generate default format
     */
    protected function updateJudulWithNewDay(string $currentJudul, string $newTanggal): string
    {
        $dayNames = $this->getIndonesianDayNameList();
        $englishDayName = date('l', strtotime($newTanggal));
        $newIndonesianDayName = $this->indonesianDayNames[$englishDayName] ?? $englishDayName;

        // Build regex pattern to find any Indonesian day name in the string
        $pattern = '/(' . implode('|', $dayNames) . ')/i';

        // Check if current judul contains any day name
        if (preg_match($pattern, $currentJudul, $matches)) {
            // Replace the found day name with the new one
            return preg_replace($pattern, $newIndonesianDayName, $currentJudul, 1);
        }

        // If no day name found in current judul, return default format
        // return $this->generateDefaultJudul($newTanggal);
        return $currentJudul;
    }

    /**
     * Listen for changes in tanggal_laporan
     * Triggered when tanggal_laporan changes (with .live modifier)
     */
    public function updatedTanggalLaporan($value): void
    {
        $currentJudul = $this->judul ?? '';

        // Update judul: find and replace day name, or generate default if empty/no day name found
        $this->judul = $this->updateJudulWithNewDay($currentJudul, $value);
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
