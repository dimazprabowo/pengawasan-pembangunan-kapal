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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Tambah Laporan'])]
class LaporanCreate extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public string $tipe = 'harian';

    public ?int $jenis_kapal_id = null;

    public array $items = [];

    // Lampiran per item - struktur: [itemIndex => [lampiranIndex => ['file' => null, 'keterangan' => '', 'cropData' => null]]]
    public array $lampiran = [];

    // Delete card confirmation modal
    public bool $showDeleteCardModal = false;
    public ?int $deletingCardIndex = null;

    // Delete lampiran confirmation modal
    public bool $showDeleteLampiranModal = false;
    public ?int $deletingLampiranItemIndex = null;
    public ?int $deletingLampiranIndex = null;

    // Delete personel confirmation modal
    public bool $showDeletePersonelModal = false;
    public ?int $deletingPersonelItemIndex = null;
    public ?int $deletingPersonelIndex = null;

    // Delete peralatan confirmation modal
    public bool $showDeletePeralatanModal = false;
    public ?int $deletingPeralatanItemIndex = null;
    public ?int $deletingPeralatanIndex = null;

    // Delete consumable confirmation modal
    public bool $showDeleteConsumableModal = false;
    public ?int $deletingConsumableItemIndex = null;
    public ?int $deletingConsumableIndex = null;

    // Delete aktivitas confirmation modal
    public bool $showDeleteAktivitasModal = false;
    public ?int $deletingAktivitasItemIndex = null;
    public ?int $deletingAktivitasIndex = null;

    // Image cropper modal
    public bool $showCropperModal = false;
    public ?int $croppingItemIndex = null;
    public ?int $croppingLampiranIndex = null;
    public ?string $croppingImageUrl = null;
    public array $cropData = [];

    public function mount(string $tipe): void
    {
        $this->authorize('create', Laporan::class);

        if (!in_array($tipe, LaporanTipe::values())) {
            abort(404);
        }

        $this->tipe = $tipe;
        $this->jenis_kapal_id = session('laporan_jenis_kapal_id');
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'judul' => '',
            'tanggal_laporan' => now()->format('Y-m-d'),
            'suhu' => '',
            'cuaca_pagi_id' => null,
            'kelembaban_pagi_id' => null,
            'cuaca_siang_id' => null,
            'kelembaban_siang_id' => null,
            'cuaca_sore_id' => null,
            'kelembaban_sore_id' => null,
            'personel' => [],
            'peralatan' => [],
            'consumable' => [],
            'aktivitas' => [],
        ];
        $this->lampiran[] = [
            [
                'file' => null,
                'keterangan' => '',
                'cropData' => null,
                'is_cropped' => false,
            ]
        ];
    }

    public function confirmRemoveItem(int $index): void
    {
        if (count($this->items) <= 1) {
            $this->notifyWarning('Minimal harus ada 1 laporan.');
            return;
        }

        $this->deletingCardIndex = $index;
        $this->showDeleteCardModal = true;
    }

    public function removeItem(): void
    {
        if ($this->deletingCardIndex === null) {
            return;
        }

        $index = $this->deletingCardIndex;

        unset($this->items[$index]);
        unset($this->lampiran[$index]);
        $this->items = array_values($this->items);
        $this->lampiran = array_values($this->lampiran);

        $this->showDeleteCardModal = false;
        $this->deletingCardIndex = null;
    }

    public function addLampiran(int $itemIndex): void
    {
        $this->lampiran[$itemIndex][] = [
            'file' => null,
            'keterangan' => '',
            'cropData' => null,
            'is_cropped' => false,
        ];
    }

    public function removeLampiran(int $itemIndex, int $lampiranIndex): void
    {
        if (isset($this->lampiran[$itemIndex][$lampiranIndex])) {
            unset($this->lampiran[$itemIndex][$lampiranIndex]);
            $this->lampiran[$itemIndex] = array_values($this->lampiran[$itemIndex]);
        }
    }

    public function confirmRemoveLampiran(int $itemIndex, int $lampiranIndex): void
    {
        $this->deletingLampiranItemIndex = $itemIndex;
        $this->deletingLampiranIndex = $lampiranIndex;
        $this->showDeleteLampiranModal = true;
    }

    public function removeLampiranConfirmed(): void
    {
        if ($this->deletingLampiranItemIndex !== null && $this->deletingLampiranIndex !== null) {
            $this->removeLampiran($this->deletingLampiranItemIndex, $this->deletingLampiranIndex);
        }
        $this->showDeleteLampiranModal = false;
        $this->deletingLampiranItemIndex = null;
        $this->deletingLampiranIndex = null;
    }

    public function openCropper(int $itemIndex, int $lampiranIndex): void
    {
        if (!isset($this->lampiran[$itemIndex][$lampiranIndex]['file'])) {
            return;
        }

        $file = $this->lampiran[$itemIndex][$lampiranIndex]['file'];
        if (!$file) {
            return;
        }

        // Only allow crop for images
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $this->notifyWarning('Crop hanya tersedia untuk file gambar (JPG, PNG, WEBP).');
            return;
        }

        $this->croppingItemIndex = $itemIndex;
        $this->croppingLampiranIndex = $lampiranIndex;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $this->lampiran[$itemIndex][$lampiranIndex]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function closeCropper(): void
    {
        $this->showCropperModal = false;
        $this->croppingItemIndex = null;
        $this->croppingLampiranIndex = null;
        $this->croppingImageUrl = null;
        $this->cropData = [];
    }

    public function saveCrop(): void
    {
        if ($this->croppingItemIndex !== null && $this->croppingLampiranIndex !== null && !empty($this->cropData)) {
            $this->lampiran[$this->croppingItemIndex][$this->croppingLampiranIndex]['cropData'] = $this->cropData;
            $this->lampiran[$this->croppingItemIndex][$this->croppingLampiranIndex]['is_cropped'] = true;
            $this->notifySuccess('Crop berhasil disimpan.');
        }
        $this->closeCropper();
    }

    public function previewCroppedImage(int $itemIndex, int $lampiranIndex): void
    {
        if (!isset($this->lampiran[$itemIndex][$lampiranIndex]['file'])) {
            $this->notifyWarning('File tidak ditemukan.');
            return;
        }

        $file = $this->lampiran[$itemIndex][$lampiranIndex]['file'];
        if (!$file) {
            $this->notifyWarning('File tidak ditemukan.');
            return;
        }

        // Open cropper modal to show the cropped preview
        $this->croppingItemIndex = $itemIndex;
        $this->croppingLampiranIndex = $lampiranIndex;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $this->lampiran[$itemIndex][$lampiranIndex]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    // Dynamic input management methods
    public function addPersonel(int $itemIndex): void
    {
        $this->items[$itemIndex]['personel'][] = [
            'jabatan' => '',
            'status' => '',
            'keterangan' => ''
        ];
    }

    public function confirmRemovePersonel(int $itemIndex, int $personelIndex): void
    {
        $this->deletingPersonelItemIndex = $itemIndex;
        $this->deletingPersonelIndex = $personelIndex;
        $this->showDeletePersonelModal = true;
    }

    public function removePersonelConfirmed(): void
    {
        if ($this->deletingPersonelItemIndex !== null && $this->deletingPersonelIndex !== null) {
            $this->removePersonel($this->deletingPersonelItemIndex, $this->deletingPersonelIndex);
        }
        $this->showDeletePersonelModal = false;
        $this->deletingPersonelItemIndex = null;
        $this->deletingPersonelIndex = null;
    }

    public function removePersonel(int $itemIndex, int $personelIndex): void
    {
        if (isset($this->items[$itemIndex]['personel'][$personelIndex])) {
            unset($this->items[$itemIndex]['personel'][$personelIndex]);
            $this->items[$itemIndex]['personel'] = array_values($this->items[$itemIndex]['personel']);
        }
    }

    public function addPeralatan(int $itemIndex): void
    {
        $this->items[$itemIndex]['peralatan'][] = [
            'jenis' => '',
            'jumlah' => '',
            'keterangan' => ''
        ];
    }

    public function confirmRemovePeralatan(int $itemIndex, int $peralatanIndex): void
    {
        $this->deletingPeralatanItemIndex = $itemIndex;
        $this->deletingPeralatanIndex = $peralatanIndex;
        $this->showDeletePeralatanModal = true;
    }

    public function removePeralatanConfirmed(): void
    {
        if ($this->deletingPeralatanItemIndex !== null && $this->deletingPeralatanIndex !== null) {
            $this->removePeralatan($this->deletingPeralatanItemIndex, $this->deletingPeralatanIndex);
        }
        $this->showDeletePeralatanModal = false;
        $this->deletingPeralatanItemIndex = null;
        $this->deletingPeralatanIndex = null;
    }

    public function removePeralatan(int $itemIndex, int $peralatanIndex): void
    {
        if (isset($this->items[$itemIndex]['peralatan'][$peralatanIndex])) {
            unset($this->items[$itemIndex]['peralatan'][$peralatanIndex]);
            $this->items[$itemIndex]['peralatan'] = array_values($this->items[$itemIndex]['peralatan']);
        }
    }

    public function addConsumable(int $itemIndex): void
    {
        $this->items[$itemIndex]['consumable'][] = [
            'jenis' => '',
            'jumlah' => '',
            'keterangan' => ''
        ];
    }

    public function confirmRemoveConsumable(int $itemIndex, int $consumableIndex): void
    {
        $this->deletingConsumableItemIndex = $itemIndex;
        $this->deletingConsumableIndex = $consumableIndex;
        $this->showDeleteConsumableModal = true;
    }

    public function removeConsumableConfirmed(): void
    {
        if ($this->deletingConsumableItemIndex !== null && $this->deletingConsumableIndex !== null) {
            $this->removeConsumable($this->deletingConsumableItemIndex, $this->deletingConsumableIndex);
        }
        $this->showDeleteConsumableModal = false;
        $this->deletingConsumableItemIndex = null;
        $this->deletingConsumableIndex = null;
    }

    public function removeConsumable(int $itemIndex, int $consumableIndex): void
    {
        if (isset($this->items[$itemIndex]['consumable'][$consumableIndex])) {
            unset($this->items[$itemIndex]['consumable'][$consumableIndex]);
            $this->items[$itemIndex]['consumable'] = array_values($this->items[$itemIndex]['consumable']);
        }
    }

    public function addAktivitas(int $itemIndex): void
    {
        $this->items[$itemIndex]['aktivitas'][] = [
            'kategori' => 'New Building',
            'aktivitas' => '',
            'pic' => ''
        ];
    }

    public function confirmRemoveAktivitas(int $itemIndex, int $aktivitasIndex): void
    {
        $this->deletingAktivitasItemIndex = $itemIndex;
        $this->deletingAktivitasIndex = $aktivitasIndex;
        $this->showDeleteAktivitasModal = true;
    }

    public function removeAktivitasConfirmed(): void
    {
        if ($this->deletingAktivitasItemIndex !== null && $this->deletingAktivitasIndex !== null) {
            $this->removeAktivitas($this->deletingAktivitasItemIndex, $this->deletingAktivitasIndex);
        }
        $this->showDeleteAktivitasModal = false;
        $this->deletingAktivitasItemIndex = null;
        $this->deletingAktivitasIndex = null;
    }

    public function removeAktivitas(int $itemIndex, int $aktivitasIndex): void
    {
        if (isset($this->items[$itemIndex]['aktivitas'][$aktivitasIndex])) {
            unset($this->items[$itemIndex]['aktivitas'][$aktivitasIndex]);
            $this->items[$itemIndex]['aktivitas'] = array_values($this->items[$itemIndex]['aktivitas']);
        }
    }

    protected function rules(): array
    {
        $rules = [
            'jenis_kapal_id' => 'required|exists:jenis_kapal,id',
            'items' => 'required|array|min:1',
            'items.*.judul' => 'required|string|max:255',
            'items.*.tanggal_laporan' => 'required|date',
            'lampiran.*.*.file' => file_upload_validation_rule('foto_kapal'),
            'lampiran.*.*.keterangan' => 'nullable|string|max:1000',
        ];

        if ($this->tipe === 'harian') {
            $rules['items.*.suhu'] = 'nullable|numeric|min:-50|max:100';
            $rules['items.*.cuaca_pagi_id'] = 'nullable|exists:cuaca,id';
            $rules['items.*.kelembaban_pagi_id'] = 'nullable|exists:kelembaban,id';
            $rules['items.*.cuaca_siang_id'] = 'nullable|exists:cuaca,id';
            $rules['items.*.kelembaban_siang_id'] = 'nullable|exists:kelembaban,id';
            $rules['items.*.cuaca_sore_id'] = 'nullable|exists:cuaca,id';
            $rules['items.*.kelembaban_sore_id'] = 'nullable|exists:kelembaban,id';
            $rules['items.*.personel'] = 'nullable|array';
            $rules['items.*.personel.*.jabatan'] = 'nullable|string|max:255';
            $rules['items.*.personel.*.status'] = 'nullable|string|max:255';
            $rules['items.*.personel.*.keterangan'] = 'nullable|string|max:1000';
            $rules['items.*.peralatan'] = 'nullable|array';
            $rules['items.*.peralatan.*.jenis'] = 'nullable|string|max:255';
            $rules['items.*.peralatan.*.jumlah'] = 'nullable|integer|min:1';
            $rules['items.*.peralatan.*.keterangan'] = 'nullable|string|max:1000';
            $rules['items.*.consumable'] = 'nullable|array';
            $rules['items.*.consumable.*.jenis'] = 'nullable|string|max:255';
            $rules['items.*.consumable.*.jumlah'] = 'nullable|integer|min:1';
            $rules['items.*.consumable.*.keterangan'] = 'nullable|string|max:1000';
            $rules['items.*.aktivitas'] = 'nullable|array';
            $rules['items.*.aktivitas.*.kategori'] = 'nullable|string|max:255';
            $rules['items.*.aktivitas.*.aktivitas'] = 'nullable|string';
            $rules['items.*.aktivitas.*.pic'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'jenis_kapal_id' => 'jenis kapal',
        ];
        
        foreach ($this->items as $index => $item) {
            $num = $index + 1;
            $attributes["items.{$index}.judul"] = "judul laporan #{$num}";
            $attributes["items.{$index}.tanggal_laporan"] = "tanggal laporan #{$num}";

            foreach ($this->lampiran[$index] as $lampIndex => $lamp) {
                $lampNum = $lampIndex + 1;
                $attributes["lampiran.{$index}.{$lampIndex}.file"] = "file lampiran #{$lampNum} pada laporan #{$num}";
                $attributes["lampiran.{$index}.{$lampIndex}.keterangan"] = "keterangan lampiran #{$lampNum} pada laporan #{$num}";
            }
            
            if ($this->tipe === 'harian') {
                $attributes["items.{$index}.suhu"] = "suhu laporan #{$num}";
                $attributes["items.{$index}.cuaca_pagi_id"] = "cuaca pagi laporan #{$num}";
                $attributes["items.{$index}.kelembaban_pagi_id"] = "kelembaban pagi laporan #{$num}";
                $attributes["items.{$index}.cuaca_siang_id"] = "cuaca siang laporan #{$num}";
                $attributes["items.{$index}.kelembaban_siang_id"] = "kelembaban siang laporan #{$num}";
                $attributes["items.{$index}.cuaca_sore_id"] = "cuaca sore laporan #{$num}";
                $attributes["items.{$index}.kelembaban_sore_id"] = "kelembaban sore laporan #{$num}";
            }
        }
        return $attributes;
    }

    public function save(LaporanService $service): void
    {
        $this->authorize('create', Laporan::class);
        
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $userId = auth()->id();
            $dataItems = [];

            foreach ($this->items as $item) {
                $itemData = [
                    'user_id' => $userId,
                    'jenis_kapal_id' => $this->jenis_kapal_id,
                    'tipe' => $this->tipe,
                    'judul' => $item['judul'],
                    'tanggal_laporan' => $item['tanggal_laporan'],
                ];

                if ($this->tipe === 'harian') {
                    $itemData['suhu'] = $item['suhu'] ?: null;
                    $itemData['cuaca_pagi_id'] = $item['cuaca_pagi_id'] ?: null;
                    $itemData['kelembaban_pagi_id'] = $item['kelembaban_pagi_id'] ?: null;
                    $itemData['cuaca_siang_id'] = $item['cuaca_siang_id'] ?: null;
                    $itemData['kelembaban_siang_id'] = $item['kelembaban_siang_id'] ?: null;
                    $itemData['cuaca_sore_id'] = $item['cuaca_sore_id'] ?: null;
                    $itemData['kelembaban_sore_id'] = $item['kelembaban_sore_id'] ?: null;
                }

                $dataItems[] = $itemData;
            }

            $createdLaporans = $service->createMany($dataItems);

            // Process dynamic inputs and lampiran for each created laporan
            foreach ($createdLaporans as $index => $laporan) {
                $item = $this->items[$index];

                // Save dynamic inputs for harian only
                if ($this->tipe === 'harian') {
                    // Save personel - allow partial data (any field filled)
                    if (isset($item['personel']) && is_array($item['personel'])) {
                        foreach ($item['personel'] as $personelData) {
                            $hasData = !empty($personelData['jabatan']) || 
                                      !empty($personelData['status']) || 
                                      !empty($personelData['keterangan']);
                            
                            if ($hasData) {
                                $laporan->personel()->create([
                                    'jabatan' => $personelData['jabatan'] ?: null,
                                    'status' => $personelData['status'] ?: null,
                                    'keterangan' => $personelData['keterangan'] ?: null,
                                ]);
                            }
                        }
                    }

                    // Save peralatan - allow partial data (any field filled)
                    if (isset($item['peralatan']) && is_array($item['peralatan'])) {
                        foreach ($item['peralatan'] as $peralatanData) {
                            $hasData = !empty($peralatanData['jenis']) || 
                                      !empty($peralatanData['jumlah']) || 
                                      !empty($peralatanData['keterangan']);
                            
                            if ($hasData) {
                                $laporan->peralatan()->create([
                                    'jenis' => $peralatanData['jenis'] ?: null,
                                    'jumlah' => $peralatanData['jumlah'] ?: null,
                                    'keterangan' => $peralatanData['keterangan'] ?: null,
                                ]);
                            }
                        }
                    }

                    // Save consumable - allow partial data (any field filled)
                    if (isset($item['consumable']) && is_array($item['consumable'])) {
                        foreach ($item['consumable'] as $consumableData) {
                            $hasData = !empty($consumableData['jenis']) || 
                                      !empty($consumableData['jumlah']) || 
                                      !empty($consumableData['keterangan']);
                            
                            if ($hasData) {
                                $laporan->consumable()->create([
                                    'jenis' => $consumableData['jenis'] ?: null,
                                    'jumlah' => $consumableData['jumlah'] ?: null,
                                    'keterangan' => $consumableData['keterangan'] ?: null,
                                ]);
                            }
                        }
                    }

                    // Save aktivitas - allow partial data (any field filled)
                    if (isset($item['aktivitas']) && is_array($item['aktivitas'])) {
                        foreach ($item['aktivitas'] as $aktivitasData) {
                            $hasData = !empty($aktivitasData['kategori']) || 
                                      !empty($aktivitasData['aktivitas']) || 
                                      !empty($aktivitasData['pic']);
                            
                            if ($hasData) {
                                $laporan->aktivitas()->create([
                                    'kategori' => $aktivitasData['kategori'] ?: null,
                                    'aktivitas' => $aktivitasData['aktivitas'] ?: null,
                                    'pic' => $aktivitasData['pic'] ?: null,
                                ]);
                            }
                        }
                    }
                }

                // Process lampiran
                if (isset($this->lampiran[$index]) && is_array($this->lampiran[$index])) {
                    foreach ($this->lampiran[$index] as $lampiranData) {
                        if (isset($lampiranData['file']) && $lampiranData['file']) {
                            $file = $lampiranData['file'];
                            $tempPath = 'laporan-temp/' . uniqid() . '_' . $file->getClientOriginalName();
                            Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));

                            // Create lampiran record
                            $lampiran = $service->addLampiran($laporan, [
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
                }
            }

            $count = count($dataItems);
            $hasLampiran = collect($this->lampiran)->flatten(1)->filter(fn($l) => isset($l['file']) && $l['file'])->isNotEmpty();
            $tipeLabel = LaporanTipe::from($this->tipe)->label();

            $message = "{$count} laporan {$tipeLabel} berhasil ditambahkan!";
            if ($hasLampiran) {
                $message .= ' Lampiran sedang diproses di background.';
            }

            session()->flash('notify', [
                'type' => 'success',
                'message' => $message,
            ]);

            $this->redirect(route('laporan.index'), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
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

        return view('livewire.laporan.laporan-create', [
            'tipeEnum' => LaporanTipe::from($this->tipe),
            'jenisKapalList' => $jenisKapalList,
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
        ]);
    }
}
