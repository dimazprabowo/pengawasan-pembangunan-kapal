<?php

namespace App\Livewire\LaporanHarian;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Models\DataKapal;
use App\Models\Kelembaban;
use App\Models\LaporanHarian;
use App\Services\LaporanHarianService;
use App\Services\LaporanNcService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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

    // Foto bukti (open) per NC - struktur: [itemIndex => [row_key => ['file' => null, 'cropData' => null, 'is_cropped' => false]]]
    public array $ncFoto = [];

    // Delete NC confirmation modal
    public bool $showDeleteNcModal = false;

    public ?int $deletingNcItemIndex = null;

    public ?int $deletingNcIndex = null;

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

    // Konteks crop: 'lampiran' (foto aktivitas) / 'nc' (foto bukti NC)
    public ?string $croppingContext = null;

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
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
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
        $this->authorize('create', LaporanHarian::class);
        $this->authorizeDataKapalAccess($this->dataKapal);
        // Auto-increment: scan dari awal periode dan ambil tanggal kosong PERTAMA
        // (celah di tengah karena laporan dihapus terisi dulu; tanpa celah hasilnya
        // otomatis = tanggal laporan terakhir + 1). Batas atas = min(hari ini,
        // tanggal selesai pelaksanaan). Tanggal terpakai = di DB maupun tab lain.
        $taken = $this->takenTanggalLaporan();
        $today = now()->startOfDay();
        $selesaiPelaksanaan = $this->dataKapal->tanggal_selesai_pelaksanaan?->copy()->startOfDay();
        $maxTanggal = $selesaiPelaksanaan && $selesaiPelaksanaan->lt($today) ? $selesaiPelaksanaan : $today;

        $mulai = $this->dataKapal->tanggal_mulai_pelaksanaan?->copy()->startOfDay()
            ?? LaporanHarian::where('data_kapal_id', $this->data_kapal_id)->min('tanggal_laporan')
            ?? $today->copy();
        $candidate = Carbon::parse($mulai)->startOfDay();
        while ($candidate->lte($maxTanggal) && in_array($candidate->format('Y-m-d'), $taken, true)) {
            $candidate->addDay();
        }

        if ($candidate->gt($maxTanggal)) {
            $this->notifyWarning('Semua tanggal sampai batas pelaporan ('.$maxTanggal->translatedFormat('d M Y').') sudah memiliki laporan.');

            return;
        }

        $tanggalLaporan = $candidate->format('Y-m-d');
        $defaults = app(LaporanHarianService::class)->previousDayDefaults($this->dataKapal, $tanggalLaporan, $this->items);

        $this->items[] = array_replace([
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
            'nc' => [],
            'is_libur' => false,
            'alasan_libur' => '',
            'default_info' => $defaults['info'],
        ], $defaults['values']);
        $this->lampiran[] = [];
        $this->ncFoto[] = [];

        $this->activeTab = count($this->items) - 1;
        $this->dispatch('laporan-item-added', index: $this->activeTab);
    }

    public function updatedItems(mixed $value, ?string $key = null): void
    {
        if ($key && str_ends_with($key, '.alasan_libur')) {
            $this->validateOnly('items.'.$key);
        }
    }

    public function toggleLibur(int $index): void
    {
        $this->authorize('create', LaporanHarian::class);
        $this->authorizeDataKapalAccess($this->dataKapal);
        abort_unless(isset($this->items[$index]), 404);
        $this->items[$index]['is_libur'] = ! ($this->items[$index]['is_libur'] ?? false);
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
        unset($this->ncFoto[$index]);
        $this->items = array_values($this->items);
        $this->lampiran = array_values($this->lampiran);
        $this->ncFoto = array_values($this->ncFoto);

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
        $this->croppingContext = 'lampiran';
        $this->croppingRowKey = $rowKey;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $this->lampiran[$itemIndex][$rowKey]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    /**
     * File foto baru menggantikan yang lama → pratinjau crop & data crop lama tidak berlaku lagi.
     */
    public function updatedNcFoto($value, ?string $key = null): void
    {
        if (! $key || ! str_ends_with($key, '.file')) {
            return;
        }

        [$itemIndex, $rowKey] = explode('.', $key);
        if (isset($this->ncFoto[$itemIndex][$rowKey])) {
            $this->ncFoto[$itemIndex][$rowKey]['preview'] = null;
            $this->ncFoto[$itemIndex][$rowKey]['cropData'] = null;
            $this->ncFoto[$itemIndex][$rowKey]['is_cropped'] = false;
        }
    }

    public function removeNcFoto(int $itemIndex, string $rowKey): void
    {
        $this->ncFoto[$itemIndex][$rowKey] = $this->emptyFoto();
    }

    public function openNcCropper(int $itemIndex, string $rowKey): void
    {
        $file = $this->ncFoto[$itemIndex][$rowKey]['file'] ?? null;
        if (! $file || ! is_object($file)) {
            $this->notifyWarning('File tidak valid, silakan upload ulang.');

            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $this->notifyWarning('Crop hanya tersedia untuk file gambar (JPG, PNG, WEBP).');

            return;
        }

        $this->croppingItemIndex = $itemIndex;
        $this->croppingContext = 'nc';
        $this->croppingRowKey = $rowKey;
        $this->croppingImageUrl = $file->temporaryUrl();
        $this->cropData = $this->ncFoto[$itemIndex][$rowKey]['cropData'] ?? [];
        $this->showCropperModal = true;
    }

    public function closeCropper(): void
    {
        $this->showCropperModal = false;
        $this->croppingItemIndex = null;
        $this->croppingContext = null;
        $this->croppingRowKey = null;
        $this->croppingImageUrl = null;
        $this->cropData = [];
    }

    public function saveCrop(): void
    {
        if ($this->croppingItemIndex !== null && $this->croppingRowKey !== null && ! empty($this->cropData)) {
            $target = $this->croppingContext === 'nc' ? $this->ncFoto : $this->lampiran;
            $target[$this->croppingItemIndex][$this->croppingRowKey]['cropData'] = $this->cropData;
            $target[$this->croppingItemIndex][$this->croppingRowKey]['is_cropped'] = true;
            if ($this->croppingContext === 'nc') {
                $this->ncFoto = $target;
            } else {
                $this->lampiran = $target;
            }
            $this->notifySuccess('Crop berhasil disimpan.');
        }
        $this->closeCropper();
    }

    // Dynamic input management methods
    public function addPersonel(int $itemIndex): void
    {
        $this->items[$itemIndex]['personel'][] = [
            'jabatan' => '',
            'jumlah' => '',
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

    // ── NC (Non-Conformity) ──────────────────────────────────────────────
    public function addNc(int $itemIndex, ?int $workGroupId = null): void
    {
        $row = app(LaporanNcService::class)->newNcRow($workGroupId);
        $this->items[$itemIndex]['nc'][] = $row;
        $this->ncFoto[$itemIndex][$row['row_key']] = $this->emptyFoto();
    }

    public function confirmRemoveNc(int $itemIndex, int $ncIndex): void
    {
        $this->deletingNcItemIndex = $itemIndex;
        $this->deletingNcIndex = $ncIndex;
        $this->showDeleteNcModal = true;
    }

    public function removeNcConfirmed(): void
    {
        if ($this->deletingNcItemIndex !== null && $this->deletingNcIndex !== null) {
            $this->removeNc($this->deletingNcItemIndex, $this->deletingNcIndex);
        }
        $this->showDeleteNcModal = false;
        $this->deletingNcItemIndex = null;
        $this->deletingNcIndex = null;
        $this->notifySuccess('NC berhasil dihapus');
    }

    public function removeNc(int $itemIndex, int $ncIndex): void
    {
        if (isset($this->items[$itemIndex]['nc'][$ncIndex])) {
            $rowKey = $this->items[$itemIndex]['nc'][$ncIndex]['row_key'];
            $this->ncFoto[$itemIndex] = array_filter($this->ncFoto[$itemIndex] ?? [], fn ($key) => $key !== $rowKey && ! str_starts_with($key, $rowKey.'_'), ARRAY_FILTER_USE_KEY);
            unset($this->items[$itemIndex]['nc'][$ncIndex]);
            $this->items[$itemIndex]['nc'] = array_values($this->items[$itemIndex]['nc']);
        }
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
                function ($attribute, $value, $fail) {
                    $mulaiPelaksanaan = $this->dataKapal->tanggal_mulai_pelaksanaan;
                    if ($value && $mulaiPelaksanaan && Carbon::parse($value)->lt($mulaiPelaksanaan)) {
                        $fail('Tanggal laporan tidak boleh sebelum tanggal mulai pelaksanaan ('.$mulaiPelaksanaan->translatedFormat('d M Y').').');
                    }
                },
                function ($attribute, $value, $fail) {
                    $selesaiPelaksanaan = $this->dataKapal->tanggal_selesai_pelaksanaan;
                    if ($value && $selesaiPelaksanaan && Carbon::parse($value)->gt($selesaiPelaksanaan)) {
                        $fail('Tanggal laporan tidak boleh melebihi tanggal selesai pelaksanaan ('.$selesaiPelaksanaan->translatedFormat('d M Y').').');
                    }
                },
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
            'ncFoto.*.*.file' => [
                ...file_upload_validation_rule('laporan_nc_file'),
                // Foto bukti wajib punya kendala NC (keterangan foto = kendala)
                function ($attribute, $value, $fail) {
                    [, $itemIndex, $rowKey] = explode('.', $attribute);
                    $row = collect($this->items[$itemIndex]['nc'] ?? [])->firstWhere('row_key', explode('_', $rowKey)[0]);
                    if ($value && trim((string) ($row['kendala'] ?? '')) === '') {
                        $fail('Kendala NC untuk foto bukti ini wajib diisi terlebih dahulu di tab NC.');
                    }
                },
            ],
            'ncFoto.*.*.preview' => 'nullable|image|max:5120',
        ];

        $rules['items.*.is_libur'] = 'sometimes|boolean';
        $rules['items.*.alasan_libur'] = 'nullable|required_if:items.*.is_libur,true|string|max:1000';
        $rules['items.*.suhu'] = 'nullable|numeric|min:-50|max:100';
        $rules['items.*.cuaca_pagi_id'] = 'nullable|exists:cuaca,id';
        $rules['items.*.kelembaban_pagi_id'] = 'nullable|exists:kelembaban,id';
        $rules['items.*.cuaca_siang_id'] = 'nullable|exists:cuaca,id';
        $rules['items.*.kelembaban_siang_id'] = 'nullable|exists:kelembaban,id';
        $rules['items.*.cuaca_sore_id'] = 'nullable|exists:cuaca,id';
        $rules['items.*.kelembaban_sore_id'] = 'nullable|exists:kelembaban,id';
        $rules['items.*.personel'] = 'nullable|array';
        $rules['items.*.personel.*.jabatan'] = 'nullable|string|max:255';
        $rules['items.*.personel.*.jumlah'] = 'nullable|integer|min:1';
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
        $rules['items.*.aktivitas.*.keterangan'] = 'nullable|string|max:255';
        $rules['items.*.nc'] = 'nullable|array';
        $rules['items.*.nc.*.work_group_id'] = 'nullable|integer';
        $rules['items.*.nc.*.kendala'] = 'nullable|string|max:2000';
        $rules['items.*.nc.*.tindakan'] = 'nullable|string|max:2000';
        $rules['items.*.nc.*.pic'] = 'nullable|string|max:255';

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

            foreach (array_keys($this->ncFoto[$index] ?? []) as $rowKey) {
                $attributes["ncFoto.{$index}.{$rowKey}.file"] = "foto bukti NC pada laporan #{$num}";
            }

            $attributes["items.{$index}.is_libur"] = "status hari libur laporan #{$num}";
            $attributes["items.{$index}.alasan_libur"] = "alasan libur laporan #{$num}";
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
        $this->authorizeDataKapalAccess($this->dataKapal);
        abort_unless($this->data_kapal_id === $this->dataKapal->id, 403);

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            $firstKey = collect($e->validator->errors()->keys())
                ->first(fn ($k) => str_starts_with($k, 'items.') || str_starts_with($k, 'lampiran.') || str_starts_with($k, 'ncFoto.'));
            if ($firstKey && preg_match('/^(items|lampiran|ncFoto)\.(\d+)/', $firstKey, $m)) {
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
                    'is_libur' => $item['is_libur'] ?? false,
                    'alasan_libur' => $item['alasan_libur'] ?? null,
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

            // Process dynamic inputs and lampiran for each created laporan
            // Save dynamic inputs
            // Save personel - allow partial data (any field filled)
            // Save peralatan - allow partial data (any field filled)
            // Save consumable - allow partial data (any field filled)
            // Uraian aktivitas per kategori + foto lampirannya
            // NC + foto bukti (open); dokumen close tetap diunggah via List NC
            [$createdLaporans, $fotoCount] = $service->saveFormBatch($dataItems, $this->items, $this->lampiran, $this->ncFoto);

            $count = count($dataItems);
            $message = "{$count} laporan harian berhasil ditambahkan!";
            if ($fotoCount > 0) {
                $message .= ' Foto lampiran sedang diproses di background.';
            }

            $this->notifySuccess($message);

            if (count($createdLaporans) === 1) {
                $this->redirect(route('laporan-harian.show', [$this->dataKapal, $createdLaporans[0]]), navigate: true);
            } else {
                $this->redirect(route('laporan-harian.index', $this->dataKapal), navigate: true);
            }
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
            // NC per kategori (Work Group) untuk setiap tab hari
            'ncGroups' => array_map(
                fn ($item) => $service->groupRowsByKategori($item['nc'] ?? [], $kategoriOptions),
                $this->items
            ),
        ]);
    }
}
