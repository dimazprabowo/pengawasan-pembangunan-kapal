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
        $query = LaporanMingguan::with(['user', 'jenisKapal.company', 'jenisKapal.galangan', 'laporanHarian'])
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
        $laporanHarianIds = $data['laporan_harian_ids'] ?? [];
        $lampiranIds = $data['lampiran_ids'] ?? [];
        unset($data['laporan_harian_ids'], $data['lampiran_ids']);

        $laporanMingguan = LaporanMingguan::create($data);

        if (!empty($laporanHarianIds)) {
            $laporanMingguan->laporanHarian()->sync($laporanHarianIds);
        }

        if (!empty($lampiranIds)) {
            $laporanMingguan->lampiran()->sync($lampiranIds);
        }

        return $laporanMingguan;
    }

    public function update(LaporanMingguan $laporanMingguan, array $data): LaporanMingguan
    {
        $laporanHarianIds = $data['laporan_harian_ids'] ?? null;
        $lampiranIds = $data['lampiran_ids'] ?? null;
        unset($data['laporan_harian_ids'], $data['lampiran_ids']);

        $laporanMingguan->update($data);

        if ($laporanHarianIds !== null) {
            $laporanMingguan->laporanHarian()->sync($laporanHarianIds);
        }

        if ($lampiranIds !== null) {
            $laporanMingguan->lampiran()->sync($lampiranIds);
        }

        return $laporanMingguan;
    }

    public function delete(LaporanMingguan $laporanMingguan): void
    {
        $laporanMingguan->delete();
    }
}
