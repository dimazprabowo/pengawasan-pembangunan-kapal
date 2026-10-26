<?php

namespace App\Livewire\LaporanHarian;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Models\DataKapal;
use App\Models\Kelembaban;
use App\Models\LaporanHarian;
use App\Services\LaporanHarianService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

    // Foto lampiran per uraian aktivitas - struktur: [itemIndex => [row_key => ['file' => null, 'cropData' => null, 'is_cropped' => false]]]
    public array $lampiran = [];

    // Delete card confirmation modal
    public bool $showDeleteCardModal = false;

    public ?int $deletingCardIndex = null;

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

    public ?string $croppingRowKey = null;

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
    protected function takenTanggalLaporan(): array
    {
        $fromForm = collect($this->items)
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
        $this->lampiran[] = [];

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

    private function emptyFoto(): array
    {
        return ['file' => null, 'preview' => null, 'cropData' => null, 'is_cropped' => false];
    }

    public function removeFoto(int $itemIndex, string $rowKey): void
    {
        $this->lampiran[$itemIndex][$rowKey] = $this->emptyFoto();
    }

    /**
     * File foto baru menggantikan yang lama → pratinjau crop & data crop lama tidak berlaku lagi.
     */
    public function updatedLampiran($value, ?string $key = null): void
    {
        if (! $key || ! str_ends_with($key, '.file')) {
            return;
        }

        [$itemIndex, $rowKey] = explode('.', $key);
        if (isset($this->lampiran[$itemIndex][$rowKey])) {
            $this->lampiran[$itemIndex][$rowKey]['preview'] = null;
            $this->lampiran[$itemIndex][$rowKey]['cropData'] = null;
            $this->lampiran[$itemIndex][$rowKey]['is_cropped'] = false;
        }
    }

    public function openCropper(int $itemIndex, string $rowKey): void
    {
        $file = $this->lampiran[$itemIndex][$rowKey]['file'] ?? null;
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
        $this->croppingRowKey = $rowKey;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $this->lampiran[$itemIndex][$rowKey]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function closeCropper(): void
    {
        $this->showCropperModal = false;
        $this->croppingItemIndex = null;
        $this->croppingRowKey = null;
        $this->croppingImageUrl = null;
        $this->cropData = [];
    }

    public function saveCrop(): void
    {
        if ($this->croppingItemIndex !== null && $this->croppingRowKey !== null && ! empty($this->cropData)) {
            $this->lampiran[$this->croppingItemIndex][$this->croppingRowKey]['cropData'] = $this->cropData;
            $this->lampiran[$this->croppingItemIndex][$this->croppingRowKey]['is_cropped'] = true;
            $this->notifySuccess('Crop berhasil disimpan.');
        }
        $this->closeCropper();
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

    public function addAktivitas(int $itemIndex, ?int $workGroupId = null): void
    {
        $row = app(LaporanHarianService::class)->newAktivitasRow($workGroupId);
        $this->items[$itemIndex]['aktivitas'][] = $row;
        $this->lampiran[$itemIndex][$row['row_key']] = $this->emptyFoto();
    }

    public function getDeletingAktivitasHasFotoProperty(): bool
    {
        $rowKey = $this->items[$this->deletingAktivitasItemIndex]['aktivitas'][$this->deletingAktivitasIndex]['row_key'] ?? null;

        return $rowKey && ! empty($this->lampiran[$this->deletingAktivitasItemIndex][$rowKey]['file']);
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
            unset($this->lampiran[$itemIndex][$this->items[$itemIndex]['aktivitas'][$aktivitasIndex]['row_key'] ?? '']);
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
            'lampiran.*.*.file' => [
                ...file_upload_validation_rule('foto_kapal'),
                // Foto wajib punya uraian aktivitas (keterangan foto = uraian aktivitas)
                function ($attribute, $value, $fail) {
                    [, $itemIndex, $rowKey] = explode('.', $attribute);
                    $row = collect($this->items[$itemIndex]['aktivitas'] ?? [])->firstWhere('row_key', $rowKey);
                    if ($value && trim((string) ($row['aktivitas'] ?? '')) === '') {
                        $fail('Uraian aktivitas untuk foto ini wajib diisi terlebih dahulu di tab Aktivitas.');
                    }
                },
            ],
            'lampiran.*.*.preview' => 'nullable|image|max:5120',
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
        $rules['items.*.aktivitas.*.work_group_id'] = 'nullable|integer';
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

            foreach (array_keys($this->lampiran[$index] ?? []) as $rowKey) {
                $attributes["lampiran.{$index}.{$rowKey}.file"] = "foto lampiran pada laporan #{$num}";
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
            $fotoCount = 0;

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

                // Uraian aktivitas per kategori + foto lampirannya
                $fotoCount += $service->syncAktivitas($laporan, $item['aktivitas'] ?? [], $this->lampiran[$index] ?? []);
            }

            $count = count($dataItems);
            $message = "{$count} laporan harian berhasil ditambahkan!";
            if ($fotoCount > 0) {
                $message .= ' Foto lampiran sedang diproses di background.';
            }

            $this->notifySuccess($message);

            $this->redirect(route('laporan-harian.index', $this->dataKapal), navigate: true);
        } catch (\Exception $e) {
            Log::error('LaporanHarianCreate: gagal menyimpan', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function cancel()
    {
        // Beri tahu Alpine untuk menghapus draft lokal sebelum navigate
        $this->dispatch('lh-clear-draft');

        return $this->redirect(route('laporan-harian.index', $this->dataKapal), navigate: true);
    }

    public function render(LaporanHarianService $service)
    {
        $cuacaList = Cuaca::active()->orderBy('nama')->get();
        $kelembabanList = Kelembaban::active()->orderBy('nama')->get();
        $kategoriOptions = $service->kategoriOptions($this->data_kapal_id);

        return view('livewire.laporan-harian.laporan-harian-create', [
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
            // Segmen uraian aktivitas per kategori untuk setiap tab hari
            'kategoriGroups' => array_map(
                fn ($item) => $service->groupRowsByKategori($item['aktivitas'] ?? [], $kategoriOptions),
                $this->items
            ),
        ]);
    }
}
