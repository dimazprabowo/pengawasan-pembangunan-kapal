<?php

namespace App\Services;

use App\Models\Company;
use App\Traits\HasDynamicLike;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyService
{
    use \App\Traits\SortsQuery;
    use HasDynamicLike;

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = Company::with(['createdBy:id,name', 'updatedBy:id,name'])->withCount(['users', 'dataKapal']);

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
            'name' => 'companies.name',
            'npwp' => 'companies.npwp',
            'email' => 'companies.email',
            'pic_name' => 'companies.pic_name',
            'users_count' => 'users_count',
            'data_kapal_count' => 'data_kapal_count',
            'status' => 'companies.status',
            'updated_at' => 'companies.updated_at',
        ], $sortField, $sortDir, 'name', 'asc', 'companies.id');

        return $query->paginate($perPage);
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company;
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }

    public function toggleStatus(Company $company): Company
    {
        $newStatus = $company->status->value === 'active' ? 'inactive' : 'active';
        $company->update(['status' => $newStatus]);

        return $company;
    }
}
