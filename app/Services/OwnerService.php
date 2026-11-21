<?php

namespace App\Services;

use App\Models\Owner;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Illuminate\Pagination\LengthAwarePaginator;

class OwnerService
{
    use HasDynamicLike;
    use SortsQuery;

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Owner::with(['createdBy:id,name', 'updatedBy:id,name'])->withCount(['users', 'dataKapal']);

        if ($search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($search, $operator) {
                $q->where('code', $operator, "%{$search}%")
                    ->orWhere('name', $operator, "%{$search}%")
                    ->orWhere('email', $operator, "%{$search}%")
                    ->orWhere('phone', $operator, "%{$search}%")
                    ->orWhere('pic_name', $operator, "%{$search}%");
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $this->applySorting($query, [
            'name' => 'owners.name',
            'npwp' => 'owners.npwp',
            'email' => 'owners.email',
            'pic_name' => 'owners.pic_name',
            'users_count' => 'users_count',
            'data_kapal_count' => 'data_kapal_count',
            'status' => 'owners.status',
            'updated_at' => 'owners.updated_at',
        ], $sortField, $sortDir, 'name', 'asc', 'owners.id');

        return $query->paginate($perPage);
    }

    public function create(array $data): Owner
    {
        return Owner::create($data);
    }

    public function update(Owner $owner, array $data): Owner
    {
        $owner->update($data);

        return $owner;
    }

    public function delete(Owner $owner): void
    {
        $owner->delete();
    }

    public function toggleStatus(Owner $owner): Owner
    {
        $newStatus = $owner->status->value === 'active' ? 'inactive' : 'active';
        $owner->update(['status' => $newStatus]);

        return $owner;
    }
}
