<?php

namespace App\Services;

use App\Enums\JenisKapalStatus;
use App\Models\JenisKapal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JenisKapalService
{
    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        ?int $companyFilter = null,
        ?int $galanganFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return JenisKapal::with(['company', 'galangan'])
            ->withCount('laporan')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('deskripsi', 'like', "%{$search}%")
                      ->orWhereHas('company', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                      });
                });
            })
            ->when($statusFilter !== null && $statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            })
            ->when($companyFilter, function ($q) use ($companyFilter) {
                $q->where('company_id', $companyFilter);
            })
            ->when($galanganFilter, function ($q) use ($galanganFilter) {
                $q->where('galangan_id', $galanganFilter);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): JenisKapal
    {
        return JenisKapal::create($data);
    }

    public function update(JenisKapal $jenisKapal, array $data): bool
    {
        return $jenisKapal->update($data);
    }

    public function delete(JenisKapal $jenisKapal): bool
    {
        return $jenisKapal->delete();
    }

    public function toggleStatus(JenisKapal $jenisKapal): bool
    {
        $newStatus = $jenisKapal->status === JenisKapalStatus::Active
            ? JenisKapalStatus::Inactive
            : JenisKapalStatus::Active;

        return $jenisKapal->update(['status' => $newStatus]);
    }
}
