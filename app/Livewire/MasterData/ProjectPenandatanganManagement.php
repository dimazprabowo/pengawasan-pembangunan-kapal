<?php

namespace App\Livewire\MasterData;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use App\Services\DataProyekService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tab Penandatangan: daftar penandatangan lembar pengesahan laporan
 * bulanan (JSON data_kapal.penandatangan — urutan baris = urutan TTD).
 * Anchor policy DataProyekPolicy via DrawingRegister (pola shell tab).
 * Pola Excel-like seperti tab lain: $saved = snapshot persisten terurut,
 * $drafts = perubahan per baris (key 'row-{key}' / 'new-{uuid}').
 */
class ProjectPenandatanganManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithPagination;

    #[Locked]
    public DataKapal $dataKapal;

    public array $saved = [];

    public array $drafts = [];

    public string $search = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    public bool $showDeleteModal = false;

    public ?string $deletingKey = null;

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', DrawingRegister::class);
        $this->authorizeDataKapalAccess($dataKapal);

        $this->dataKapal = $dataKapal;
        $this->saved = collect($dataKapal->penandatangan ?? [])
            ->map(fn ($row) => [
                'key' => (string) Str::uuid(),
                'label' => $row['label'] ?? '',
                'nama' => $row['nama'] ?? '',
                'jabatan' => $row['jabatan'] ?? '',
                'instansi' => $row['instansi'] ?? '',
            ])->all();
    }

    protected function rules(): array
    {
        return [
            'drafts.*.label' => 'nullable|string|max:100',
            'drafts.*.nama' => 'required|string|max:255',
            'drafts.*.jabatan' => 'nullable|string|max:255',
            'drafts.*.instansi' => 'nullable|string|max:255',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'drafts.*.label' => 'label penandatangan',
            'drafts.*.nama' => 'nama penandatangan',
            'drafts.*.jabatan' => 'jabatan penandatangan',
            'drafts.*.instansi' => 'instansi penandatangan',
        ];
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'drafts.')) {
            $parts = explode('.', $property);
            if (isset($parts[1])) {
                $this->drafts[$parts[1]]['dirty'] = true;
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->markFiltersChanged();
    }

    public function updatingPerPage(): void
    {
        $this->markFiltersChanged();
    }

    private function markFiltersChanged(): void
    {
        $this->resetPage('ttdPage');
        $this->filterChanged = true;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'perPage']);
        $this->resetPage('ttdPage');
        $this->filterChanged = true;
    }

    public function warnUnsaved(): void
    {
        $this->notifyWarning('Simpan atau batalkan perubahan penandatangan sebelum berpindah halaman/tab.');
    }

    public function addRow(): void
    {
        $this->authorize('update', DrawingRegister::class);
        $this->drafts['new-'.Str::uuid()] = ['label' => '', 'nama' => '', 'jabatan' => '', 'instansi' => '', 'dirty' => true];
    }

    public function cancelRow(string $key): void
    {
        $this->authorize('viewAny', DrawingRegister::class);
        unset($this->drafts[$key]);
        $this->dispatch('ttd-row-saved', key: $key);
        $this->resetValidation();
    }

    public function saveRow(string $key, DataProyekService $service): void
    {
        try {
            if (! isset($this->drafts[$key])) {
                return;
            }
            $this->authorize('update', DrawingRegister::class);
            $rules = [];
            foreach ($this->rules() as $field => $rule) {
                $rules[str_replace('.*.', '.'.$key.'.', $field)] = $rule;
            }
            $this->validate($rules);

            $row = $this->drafts[$key];
            $isNew = str_starts_with($key, 'new-');
            $saved = $this->saved;
            if ($isNew) {
                $saved[] = ['key' => (string) Str::uuid()] + Arr::only($row, ['label', 'nama', 'jabatan', 'instansi']);
            } else {
                $rowKey = substr($key, 4);
                $saved = collect($saved)->map(fn ($s) => $s['key'] === $rowKey
                    ? ['key' => $rowKey] + Arr::only($row, ['label', 'nama', 'jabatan', 'instansi'])
                    : $s)->all();
            }
            $service->savePenandatangan($this->dataKapal, $saved);
            $this->saved = $saved;
            unset($this->drafts[$key]);
            $this->dispatch('ttd-row-saved', key: $key);
            $this->notifySuccess('Data penandatangan berhasil disimpan.');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'drafts.') ? $field : "drafts.$key.$field"] = $errors;
            }
            $error = ValidationException::withMessages($messages);
            $this->notifyValidationError($error);
            throw $error;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menyimpan penandatangan.');
        } catch (\Exception $e) {
            Log::error('Penandatangan save failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDelete(string $key): void
    {
        $this->authorize('update', DrawingRegister::class);
        $this->deletingKey = $key;
        $this->showDeleteModal = true;
    }

    public function delete(DataProyekService $service): void
    {
        if (! $this->deletingKey) {
            $this->notifyWarning('Pilih data yang akan dihapus terlebih dahulu.');

            return;
        }

        try {
            $this->authorize('update', DrawingRegister::class);
            $rowKey = $this->deletingKey;
            $saved = collect($this->saved)->reject(fn ($s) => $s['key'] === $rowKey)->values()->all();
            $service->savePenandatangan($this->dataKapal, $saved);
            $this->saved = $saved;
            unset($this->drafts['row-'.$rowKey]);
            $this->dispatch('ttd-row-saved', key: 'row-'.$rowKey);
            $this->reset(['showDeleteModal', 'deletingKey']);
            $this->notifySuccess('Data penandatangan berhasil dihapus.');
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk menghapus data.');
        } catch (\Exception $e) {
            Log::error('Penandatangan delete failed', ['exception' => $e]);
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function render()
    {
        $term = mb_strtolower(trim($this->search));
        $matches = fn ($fields) => $term === '' || Str::contains(
            mb_strtolower(implode(' ', [$fields['label'] ?? '', $fields['nama'] ?? '', $fields['jabatan'] ?? '', $fields['instansi'] ?? ''])),
            $term
        );

        // Baris persisten yang lolos filter, dipaginasi per posisi.
        $filtered = collect($this->saved)->filter(fn ($s) => $matches($this->drafts['row-'.$s['key']] ?? $s));
        $page = LengthAwarePaginator::resolveCurrentPage('ttdPage');
        $items = new LengthAwarePaginator(
            $filtered->forPage($page, $this->perPage)->values()->all(),
            $filtered->count(),
            $this->perPage,
            $page,
            ['pageName' => 'ttdPage']
        );

        // Draft terlihat: isi draft dari baris persisten yang tampil; pertahankan
        // draft dirty/baru agar tidak hilang saat filter/paginasi (pola vendor).
        $visibleKeys = collect($items->items())->map(fn ($s) => 'row-'.$s['key'])->all();
        foreach ($items->items() as $s) {
            $key = 'row-'.$s['key'];
            if (! ($this->drafts[$key]['dirty'] ?? false)) {
                $this->drafts[$key] = ['label' => $s['label'], 'nama' => $s['nama'], 'jabatan' => $s['jabatan'], 'instansi' => $s['instansi'], 'dirty' => false];
            }
        }
        $rows = collect($this->drafts)->filter(fn ($row, $key) => str_starts_with($key, 'new-') || ($row['dirty'] ?? false) && ! in_array($key, $visibleKeys, true))->all();
        foreach ($items->items() as $s) {
            $key = 'row-'.$s['key'];
            $rows[$key] = $this->drafts[$key];
        }
        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$items->total()} data penandatangan.");
            $this->filterChanged = false;
        }
        $this->drafts = array_intersect_key($this->drafts, $rows);

        // Nomor urut mengikuti posisi persisten (urutan TTD), bukan posisi halaman.
        $positions = collect($this->saved)->mapWithKeys(fn ($s, $i) => [$s['key'] => $i + 1])->all();

        return view('livewire.master-data.project-penandatangan-management', [
            'items' => $items,
            'rows' => $rows,
            'positions' => $positions,
        ]);
    }
}
