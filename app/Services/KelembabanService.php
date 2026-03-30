<?php

namespace App\Services;

use App\Enums\KelembabanStatus;
use App\Models\Kelembaban;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KelembabanService
{
    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        int $perPage = 15
    ): LengthAwarePaginator {
        return Kelembaban::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('nilai', 'like', "%{$search}%")
                      ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== null && $statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): Kelembaban
    {
        return Kelembaban::create($data);
    }

    public function update(Kelembaban $kelembaban, array $data): Kelembaban
    {
        $kelembaban->update($data);
        return $kelembaban->fresh();
    }

    public function delete(Kelembaban $kelembaban): bool
    {
        return $kelembaban->delete();
    }

    public function toggleStatus(Kelembaban $kelembaban): Kelembaban
    {
        $newStatus = $kelembaban->status === KelembabanStatus::Active
            ? KelembabanStatus::Inactive
            : KelembabanStatus::Active;

        $kelembaban->update(['status' => $newStatus]);

        return $kelembaban->fresh();
    }
}
