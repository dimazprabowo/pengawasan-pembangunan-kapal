<?php

namespace App\Services;

use App\Models\LaporanBulanan;
use App\Models\LaporanMingguan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaporanBulananService
{
    use \App\Traits\SortsQuery;

    public function getFiltered(
        ?string $search = null,
        ?int $dataKapalId = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = LaporanBulanan::with(['user', 'updatedBy:id,name', 'dataKapal.company', 'dataKapal.galangan'])
            ->byDataKapal($dataKapalId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ringkasan', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });

                if (is_numeric($search)) {
                    $q->orWhere('bulan_ke', (int) $search);
                }
            });
        }

        $this->applySorting($query, [
            'bulan_ke' => 'laporan_bulanan.bulan_ke',
            'tanggal_laporan' => 'laporan_bulanan.tanggal_laporan',
            'updated_at' => 'laporan_bulanan.updated_at',
        ], $sortField, $sortDir, 'bulan_ke', 'desc', 'laporan_bulanan.created_at', 'desc');

        return $query
            ->paginate($perPage);
    }

    /**
     * Laporan mingguan yang masuk ke bulan_ke tertentu pada data kapal ini
     * (berdasarkan pengelompokan kontrak), diurutkan per minggu.
     */
    public function getMingguanBulan(LaporanBulanan $laporan): Collection
    {
        $bulan = app(KontrakPeriodeService::class)
            ->getBulanPeriode($laporan->dataKapal, (int) $laporan->bulan_ke);

        if (! $bulan) {
            return collect();
        }

        return LaporanMingguan::where('data_kapal_id', $laporan->data_kapal_id)
            ->whereBetween('minggu_ke', [$bulan['minggu_dari'], $bulan['minggu_sampai']])
            ->withSum('laporanProgress as total_progress', 'pct_realisasi')
            ->orderBy('minggu_ke')
            ->get();
    }

    /**
     * Cek bulan_ke sudah dipakai laporan bulanan lain pada data kapal ini.
     */
    public function bulanTaken(int $dataKapalId, int $bulanKe, ?int $exceptId = null): bool
    {
        return LaporanBulanan::where('data_kapal_id', $dataKapalId)
            ->where('bulan_ke', $bulanKe)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    public function create(array $data): LaporanBulanan
    {
        return DB::transaction(fn () => LaporanBulanan::create($data));
    }

    public function update(LaporanBulanan $laporanBulanan, array $data): LaporanBulanan
    {
        return DB::transaction(function () use ($laporanBulanan, $data) {
            $laporanBulanan->update($data);

            return $laporanBulanan;
        });
    }

    /**
     * Hapus laporan beserta file laporan external miliknya.
     */
    public function delete(LaporanBulanan $laporanBulanan): void
    {
        DB::transaction(function () use ($laporanBulanan) {
            foreach ($laporanBulanan->laporanExternal as $external) {
                if ($external->file_path) {
                    Storage::disk('local')->delete($external->file_path);
                }
                $external->delete();
            }

            $laporanBulanan->delete();
        });
    }
}
