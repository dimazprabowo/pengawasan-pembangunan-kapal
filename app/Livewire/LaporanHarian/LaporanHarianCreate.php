<?php

namespace App\Livewire\LaporanHarian;

use App\Jobs\ProcessLaporanLampiran;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Models\DataKapal;
use App\Models\Kelembaban;
use App\Models\LaporanHarian;
use App\Services\LaporanHarianService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Tambah Laporan Harian'])]
class LaporanHarianCreate extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithFileUploads;

    public DataKapal $dataKapal;

    public ?int $data_kapal_id = null;

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

    // Preferensi autosave draft per user (persisten via cache)
    public bool $autoSave = true;

    // Tab hari aktif — server-side single source of truth (entangled ke Alpine)
    public int $activeTab = 0;

    public function updatedActiveTab(int $value): void
    {
        $this->activeTab = max(0, min($value, count($this->items) - 1));
    }

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('create', LaporanHarian::class);
        $this->authorizeDataKapalAccess($dataKapal);

        $this->dataKapal = $dataKapal;
        $this->data_kapal_id = $dataKapal->id;
        $this->autoSave = Cache::get('lh_draft_autosave:'.auth()->id(), true);
        $this->addItem();
    }

    public function toggleAutoSave(): void
    {
        $this->autoSave = ! $this->autoSave;
        Cache::forever('lh_draft_autosave:'.auth()->id(), $this->autoSave);
    }

    /**
     * Daftar tanggal yang sudah memiliki laporan harian untuk kapal ini (format Y-m-d).
     */
    protected function usedTanggalLaporan(): array
    {
        return LaporanHarian::where('data_kapal_id', $this->data_kapal_id)
            ->pluck('tanggal_laporan')
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->all();
    }

    /**
     * Tanggal yang sudah terpakai di DB maupun di tab lain pada form ini.
     */
    protected function takenTanggalLaporan(?int $excludeIndex = null): array
    {
        $fromForm = collect($this->items)
            ->except($excludeIndex)
            ->pluck('tanggal_laporan')
            ->filter()
            ->all();

        return array_merge($this->usedTanggalLaporan(), $fromForm);
    }

    public function addItem(): void
    {
        // Auto-increment: hari ke-N mengikuti tanggal laporan terakhir + 1 hari,
        // lewati tanggal yang sudah dipakai (di DB maupun di tab lain).
        // Tanggal tidak boleh melebihi hari ini — bila hari esok sudah lewat,
        // cari tanggal kosong dengan mundur dari hari ini.
        $lastTanggal = $this->items[count($this->items) - 1]['tanggal_laporan'] ?? null;
        $taken = $this->takenTanggalLaporan();
        $today = now()->startOfDay();

        $candidate = $lastTanggal
            ? \Carbon\Carbon::parse($lastTanggal)->addDay()
            : $today->copy();
        if ($candidate->gt($today)) {
            $candidate = $today->copy();
        }
        $guard = 0;
        while (in_array($candidate->format('Y-m-d'), $taken) && $guard++ < 3660) {
            $candidate->subDay();
        }
        $tanggalLaporan = $candidate->format('Y-m-d');

        $this->items[] = [
            'tanggal_laporan' => $tanggalLaporan,
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
            ],
        ];

        $this->activeTab = count($this->items) - 1;
        $this->dispatch('laporan-item-added', index: $this->activeTab);
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

        if ($this->activeTab > $index) {
            $this->activeTab--;
        }
        $this->activeTab = min($this->activeTab, max(0, count($this->items) - 1));

        $this->dispatch('laporan-item-removed', index: $index, count: count($this->items));

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
        if (! isset($this->lampiran[$itemIndex][$lampiranIndex]['file'])) {
            return;
        }

        $file = $this->lampiran[$itemIndex][$lampiranIndex]['file'];
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
        if ($this->croppingItemIndex !== null && $this->croppingLampiranIndex !== null && ! empty($this->cropData)) {
            $this->lampiran[$this->croppingItemIndex][$this->croppingLampiranIndex]['cropData'] = $this->cropData;
            $this->lampiran[$this->croppingItemIndex][$this->croppingLampiranIndex]['is_cropped'] = true;
            $this->notifySuccess('Crop berhasil disimpan.');
        }
        $this->closeCropper();
    }

    public function previewCroppedImage(int $itemIndex, int $lampiranIndex): void
    {
        if (! isset($this->lampiran[$itemIndex][$lampiranIndex]['file'])) {
            $this->notifyWarning('File tidak ditemukan.');

            return;
        }

        $file = $this->lampiran[$itemIndex][$lampiranIndex]['file'];
        if (! $file || ! is_object($file)) {
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
            'keterangan' => '',
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
        $this->notifySuccess('Personel berhasil dihapus');
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
            'keterangan' => '',
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
        $this->notifySuccess('Peralatan berhasil dihapus');
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
            'keterangan' => '',
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
        $this->notifySuccess('Consumable berhasil dihapus');
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
            'pic' => '',
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
        $this->notifySuccess('Aktivitas berhasil dihapus');
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
            'data_kapal_id' => 'required|exists:data_kapal,id',
            'items' => 'required|array|min:1',
            'items.*.tanggal_laporan' => [
                'required',
                'date',
                'before_or_equal:today',
                'distinct',
                // whereDate (bukan Rule::unique) agar perbandingan tanggal konsisten lintas driver DB
                function ($attribute, $value, $fail) {
                    if ($value && LaporanHarian::where('data_kapal_id', $this->data_kapal_id)
                        ->whereDate('tanggal_laporan', $value)
                        ->exists()) {
                        $fail('Tanggal pada :attribute sudah memiliki laporan untuk kapal ini.');
                    }
                },
            ],
            'lampiran.*.*.file' => file_upload_validation_rule('foto_kapal'),
            'lampiran.*.*.keterangan' => 'nullable|string|max:1000',
        ];

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

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'items.*.tanggal_laporan.before_or_equal' => 'Tanggal pada :attribute tidak boleh melebihi hari ini.',
            'items.*.tanggal_laporan.distinct' => 'Tanggal pada :attribute sama dengan tanggal di laporan lain pada form ini.',
        ];
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'data_kapal_id' => 'data kapal',
        ];

        foreach ($this->items as $index => $item) {
            $num = $index + 1;
            $attributes["items.{$index}.tanggal_laporan"] = "tanggal laporan #{$num}";

            foreach ($this->lampiran[$index] as $lampIndex => $lamp) {
                $lampNum = $lampIndex + 1;
                $attributes["lampiran.{$index}.{$lampIndex}.file"] = "file lampiran #{$lampNum} pada laporan #{$num}";
                $attributes["lampiran.{$index}.{$lampIndex}.keterangan"] = "keterangan lampiran #{$lampNum} pada laporan #{$num}";
            }

            $attributes["items.{$index}.suhu"] = "suhu laporan #{$num}";
            $attributes["items.{$index}.cuaca_pagi_id"] = "cuaca pagi laporan #{$num}";
            $attributes["items.{$index}.kelembaban_pagi_id"] = "kelembaban pagi laporan #{$num}";
            $attributes["items.{$index}.cuaca_siang_id"] = "cuaca siang laporan #{$num}";
            $attributes["items.{$index}.kelembaban_siang_id"] = "kelembaban siang laporan #{$num}";
            $attributes["items.{$index}.cuaca_sore_id"] = "cuaca sore laporan #{$num}";
            $attributes["items.{$index}.kelembaban_sore_id"] = "kelembaban sore laporan #{$num}";
        }

        return $attributes;
    }

    public function save(LaporanHarianService $service): void
    {
        $this->authorize('create', LaporanHarian::class);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            $firstKey = collect($e->validator->errors()->keys())
                ->first(fn ($k) => str_starts_with($k, 'items.') || str_starts_with($k, 'lampiran.'));
            if ($firstKey && preg_match('/^(items|lampiran)\.(\d+)/', $firstKey, $m)) {
                $this->activeTab = (int) $m[2];
            }
            throw $e;
        }

        try {
            $userId = auth()->id();
            $dataItems = [];

            foreach ($this->items as $item) {
                $itemData = [
                    'user_id' => $userId,
                    'data_kapal_id' => $this->data_kapal_id,
                    'tanggal_laporan' => $item['tanggal_laporan'],
                ];

                $itemData['suhu'] = $item['suhu'] ?: null;
                $itemData['cuaca_pagi_id'] = $item['cuaca_pagi_id'] ?: null;
                $itemData['kelembaban_pagi_id'] = $item['kelembaban_pagi_id'] ?: null;
                $itemData['cuaca_siang_id'] = $item['cuaca_siang_id'] ?: null;
                $itemData['kelembaban_siang_id'] = $item['kelembaban_siang_id'] ?: null;
                $itemData['cuaca_sore_id'] = $item['cuaca_sore_id'] ?: null;
                $itemData['kelembaban_sore_id'] = $item['kelembaban_sore_id'] ?: null;

                $dataItems[] = $itemData;
            }

            $createdLaporans = $service->createMany($dataItems);

            // Process dynamic inputs and lampiran for each created laporan
            foreach ($createdLaporans as $index => $laporan) {
                $item = $this->items[$index];

                // Save dynamic inputs

                // Save personel - allow partial data (any field filled)
                if (isset($item['personel']) && is_array($item['personel'])) {
                    foreach ($item['personel'] as $personelData) {
                        $hasData = ! empty($personelData['jabatan']) ||
                                  ! empty($personelData['status']) ||
                                  ! empty($personelData['keterangan']);

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
                        $hasData = ! empty($peralatanData['jenis']) ||
                                  ! empty($peralatanData['jumlah']) ||
                                  ! empty($peralatanData['keterangan']);

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
                        $hasData = ! empty($consumableData['jenis']) ||
                                  ! empty($consumableData['jumlah']) ||
                                  ! empty($consumableData['keterangan']);

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
                        $hasData = ! empty($aktivitasData['kategori']) ||
                                  ! empty($aktivitasData['aktivitas']) ||
                                  ! empty($aktivitasData['pic']);

                        if ($hasData) {
                            $laporan->aktivitas()->create([
                                'kategori' => $aktivitasData['kategori'] ?: null,
                                'aktivitas' => $aktivitasData['aktivitas'] ?: null,
                                'pic' => $aktivitasData['pic'] ?: null,
                            ]);
                        }
                    }
                }

                // Process lampiran
                if (isset($this->lampiran[$index]) && is_array($this->lampiran[$index])) {
                    foreach ($this->lampiran[$index] as $lampiranData) {
                        if (isset($lampiranData['file']) && $lampiranData['file'] && is_object($lampiranData['file'])) {
                            $file = $lampiranData['file'];
                            $tempPath = 'laporan-temp/'.uniqid().'_'.$file->getClientOriginalName();
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
            $hasLampiran = collect($this->lampiran)->flatten(1)->filter(fn ($l) => isset($l['file']) && $l['file'])->isNotEmpty();
            $message = "{$count} laporan harian berhasil ditambahkan!";
            if ($hasLampiran) {
                $message .= ' Lampiran sedang diproses di background.';
            }

            $this->notifySuccess($message);

            $this->redirect(route('laporan-harian.index', $this->dataKapal), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function cancel()
    {
        // Beri tahu Alpine untuk menghapus draft lokal sebelum navigate
        $this->dispatch('lh-clear-draft');

        return $this->redirect(route('laporan-harian.index', $this->dataKapal), navigate: true);
    }

    /**
     * Listen for changes in items array (specifically tanggal_laporan)
     * Triggered when tanggal_laporan changes (with .live modifier)
     */
    public function updatedItems($value, $key): void
    {
        // Check if tanggal_laporan was updated (key format: "{index}.tanggal_laporan")
        if (str_ends_with($key, '.tanggal_laporan')) {
            $index = explode('.', $key)[0];
            $newTanggal = $value;

            // Peringatan dini jika tanggal di masa depan / sudah terpakai
            // (mis. dari draft autosave basi yang melewati picker)
            if (\Carbon\Carbon::parse($newTanggal)->isFuture()) {
                $this->notifyWarning('Tanggal laporan tidak boleh melebihi hari ini.');
            } elseif (in_array($newTanggal, $this->usedTanggalLaporan())) {
                $this->notifyWarning('Tanggal '.\Carbon\Carbon::parse($newTanggal)->translatedFormat('d M Y').' sudah memiliki laporan untuk kapal ini.');
            } elseif (in_array($newTanggal, $this->takenTanggalLaporan((int) $index))) {
                $this->notifyWarning('Tanggal '.\Carbon\Carbon::parse($newTanggal)->translatedFormat('d M Y').' sama dengan tanggal di tab lain.');
            }
        }
    }

    public function render()
    {
        $cuacaList = Cuaca::active()->orderBy('nama')->get();
        $kelembabanList = Kelembaban::active()->orderBy('nama')->get();

        return view('livewire.laporan-harian.laporan-harian-create', [
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
            'usedDates' => $this->usedTanggalLaporan(),
        ]);
    }
}
