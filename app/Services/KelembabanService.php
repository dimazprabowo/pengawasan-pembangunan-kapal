<?php

namespace App\Services;

use App\Enums\KelembabanStatus;
use App\Models\Kelembaban;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KelembabanService
{
    use \App\Traits\SortsQuery;

    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Kelembaban::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nilai', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->when($statusFilter !== null && $statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            });

        $this->applySorting($query, [
            'nama' => 'kelembaban.nama',
            'status' => 'kelembaban.status',
            'updated_at' => 'kelembaban.updated_at',
        ], $sortField, $sortDir, 'nama', 'asc', 'kelembaban.id');

        return $query->paginate($perPage);
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
