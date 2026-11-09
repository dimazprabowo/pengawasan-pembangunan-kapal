<?php

namespace App\Services;

use App\Enums\NcStatus;
use App\Jobs\ProcessNcFoto;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcFoto;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LaporanNcService
{
    use HasDynamicLike;
    use SortsQuery;

    /**
     * Baris NC kosong untuk form (row_key stabil agar foto & wire:key tidak tertukar).
     */
    public function newNcRow(?int $workGroupId = null): array
    {
        return [
            'row_key' => 'n'.Str::lower(Str::random(12)),
            'id' => null,
            'work_group_id' => $workGroupId,
            'kendala' => '',
            'tindakan' => '',
            'pic' => '',
        ];
    }

    /**
     * Baris form dari NC tersimpan.
     */
    public function ncRowsFrom(LaporanHarian $laporan): array
    {
        return $laporan->ncs->map(fn (LaporanNc $nc) => [
            'row_key' => 'c'.$nc->id,
            'id' => $nc->id,
            'work_group_id' => $nc->kurva_s_work_group_id,
            'kendala' => $nc->kendala ?? '',
            'tindakan' => $nc->tindakan ?? '',
            'pic' => $nc->pic ?? '',
        ])->all();
    }

    /**
     * Sinkronkan NC untuk satu laporan harian (pola syncAktivitas):
     * - Baris tanpa kendala/tindakan/pic/foto baru diabaikan.
     * - Baris tersimpan (id milik laporan ini) di-update; yang tidak dikirim dihapus beserta fotonya.
     * - Foto bukti baru (1 per NC) menggantikan foto lama, disimpan ke temp lalu
     *   diproses async (crop + WebP) oleh ProcessNcFoto.
     * - Status NC tidak diubah di sini — close lewat Form Penutupan, reopen di List NC.
     *
     * @param  array  $rows  [['row_key', 'id', 'kendala', 'tindakan', 'pic'], ...]
     * @param  array  $fotos  [row_key => ['file' => UploadedFile|null, 'cropData' => ?array]]
     * @return int jumlah foto baru yang diantrikan
     */
    public function syncNc(LaporanHarian $laporan, array $rows, array $fotos = []): int
    {
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $laporan->data_kapal_id)->pluck('nama', 'id');
        $jobs = [];

        DB::transaction(function () use ($laporan, $rows, $fotos, $workGroups, &$jobs) {
            $existing = $laporan->ncs()->with('foto')->get()->keyBy('id');
            $keepIds = [];

            foreach ($rows as $row) {
                $foto = $fotos[$row['row_key'] ?? ''] ?? [];
                $file = ($foto['file'] ?? null) instanceof UploadedFile ? $foto['file'] : null;
                $kendala = trim((string) ($row['kendala'] ?? ''));
                $tindakan = trim((string) ($row['tindakan'] ?? ''));
                $pic = trim((string) ($row['pic'] ?? ''));

                if ($kendala === '' && $tindakan === '' && $pic === '' && ! $file) {
                    continue;
                }

                $wgId = (int) ($row['work_group_id'] ?? 0);
                $wgId = $workGroups->has($wgId) ? $wgId : null;
                $attributes = [
                    'kurva_s_work_group_id' => $wgId,
                    'kategori' => $wgId ? $workGroups[$wgId] : null,
                    'kendala' => $kendala ?: '-',
                    'tindakan' => $tindakan ?: null,
                    'pic' => $pic ?: null,
                ];

                $nc = $existing->get((int) ($row['id'] ?? 0));
                if ($nc) {
                    $nc->update($attributes);
                } else {
                    $nc = $laporan->ncs()->create($attributes + [
                        'data_kapal_id' => $laporan->data_kapal_id,
                        'nomor' => $this->nextNomor($laporan->data_kapal_id),
                    ]);
                }
                $keepIds[] = $nc->id;

                if ($file) {
                    if ($nc->foto) {
                        $this->deleteFoto($nc->foto);
                    }

                    $ncFoto = $nc->foto()->create([
                        'file_name' => basename($file->getClientOriginalName()),
                        'file_size' => $file->getSize(),
                        'file_status' => 'pending',
                        'uploaded_by' => auth()->id(),
                    ]);

                    $jobs[] = [$ncFoto, $file->store('temp/laporan-nc', 'local'), $foto['cropData'] ?? null];
                }
            }

            $existing->except($keepIds)->each(fn (LaporanNc $nc) => $this->deleteNc($nc));
        });

        foreach ($jobs as [$ncFoto, $tempPath, $cropData]) {
            ProcessNcFoto::dispatch($ncFoto, $tempPath, $cropData);
        }

        return count($jobs);
    }

    /**
     * Buka kembali NC yang sudah closed. Form penutupan yang pernah menutupnya
     * tetap tersimpan sebagai histori; NC bisa dimasukkan ke form penutupan baru.
     * Waktu, pengguna & alasan reopen dicatat untuk audit.
     */
    public function reopen(LaporanNc $nc, int $userId, string $note): void
    {
        $nc->update([
            'status' => NcStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
            'reopened_at' => now(),
            'reopened_by' => $userId,
            'reopen_note' => $note,
        ]);
    }

    public function deleteFoto(LaporanNcFoto $foto): void
    {
        if ($foto->file_path && Storage::disk('local')->exists($foto->file_path)) {
            Storage::disk('local')->delete($foto->file_path);
        }
        $foto->delete();
    }

    /**
     * Hapus NC beserta foto bukti (link Form NC ikut terhapus via cascade).
     */
    public function deleteNc(LaporanNc $nc): void
    {
        if ($nc->foto) {
            $this->deleteFoto($nc->foto);
        }
        $nc->delete();
    }

    /**
     * Cleanup saat laporan harian dihapus — dipanggil sebelum $laporan->delete()
     * agar file foto & scan form temuan ikut dibersihkan (cascade DB tidak menghapus file).
     */
    public function deleteForLaporan(LaporanHarian $laporan): void
    {
        foreach ($laporan->ncs()->with('foto')->get() as $nc) {
            $this->deleteNc($nc);
        }

        app(LaporanNcFormService::class)->deleteForLaporan($laporan);
    }

    /**
     * List NC per data kapal untuk tab List NC — eager load relasi tampilan.
     */
    public function getFiltered(
        int $dataKapalId,
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 10,
        array $mingguFilter = [],
        array $bulanFilter = [],
        array $hariFilter = []
    ): LengthAwarePaginator {
        return $this->filteredQuery($dataKapalId, $search, $statusFilter, $mingguFilter, $bulanFilter, $hariFilter)
            ->paginate($perPage);
    }

    /**
     * Query terfilter untuk export (tanpa paginasi).
     */
    public function filteredQuery(
        int $dataKapalId,
        ?string $search = null,
        ?string $statusFilter = null,
        array $mingguFilter = [],
        array $bulanFilter = [],
        array $hariFilter = []
    ): Builder {
        $like = $this->getLikeOperator();

        $query = LaporanNc::with(['laporanHarian:id,tanggal_laporan', 'createdBy:id,name', 'closedBy:id,name', 'reopenedBy:id,name', 'foto', 'forms:id,jenis,nomor,signed_at,file_status'])
            ->where('data_kapal_id', $dataKapalId)
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($search, function ($q) use ($search, $like) {
                $q->where(function ($q) use ($search, $like) {
                    $q->where('nomor', $like, "%{$search}%")
                        ->orWhere('kendala', $like, "%{$search}%")
                        ->orWhere('pic', $like, "%{$search}%");
                });
            });

        // Filter minggu/bulan/hari mengikuti tanggal laporan harian asal NC
        if ($mingguFilter || $bulanFilter) {
            $query->whereHas('laporanHarian', function ($q) use ($dataKapalId, $mingguFilter, $bulanFilter) {
                app(KontrakPeriodeService::class)->applyPeriodeFilter(
                    $q,
                    DataKapal::find($dataKapalId),
                    $mingguFilter,
                    $bulanFilter
                );
            });
        }

        $hariFilter = array_filter($hariFilter);
        if ($hariFilter) {
            $query->whereHas('laporanHarian', fn ($q) => $q->whereIn('tanggal_laporan', $hariFilter));
        }

        return $query->orderByDesc('laporan_harian_id')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');
    }

    /**
     * Rekap NC untuk Laporan Mingguan periode [mulai, selesai]:
     * NC yang open kapan pun selama periode (dibuat <= selesai & belum closed
     * sebelum mulai) terbawa terus; NC closed hanya tampil SEKALI pada periode
     * ia di-close (closed_at >= mulai), lalu tidak muncul di periode berikutnya.
     *
     * @return Collection<int, LaporanNc>
     */
    public function rekapMingguan(int $dataKapalId, $periodeMulai, $periodeSelesai): Collection
    {
        $mulai = Carbon::parse($periodeMulai)->startOfDay();
        $selesai = Carbon::parse($periodeSelesai)->endOfDay();

        return LaporanNc::with(['laporanHarian:id,tanggal_laporan', 'foto', 'closedBy:id,name'])
            ->where('data_kapal_id', $dataKapalId)
            ->where('created_at', '<=', $selesai)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $mulai))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Opsi tanggal laporan (hari) untuk kapal ini, dibatasi rentang minggu/bulan
     * terpilih bila ada. Value = 'Y-m-d', label = tanggal terformat.
     *
     * @param  array<array{0: Carbon, 1: Carbon}>  $ranges
     * @return array<int, array{value: string, label: string}>
     */
    public function tanggalOptions(int $dataKapalId, array $ranges = []): array
    {
        return LaporanHarian::where('data_kapal_id', $dataKapalId)
            ->when($ranges, function ($q) use ($ranges) {
                $q->where(function ($q) use ($ranges) {
                    foreach ($ranges as [$mulai, $selesai]) {
                        $q->orWhereBetween('tanggal_laporan', [$mulai->toDateString(), $selesai->toDateString()]);
                    }
                });
            })
            ->distinct()
            ->orderBy('tanggal_laporan')
            ->pluck('tanggal_laporan')
            ->map(fn ($t) => [
                'value' => $t->toDateString(),
                'label' => $t->translatedFormat('D, d M Y'),
            ])->all();
    }

    private function nextNomor(int $dataKapalId): string
    {
        $last = LaporanNc::where('data_kapal_id', $dataKapalId)
            ->whereNotNull('nomor')
            ->orderByDesc('id')
            ->value('nomor');

        $next = $last && preg_match('/(\d+)$/', $last, $m) ? (int) $m[1] + 1 : 1;

        return 'NC-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
