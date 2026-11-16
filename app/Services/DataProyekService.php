<?php

namespace App\Services;

use App\Enums\DrawingStatus;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use App\Models\Pengujian;
use App\Models\ProjectMaterial;
use App\Models\ProjectVendor;
use App\Models\Rapat;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * CRUD entity data proyek per data kapal (drawing register, vendor,
 * material, pengujian, rapat) + agregasi untuk laporan bulanan.
 */
class DataProyekService
{
    // ─── LIST PER ENTITY (paginated, named paginator per tab) ────────────

    public function getDrawings(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return $this->applySearch(
            $dataKapal->drawingRegisters()->getQuery(),
            $search,
            ['nomor', 'judul', 'keterangan']
        )
            ->when($filters['status_produksi'] ?? null, fn ($q, $v) => $q->where('status_produksi', $v))
            ->when($filters['status_review_mno'] ?? null, fn ($q, $v) => $q->where('status_review_mno', $v))
            ->when($filters['status_approval_klas'] ?? null, fn ($q, $v) => $q->where('status_approval_klas', $v))
            ->orderBy('nomor')->paginate($perPage, ['*'], 'drawingPage');
    }

    public function getVendors(DataKapal $dataKapal, ?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return $this->applySearch(
            $dataKapal->projectVendors()->getQuery(),
            $search,
            ['nama', 'lingkup_pekerjaan', 'kontak']
        )->orderBy('nama')->paginate($perPage, ['*'], 'vendorPage');
    }

    public function getMaterials(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return $this->applySearch(
            $dataKapal->projectMaterials()->getQuery(),
            $search,
            ['nama_item', 'spesifikasi', 'keterangan']
        )
            ->when($filters['kategori'] ?? null, fn ($q, $v) => $q->where('kategori', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('kategori')->orderBy('nama_item')->paginate($perPage, ['*'], 'materialPage');
    }

    public function getPengujians(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return $this->applySearch(
            $dataKapal->pengujians()->getQuery(),
            $search,
            ['nama_pengujian', 'hasil', 'keterangan']
        )
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('tanggal')->orderBy('nama_pengujian')->paginate($perPage, ['*'], 'pengujianPage');
    }

    public function getRapats(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        return $this->applySearch(
            $dataKapal->rapats()->getQuery(),
            $search,
            ['lokasi', 'topik']
        )
            ->when($filters['jenis'] ?? null, fn ($q, $v) => $q->where('jenis', $v))
            ->orderByDesc('tanggal')->paginate($perPage, ['*'], 'rapatPage');
    }

    private function applySearch($query, ?string $search, array $columns)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', "%{$search}%");
            }
        });
    }

    // ─── SAVE / DELETE ───────────────────────────────────────────────────

    public function saveDrawing(DataKapal $dataKapal, array $data, ?DrawingRegister $model = null): DrawingRegister
    {
        return tap($model ?? new DrawingRegister, function (DrawingRegister $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function saveVendor(DataKapal $dataKapal, array $data, ?ProjectVendor $model = null): ProjectVendor
    {
        return tap($model ?? new ProjectVendor, function (ProjectVendor $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function saveMaterial(DataKapal $dataKapal, array $data, ?ProjectMaterial $model = null): ProjectMaterial
    {
        return tap($model ?? new ProjectMaterial, function (ProjectMaterial $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function savePengujian(DataKapal $dataKapal, array $data, ?Pengujian $model = null): Pengujian
    {
        return tap($model ?? new Pengujian, function (Pengujian $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function saveRapat(DataKapal $dataKapal, array $data, ?Rapat $model = null): Rapat
    {
        return tap($model ?? new Rapat, function (Rapat $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    // ─── DATA UNTUK LAPORAN BULANAN ──────────────────────────────────────

    /**
     * Agregasi status drawing untuk BAB 2.2 — tiga blok:
     * produksi galangan, review MNO, review/approval Klas.
     *
     * @return array{total:int, produksi_selesai:int, produksi_pct:?float,
     *               mno_diterima:int, mno_selesai:int, mno_pct:?float,
     *               mno_comment:int, mno_open:int, mno_closed:int, mno_informasi:int,
     *               klas_dikirim:int, klas_disetujui:int, klas_pct:?float,
     *               klas_comment:int, klas_open:int, klas_closed:int, klas_informasi:int}
     */
    public function getDrawingAggregates(DataKapal $dataKapal): array
    {
        $rows = $dataKapal->drawingRegisters()
            ->select(['status_produksi', 'status_review_mno', 'status_approval_klas',
                'mno_comment_open', 'mno_comment_closed', 'mno_comment_informasi',
                'klas_comment_open', 'klas_comment_closed', 'klas_comment_informasi'])
            ->get();

        $total = $rows->count();
        $produksiSelesai = $rows->where('status_produksi', DrawingStatus::Selesai)->count();
        $mnoDiterima = $rows->where('status_review_mno', '!=', DrawingStatus::Belum)->count();
        $mnoSelesai = $rows->where('status_review_mno', DrawingStatus::Selesai)->count();
        $klasDikirim = $rows->where('status_approval_klas', '!=', DrawingStatus::Belum)->count();
        $klasDisetujui = $rows->where('status_approval_klas', DrawingStatus::Selesai)->count();

        $pct = fn (int $done, int $of): ?float => $of > 0 ? round($done / $of * 100, 2) : null;

        return [
            'total' => $total,
            'produksi_selesai' => $produksiSelesai,
            'produksi_pct' => $pct($produksiSelesai, $total),
            'mno_diterima' => $mnoDiterima,
            'mno_selesai' => $mnoSelesai,
            'mno_pct' => $pct($mnoSelesai, $mnoDiterima ?: $total),
            'mno_comment' => (int) $rows->sum(fn ($r) => $r->mno_comment_open + $r->mno_comment_closed + $r->mno_comment_informasi),
            'mno_open' => (int) $rows->sum('mno_comment_open'),
            'mno_closed' => (int) $rows->sum('mno_comment_closed'),
            'mno_informasi' => (int) $rows->sum('mno_comment_informasi'),
            'klas_dikirim' => $klasDikirim,
            'klas_disetujui' => $klasDisetujui,
            'klas_pct' => $pct($klasDisetujui, $klasDikirim ?: $total),
            'klas_comment' => (int) $rows->sum(fn ($r) => $r->klas_comment_open + $r->klas_comment_closed + $r->klas_comment_informasi),
            'klas_open' => (int) $rows->sum('klas_comment_open'),
            'klas_closed' => (int) $rows->sum('klas_comment_closed'),
            'klas_informasi' => (int) $rows->sum('klas_comment_informasi'),
        ];
    }

    /**
     * Material dikelompokkan per kategori (untuk tabel BAB 2.5).
     *
     * @return Collection<string, Collection<int, ProjectMaterial>>
     */
    public function getMaterialsGrouped(DataKapal $dataKapal): Collection
    {
        return $dataKapal->projectMaterials()->get()->groupBy(fn ($m) => $m->kategori->value);
    }

    /**
     * Rapat dalam rentang periode (BAB 3.1).
     */
    public function getRapatsInPeriode(DataKapal $dataKapal, CarbonInterface $mulai, CarbonInterface $selesai): Collection
    {
        return $dataKapal->rapats()
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get();
    }

    /**
     * Pengujian sampai tanggal cut-off (BAB 2.7).
     */
    public function getPengujiansUpTo(DataKapal $dataKapal, ?CarbonInterface $selesai = null): Collection
    {
        return $dataKapal->pengujians()
            ->when($selesai, fn ($q) => $q->where(function ($q) use ($selesai) {
                $q->whereNull('tanggal')->orWhere('tanggal', '<=', $selesai->toDateString());
            }))
            ->get();
    }
}
