<?php

namespace App\Services;

use App\Enums\GalanganStatus;
use App\Models\Galangan;
use App\Traits\SortsQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GalanganService
{
    use SortsQuery;

    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Galangan::with(['createdBy:id,name', 'updatedBy:id,name'])->withCount('dataKapal')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode', 'like', "%{$search}%")
                        ->orWhere('kota', 'like', "%{$search}%")
                        ->orWhere('provinsi', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== null && $statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            });

        $this->applySorting($query, [
            'nama' => 'galangan.nama',
            'kota' => 'galangan.kota',
            'data_kapal_count' => 'data_kapal_count',
            'status' => 'galangan.status',
            'updated_at' => 'galangan.updated_at',
        ], $sortField, $sortDir, 'nama', 'asc', 'galangan.id');

        return $query->paginate($perPage);
    }

    public function create(array $data): Galangan
    {
        return Galangan::create($this->normalize($data));
    }

    public function update(Galangan $galangan, array $data): Galangan
    {
        $galangan->update($this->normalize($data));

        return $galangan->fresh();
    }

    /**
     * Koordinat: kosong → null, dibulatkan 7 desimal (presisi kolom decimal(10,7)).
     */
    private function normalize(array $data): array
    {
        foreach (['latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] === null || $data[$field] === ''
                    ? null
                    : round((float) $data[$field], 7);
            }
        }

        return $data;
    }

    public function delete(Galangan $galangan): bool
    {
        return $galangan->delete();
    }

    public function toggleStatus(Galangan $galangan): Galangan
    {
        $newStatus = $galangan->status === GalanganStatus::Active
            ? GalanganStatus::Inactive
            : GalanganStatus::Active;

        $galangan->update(['status' => $newStatus]);

        return $galangan->fresh();
    }
}
