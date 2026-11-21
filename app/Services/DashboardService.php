<?php

namespace App\Services;

use App\Enums\GalanganStatus;
use App\Enums\NcStatus;
use App\Models\DataKapal;
use App\Models\Galangan;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use Illuminate\Support\Collection;

/**
 * Agregasi dashboard peta galangan. Semua method menerima daftar id data kapal
 * yang SUDAH ter-scope akses user (HasDataKapalFilter) — service tidak menentukan scope.
 */
class DashboardService
{
    public function __construct(private KurvaSService $kurvaSService) {}

    /**
     * @param  Collection<int, int>  $kapalIds
     * @return array{galangan: int, galangan_berlokasi: int, kapal: int, kontrak_segera_berakhir: int, laporan_7_hari: int, laporan_hari_ini: int, nc_open: int, nc_closed_30_hari: int}
     */
    public function summary(Collection $kapalIds): array
    {
        $galangan = Galangan::where('status', GalanganStatus::Active)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) AS berlokasi')
            ->first();

        $kontrakBerakhir = DataKapal::whereIn('id', $kapalIds)
            ->whereNotNull('tanggal_mulai_pelaksanaan')
            ->whereNotNull('waktu_pelaksanaan_hari')
            ->get(['id', 'tanggal_mulai_pelaksanaan', 'waktu_pelaksanaan_hari'])
            ->filter(fn (DataKapal $k) => ($sisa = $k->sisaHariPelaksanaan()) !== null && $sisa >= 0 && $sisa <= 30)
            ->count();

        $laporan = LaporanHarian::whereIn('data_kapal_id', $kapalIds);

        $nc = LaporanNc::whereIn('data_kapal_id', $kapalIds)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS open_total', [NcStatus::Open->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND closed_at >= ? THEN 1 ELSE 0 END) AS closed_recent', [NcStatus::Closed->value, now()->subDays(30)])
            ->first();

        return [
            'galangan' => (int) $galangan->total,
            'galangan_berlokasi' => (int) $galangan->berlokasi,
            'kapal' => $kapalIds->count(),
            'kontrak_segera_berakhir' => $kontrakBerakhir,
            'laporan_7_hari' => (clone $laporan)->whereDate('tanggal_laporan', '>=', today()->subDays(6))->count(),
            'laporan_hari_ini' => (clone $laporan)->whereDate('tanggal_laporan', today())->count(),
            'nc_open' => (int) $nc->open_total,
            'nc_closed_30_hari' => (int) $nc->closed_recent,
        ];
    }

    /**
     * Data marker peta: galangan aktif + jumlah kapal & NC open (dari kapal ter-scope).
     *
     * @param  Collection<int, int>  $kapalIds
     * @return list<array{kode: string, nama: string, kota: ?string, provinsi: ?string, lat: ?float, lng: ?float, kapal: int, nc: int}>
     */
    public function markers(Collection $kapalIds): array
    {
        $ncPerGalangan = LaporanNc::query()
            ->join('data_kapal', 'data_kapal.id', '=', 'laporan_nc.data_kapal_id')
            ->whereIn('laporan_nc.data_kapal_id', $kapalIds)
            ->where('laporan_nc.status', NcStatus::Open->value)
            ->groupBy('data_kapal.galangan_id')
            ->selectRaw('data_kapal.galangan_id, COUNT(*) AS total')
            ->pluck('total', 'galangan_id');

        return Galangan::where('status', GalanganStatus::Active)
            ->withCount(['dataKapal as kapal_count' => fn ($q) => $q->whereIn('data_kapal.id', $kapalIds)])
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'kota', 'provinsi', 'latitude', 'longitude'])
            ->map(fn (Galangan $g) => [
                'kode' => $g->kode,
                'nama' => $g->nama,
                'kota' => $g->kota,
                'provinsi' => $g->provinsi,
                'lat' => $g->latitude,
                'lng' => $g->longitude,
                'kapal' => (int) $g->kapal_count,
                'nc' => (int) ($ncPerGalangan[$g->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Detail galangan terpilih + data kapal ter-scope beserta progress Kurva-S.
     *
     * @param  Collection<int, int>  $kapalIds
     * @return array{galangan: Galangan, kapal: \Illuminate\Database\Eloquent\Collection<int, DataKapal>}|null
     */
    public function galanganDetail(string $kode, Collection $kapalIds): ?array
    {
        $galangan = Galangan::where('status', GalanganStatus::Active)->where('kode', $kode)->first();

        if (! $galangan) {
            return null;
        }

        $kapal = DataKapal::with('owner:id,name')
            ->where('galangan_id', $galangan->id)
            ->whereIn('id', $kapalIds)
            ->withCount([
                'laporanHarian',
                'laporanMingguan',
                'laporanBulanan',
                'laporanNc as nc_open_count' => fn ($q) => $q->where('status', NcStatus::Open->value),
            ])
            ->withMax('laporanHarian', 'tanggal_laporan')
            ->orderBy('nama')
            ->get();

        return [
            'galangan' => $galangan,
            'kapal' => $this->kurvaSService->attachProgress($kapal),
        ];
    }

    /**
     * Daftar kapal ter-scope yang memiliki jadwal Kurva-S (work groups),
     * lengkap dengan progress rencana/aktual/deviasi. Urut: deviasi
     * terburuk (paling negatif) di atas agar kapal terlambat terlihat
     * duluan; kapal tanpa data progress berada paling bawah.
     *
     * @param  Collection<int, int>  $kapalIds
     * @return \Illuminate\Database\Eloquent\Collection<int, DataKapal>
     */
    public function kurvaSKapalRows(Collection $kapalIds): \Illuminate\Database\Eloquent\Collection
    {
        $kapal = DataKapal::with(['owner:id,name', 'galangan:id,nama,kota'])
            ->whereIn('id', $kapalIds)
            ->whereHas('kurvaSWorkGroups')
            ->withCount('laporanMingguan')
            ->withMax('laporanHarian', 'tanggal_laporan')
            ->get();

        $this->kurvaSService->attachProgress($kapal);

        return $kapal->sortBy(fn (DataKapal $k) => [
            $k->progress_deviasi === null ? 1 : 0,
            $k->progress_deviasi ?? 0.0,
            mb_strtolower($k->nama),
        ])->values();
    }

    /**
     * Data grafik Kurva-S satu kapal untuk panel dashboard.
     * mode 'mingguan' = kurva mingguan kanonik; 'bulanan' = agregat per bulan.
     */
    public function kurvaSChart(DataKapal $dataKapal, string $mode = 'mingguan'): array
    {
        return $mode === 'bulanan'
            ? $this->kurvaSService->getMonthlyChartData($dataKapal)
            : $this->kurvaSService->getChartData($dataKapal);
    }

    /**
     * Laporan harian terbaru dari kapal ter-scope (aktivitas terkini).
     *
     * @param  Collection<int, int>  $kapalIds
     * @return \Illuminate\Database\Eloquent\Collection<int, LaporanHarian>
     */
    public function recentLaporan(Collection $kapalIds, int $limit = 6): \Illuminate\Database\Eloquent\Collection
    {
        return LaporanHarian::with(['dataKapal:id,nama,galangan_id', 'dataKapal.galangan:id,nama', 'user:id,name'])
            ->whereIn('data_kapal_id', $kapalIds)
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
