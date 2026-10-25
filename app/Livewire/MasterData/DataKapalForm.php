<?php

namespace App\Livewire\MasterData;

use App\Enums\DataKapalStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use App\Models\DataKapal;
use App\Models\Galangan;
use App\Services\DataKapalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Data Kapal'])]
class DataKapalForm extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?DataKapal $dataKapal = null;

    public bool $editMode = false;

    public $company_id;

    public $galangan_id;

    public $nama;

    public $deskripsi;

    public array $spesifikasi = [];

    public bool $showDeleteSpesifikasiModal = false;

    public ?int $deletingSpesifikasiIndex = null;

    public $status = 'active';

    // Preferensi autosave draft per user (persisten via cache)
    public bool $autoSave = true;

    public function mount(?DataKapal $dataKapal = null): void
    {
        $this->autoSave = Cache::get('dk_draft_autosave:'.auth()->id(), true);
        if ($dataKapal && $dataKapal->exists) {
            $this->authorize('update', $dataKapal);

            $this->dataKapal = $dataKapal;
            $this->editMode = true;
            $this->company_id = $dataKapal->company_id;
            $this->galangan_id = $dataKapal->galangan_id;
            $this->nama = $dataKapal->nama;
            $this->deskripsi = $dataKapal->deskripsi;
            $this->spesifikasi = collect($dataKapal->spesifikasi ?? [])
                ->map(fn ($row) => [
                    'row_key' => (string) Str::uuid(),
                    'item' => $row['item'] ?? '',
                    'keterangan' => $row['keterangan'] ?? '',
                ])->all();
            $this->status = $dataKapal->status->value;
        } else {
            $this->authorize('create', DataKapal::class);
        }
    }

    protected function rules(): array
    {
        return [
            'company_id' => 'nullable|exists:companies,id',
            'galangan_id' => 'nullable|exists:galangan,id',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'spesifikasi' => 'array',
            'spesifikasi.*.item' => 'required|string|max:100|distinct',
            'spesifikasi.*.keterangan' => 'required|string|max:255',
            'status' => ['required', 'string', 'in:'.implode(',', DataKapalStatus::values())],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'company_id' => 'perusahaan',
            'galangan_id' => 'galangan',
            'nama' => 'nama kapal',
            'deskripsi' => 'deskripsi',
            'spesifikasi.*.item' => 'item spesifikasi',
            'spesifikasi.*.keterangan' => 'keterangan spesifikasi',
            'status' => 'status',
        ];
    }

    public function addSpesifikasi(): void
    {
        $this->spesifikasi[] = [
            'row_key' => (string) Str::uuid(),
            'item' => '',
            'keterangan' => '',
        ];
    }

    public function confirmRemoveSpesifikasi(int $index): void
    {
        $this->deletingSpesifikasiIndex = $index;
        $this->showDeleteSpesifikasiModal = true;
    }

    public function removeSpesifikasiConfirmed(): void
    {
        if ($this->deletingSpesifikasiIndex !== null) {
            $this->removeSpesifikasi($this->deletingSpesifikasiIndex);
        }
        $this->showDeleteSpesifikasiModal = false;
        $this->deletingSpesifikasiIndex = null;
        $this->notifySuccess('Item spesifikasi berhasil dihapus');
    }

    public function removeSpesifikasi(int $index): void
    {
        if (isset($this->spesifikasi[$index])) {
            unset($this->spesifikasi[$index]);
            $this->spesifikasi = array_values($this->spesifikasi);
        }
    }

    public function save(DataKapalService $service)
    {
        // Buang baris spesifikasi yang masih kosong total sebelum validasi
        $this->spesifikasi = collect($this->spesifikasi)
            ->filter(fn ($row) => trim((string) ($row['item'] ?? '')) !== ''
                || trim((string) ($row['keterangan'] ?? '')) !== '')
            ->values()
            ->all();

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'company_id' => $this->company_id ?: null,
                'galangan_id' => $this->galangan_id ?: null,
                'nama' => $this->nama,
                'deskripsi' => $this->deskripsi,
                'spesifikasi' => $this->spesifikasi,
                'status' => $this->status,
            ];

            if ($this->editMode) {
                $this->authorize('update', $this->dataKapal);
                $service->update($this->dataKapal, $data);
                $message = 'Kapal berhasil diupdate!';
            } else {
                $this->authorize('create', DataKapal::class);
                $service->create($data);
                $message = 'Kapal berhasil ditambahkan!';
            }

            $this->notifySuccess($message);

            return $this->redirect(route('master-data.data-kapal'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            \Log::error('Simpan data kapal gagal', ['error' => $e->getMessage()]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function toggleAutoSave(): void
    {
        $this->autoSave = ! $this->autoSave;
        Cache::forever('dk_draft_autosave:'.auth()->id(), $this->autoSave);
    }

    public function cancel()
    {
        // Buang draft lokal sebelum navigate kembali ke index
        $this->dispatch('dk-clear-draft');

        return $this->redirect(route('master-data.data-kapal'), navigate: true);
    }

    public function render()
    {
        return view('livewire.master-data.data-kapal-form', [
            'statuses' => DataKapalStatus::cases(),
            'companies' => Company::active()->orderBy('name')->get(),
            'galangans' => Galangan::active()->orderBy('nama')->get(),
        ]);
    }
}
