<?php

namespace App\Services;

use App\Models\LaporanMingguan;
use Illuminate\Pagination\LengthAwarePaginator;

class LaporanMingguanService
{
    public function getFiltered(
        ?string $search = null,
        ?int $jenisKapalId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = LaporanMingguan::with(['user', 'jenisKapal.company', 'jenisKapal.galangan'])
            ->byJenisKapal($jenisKapalId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): LaporanMingguan
    {
        return LaporanMingguan::create($data);
    }

    public function update(LaporanMingguan $laporanMingguan, array $data): LaporanMingguan
    {
        $laporanMingguan->update($data);
        return $laporanMingguan;
    }

    public function delete(LaporanMingguan $laporanMingguan): void
    {
        $laporanMingguan->delete();
    }
}
