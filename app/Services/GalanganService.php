<?php

namespace App\Services;

use App\Enums\GalanganStatus;
use App\Models\Galangan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GalanganService
{
    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        int $perPage = 15
    ): LengthAwarePaginator {
        return Galangan::withCount('jenisKapal')
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
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): Galangan
    {
        return Galangan::create($data);
    }

    public function update(Galangan $galangan, array $data): Galangan
    {
        $galangan->update($data);
        return $galangan->fresh();
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
