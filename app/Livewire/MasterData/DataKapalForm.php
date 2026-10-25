<?php

namespace App\Livewire\MasterData;

use App\Enums\DataKapalStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use App\Models\DataKapal;
use App\Models\Galangan;
use App\Services\DataKapalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

    public $status = 'active';

    public function mount(?DataKapal $dataKapal = null): void
    {
        if ($dataKapal && $dataKapal->exists) {
            $this->authorize('update', $dataKapal);

            $this->dataKapal = $dataKapal;
            $this->editMode = true;
            $this->company_id = $dataKapal->company_id;
            $this->galangan_id = $dataKapal->galangan_id;
            $this->nama = $dataKapal->nama;
            $this->deskripsi = $dataKapal->deskripsi;
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
            'status' => 'status',
        ];
    }

    public function save(DataKapalService $service)
    {
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

    public function cancel()
    {
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
