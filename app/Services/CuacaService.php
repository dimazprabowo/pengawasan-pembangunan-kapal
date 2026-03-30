<?php

namespace App\Services;

use App\Enums\CuacaStatus;
use App\Models\Cuaca;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CuacaService
{
    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        int $perPage = 15
    ): LengthAwarePaginator {
        return Cuaca::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== null && $statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): Cuaca
    {
        return Cuaca::create($data);
    }

    public function update(Cuaca $cuaca, array $data): Cuaca
    {
        $cuaca->update($data);
        return $cuaca->fresh();
    }

    public function delete(Cuaca $cuaca): bool
    {
        return $cuaca->delete();
    }

    public function toggleStatus(Cuaca $cuaca): Cuaca
    {
        $newStatus = $cuaca->status === CuacaStatus::Active
            ? CuacaStatus::Inactive
            : CuacaStatus::Active;

        $cuaca->update(['status' => $newStatus]);

        return $cuaca->fresh();
    }
}
