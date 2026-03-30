<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyService
{
    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Company::withCount(['users', 'jenisKapal']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('pic_name', 'like', "%{$search}%");
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
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
