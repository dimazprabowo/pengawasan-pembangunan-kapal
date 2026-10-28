<?php

namespace App\Services;

use App\Jobs\ProcessNcFile;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcFile;
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
use Illuminate\Validation\ValidationException;

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
     * - Baris tanpa kendala/tindakan/pic diabaikan.
     * - Baris tersimpan (id milik laporan ini) di-update; yang tidak dikirim dihapus.
     * - File bukti before/after (dokumen form NC) diunggah via List NC, bukan di sini.
     * - Status NC tidak diubah di sini — open/closed dikelola di List NC.
     *
     * @param  array  $rows  [['row_key', 'id', 'kendala', 'tindakan', 'pic'], ...]
     */
    public function syncNc(LaporanHarian $laporan, array $rows): void
    {
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $laporan->data_kapal_id)->pluck('nama', 'id');

        DB::transaction(function () use ($laporan, $rows, $workGroups) {
            $existing = $laporan->ncs()->with('files')->get()->keyBy('id');
            $keepIds = [];

            foreach ($rows as $row) {
                $kendala = trim((string) ($row['kendala'] ?? ''));
                $tindakan = trim((string) ($row['tindakan'] ?? ''));
                $pic = trim((string) ($row['pic'] ?? ''));

                if ($kendala === '' && $tindakan === '' && $pic === '') {
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
            }

            $existing->except($keepIds)->each(function (LaporanNc $nc) {
                $this->deleteNc($nc);
            });
        });
    }

    /**
     * Ubah status open <-> closed. Closed WAJIB punya minimal 1 file after.
     */
    public function toggleStatus(LaporanNc $nc, int $userId): void
    {
        if ($nc->isOpen()) {
            $hasAfter = $nc->afterFiles()
                ->where('file_status', '!=', 'failed')
                ->exists();

            if (! $hasAfter) {
                throw ValidationException::withMessages([
                    'nc_status' => 'NC tidak bisa ditutup — unggah minimal 1 foto After sebagai bukti penyelesaian.',
                ]);
            }

            $nc->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);
        } else {
            $nc->update([
                'status' => 'open',
                'closed_at' => null,
                'closed_by' => null,
            ]);
        }
    }

    /**
     * Upload file bukti lalu link ke beberapa NC sekaligus (checklist) dengan
     * peran before/after. Return jumlah file yang diantrikan ke worker.
     *
     * @param  UploadedFile[]  $files
     */
    public function storeUploads(int $dataKapalId, array $ncIds, array $files, string $kind, int $userId): int
    {
        $jobs = [];

        DB::transaction(function () use ($dataKapalId, $ncIds, $files, $kind, $userId, &$jobs) {
            $ncs = LaporanNc::where('data_kapal_id', $dataKapalId)->whereIn('id', $ncIds)->get();

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $ncFile = $this->createFileRecord($dataKapalId, $file, $userId);
                $tempPath = $file->store('temp/laporan-nc', 'local');

                foreach ($ncs as $nc) {
                    $nc->files()->syncWithoutDetaching([$ncFile->id => ['kind' => $kind]]);
                }

                $jobs[] = [$ncFile, $tempPath];
            }
        });

        foreach ($jobs as [$ncFile, $tempPath]) {
            ProcessNcFile::dispatch($ncFile, $tempPath);
        }

        return count($jobs);
    }

    /**
     * Lepas link file dari NC; file record dihapus bila sudah tidak dipakai NC lain.
     */
    public function detachFile(LaporanNc $nc, LaporanNcFile $file): void
    {
        DB::transaction(function () use ($nc, $file) {
            $nc->files()->detach($file->id);

            if (! $file->ncs()->exists()) {
                $this->deleteNcFile($file);
            }
        });
    }

    /**
     * Hapus NC beserta link file-nya (file record dihapus bila jadi orphan).
     */
    public function deleteNc(LaporanNc $nc): void
    {
        $files = $nc->files()->get();
        $nc->files()->detach();
        $nc->delete();

        foreach ($files as $file) {
            if (! $file->ncs()->exists()) {
                $this->deleteNcFile($file);
            }
        }
    }

    /**
     * Cleanup saat laporan harian dihapus — dipanggil sebelum $laporan->delete()
     * agar file orphan ikut dibersihkan (cascade hanya menghapus link).
     */
    public function deleteForLaporan(LaporanHarian $laporan): void
    {
        foreach ($laporan->ncs()->with('files')->get() as $nc) {
            $this->deleteNc($nc);
        }
    }

    /**
     * List NC per data kapal untuk tab List NC — earger load relasi tampilan.
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

        $query = LaporanNc::with(['laporanHarian:id,tanggal_laporan', 'createdBy:id,name', 'closedBy:id,name', 'files'])
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
     * sebelum mulai) + NC yang closed pada periode atau minggu sebelumnya
     * (closed_at >= mulai - 7 hari) — NC closed minggu ini tetap tampil minggu
     * depan sebagai bukti penyelesaian, lalu hilang minggu berikutnya.
     *
     * @return Collection<int, LaporanNc>
     */
    public function rekapMingguan(int $dataKapalId, $periodeMulai, $periodeSelesai): Collection
    {
        $mulai = Carbon::parse($periodeMulai)->startOfDay();
        $selesai = Carbon::parse($periodeSelesai)->endOfDay();

        return LaporanNc::with(['laporanHarian:id,tanggal_laporan', 'files', 'closedBy:id,name'])
            ->where('data_kapal_id', $dataKapalId)
            ->where('created_at', '<=', $selesai)
            ->where(function ($q) use ($mulai) {
                $q->whereNull('closed_at')
                    ->orWhere('closed_at', '>=', $mulai->copy()->subDays(7)->startOfDay());
            })
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

    private function createFileRecord(int $dataKapalId, UploadedFile $file, ?int $userId = null): LaporanNcFile
    {
        return LaporanNcFile::create([
            'data_kapal_id' => $dataKapalId,
            'file_name' => basename($file->getClientOriginalName()),
            'file_size' => $file->getSize(),
            'file_status' => 'pending',
            'uploaded_by' => $userId ?? auth()->id(),
        ]);
    }

    private function deleteNcFile(LaporanNcFile $file): void
    {
        if ($file->file_path && Storage::disk('local')->exists($file->file_path)) {
            Storage::disk('local')->delete($file->file_path);
        }
        $file->delete();
    }
}
