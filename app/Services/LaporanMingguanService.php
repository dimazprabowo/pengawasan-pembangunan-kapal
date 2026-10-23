<?php

namespace App\Services;

use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
                $q->where('ringkasan', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });

                if (is_numeric($search)) {
                    $q->orWhere('minggu_ke', (int) $search);
                }
            });
        }

        return $query->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Laporan harian dalam rentang periode untuk dipilih di form mingguan.
     * Return: list ['id', 'judul', 'tanggal', 'claimed_label']
     */
    public function getAvailableHarianForPeriode(
        int $jenisKapalId,
        string $periodeMulai,
        string $periodeSelesai,
        ?int $exceptMingguanId = null
    ): array {
        $claimLabels = $this->getHarianClaimLabels($jenisKapalId, $exceptMingguanId);

        return LaporanHarian::where('jenis_kapal_id', $jenisKapalId)
            ->whereDate('tanggal_laporan', '>=', $periodeMulai)
            ->whereDate('tanggal_laporan', '<=', $periodeSelesai)
            ->orderByDesc('tanggal_laporan')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'judul' => $item->judul,
                'tanggal' => $item->tanggal_laporan->format('d M Y'),
                'claimed_label' => $claimLabels[$item->id] ?? null,
            ])
            ->all();
    }

    /**
     * Laporan harian yang sudah diklaim laporan mingguan lain.
     * Return: list grup per mingguan — ['mingguan_id', 'judul', 'minggu_ke', 'periode', 'dates' => []]
     */
    public function findHarianConflicts(array $harianIds, ?int $exceptMingguanId = null): Collection
    {
        if (empty($harianIds)) {
            return collect();
        }

        $harians = LaporanHarian::whereIn('id', $harianIds)
            ->whereHas('laporanMingguan', function ($q) use ($exceptMingguanId) {
                $q->when($exceptMingguanId, fn ($q2) => $q2->where('laporan_mingguan.id', '!=', $exceptMingguanId));
            })
            ->with(['laporanMingguan' => function ($q) use ($exceptMingguanId) {
                $q->when($exceptMingguanId, fn ($q2) => $q2->where('laporan_mingguan.id', '!=', $exceptMingguanId));
            }])
            ->orderBy('tanggal_laporan')
            ->get();

        $groups = [];
        foreach ($harians as $harian) {
            foreach ($harian->laporanMingguan as $mingguan) {
                $groups[$mingguan->id]['mingguan_id'] = $mingguan->id;
                $groups[$mingguan->id]['judul'] = $mingguan->judul;
                $groups[$mingguan->id]['minggu_ke'] = $mingguan->minggu_ke;
                $groups[$mingguan->id]['periode'] = trim(
                    ($mingguan->periode_mulai?->translatedFormat('d M Y') ?? '').' - '.($mingguan->periode_selesai?->translatedFormat('d M Y') ?? ''),
                    ' -'
                );
                $groups[$mingguan->id]['dates'][] = $harian->tanggal_laporan->translatedFormat('d M Y');
            }
        }

        return collect(array_values($groups));
    }

    /**
     * Label klaim mingguan untuk tiap laporan harian pada sebuah jenis kapal.
     * Return: [laporan_harian_id => "Minggu ke-N, ..."]
     */
    public function getHarianClaimLabels(int $jenisKapalId, ?int $exceptMingguanId = null): array
    {
        return DB::table('laporan_mingguan_harian')
            ->join('laporan_mingguan', 'laporan_mingguan.id', '=', 'laporan_mingguan_harian.laporan_mingguan_id')
            ->where('laporan_mingguan.jenis_kapal_id', $jenisKapalId)
            ->when($exceptMingguanId, fn ($q) => $q->where('laporan_mingguan.id', '!=', $exceptMingguanId))
            ->select('laporan_mingguan_harian.laporan_harian_id', 'laporan_mingguan.minggu_ke')
            ->get()
            ->groupBy('laporan_harian_id')
            ->map(fn ($rows) => $rows
                ->map(fn ($r) => 'Minggu ke-'.$r->minggu_ke)
                ->unique()
                ->implode(', '))
            ->all();
    }

    /**
     * Lepas laporan harian dari laporan mingguan lain (kecuali $exceptMingguanId).
     */
    public function releaseHarianFromOtherMingguan(array $harianIds, ?int $exceptMingguanId = null): void
    {
        if (empty($harianIds)) {
            return;
        }

        DB::table('laporan_mingguan_harian')
            ->whereIn('laporan_harian_id', $harianIds)
            ->when($exceptMingguanId, fn ($q) => $q->where('laporan_mingguan_id', '!=', $exceptMingguanId))
            ->delete();
    }

    public function create(array $data): LaporanMingguan
    {
        $laporanHarianIds = $data['laporan_harian_ids'] ?? [];
        $lampiranIds = $data['lampiran_ids'] ?? [];
        unset($data['laporan_harian_ids'], $data['lampiran_ids']);

        return DB::transaction(function () use ($data, $laporanHarianIds, $lampiranIds) {
            $laporanMingguan = LaporanMingguan::create($data);

            if (! empty($laporanHarianIds)) {
                $this->releaseHarianFromOtherMingguan($laporanHarianIds, $laporanMingguan->id);
                $laporanMingguan->laporanHarian()->sync($laporanHarianIds);
            }

            if (! empty($lampiranIds)) {
                $laporanMingguan->lampiran()->sync($lampiranIds);
            }

            return $laporanMingguan;
        });
    }

    public function update(LaporanMingguan $laporanMingguan, array $data): LaporanMingguan
    {
        $laporanHarianIds = $data['laporan_harian_ids'] ?? null;
        $lampiranIds = $data['lampiran_ids'] ?? null;
        unset($data['laporan_harian_ids'], $data['lampiran_ids']);

        return DB::transaction(function () use ($laporanMingguan, $data, $laporanHarianIds, $lampiranIds) {
            $laporanMingguan->update($data);

            if ($laporanHarianIds !== null) {
                $this->releaseHarianFromOtherMingguan($laporanHarianIds, $laporanMingguan->id);
                $laporanMingguan->laporanHarian()->sync($laporanHarianIds);
            }

            if ($lampiranIds !== null) {
                $laporanMingguan->lampiran()->sync($lampiranIds);
            }

            return $laporanMingguan;
        });
    }

    public function delete(LaporanMingguan $laporanMingguan): void
    {
        $laporanMingguan->delete();
    }

    public function removeDoc(LaporanMingguan $laporanMingguan): void
    {
        if ($laporanMingguan->doc_path) {
            $filePath = storage_path('app/'.$laporanMingguan->doc_path);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $laporanMingguan->update([
            'doc_path' => null,
            'doc_name' => null,
            'doc_status' => null,
            'doc_generated_at' => null,
            'doc_error' => null,
        ]);
    }
}
