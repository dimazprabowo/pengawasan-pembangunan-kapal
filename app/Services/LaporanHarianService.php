<?php

namespace App\Services;

use App\Jobs\ProcessLaporanLampiran;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanAktivitas;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LaporanHarianService
{
    public const KATEGORI_LAINNYA = 'Lainnya';

    public function getFiltered(
        ?string $search = null,
        ?int $dataKapalId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = LaporanHarian::with(['user', 'updatedBy:id,name', 'dataKapal.company', 'dataKapal.galangan', 'lampiran'])
            ->byDataKapal($dataKapalId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tanggal_laporan', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $this->flagDuplicateTanggal($paginator, $dataKapalId);

        return $paginator;
    }

    /**
     * Tandai laporan pada halaman ini yang tanggal_laporan-nya juga dipakai
     * laporan harian lain pada kapal yang sama (data duplikat legacy).
     */
    private function flagDuplicateTanggal(LengthAwarePaginator $paginator, ?int $dataKapalId): void
    {
        $items = $paginator->getCollection();
        $dates = $items->map(fn ($l) => $l->tanggal_laporan->format('Y-m-d'))->unique();

        if (! $dataKapalId || $dates->isEmpty()) {
            $items->each(function ($l) {
                $l->tanggal_duplikat = false;
            });

            return;
        }

        $dupeDates = LaporanHarian::where('data_kapal_id', $dataKapalId)
            ->where(function ($q) use ($dates) {
                foreach ($dates as $d) {
                    $q->orWhereDate('tanggal_laporan', $d);
                }
            })
            ->get(['id', 'tanggal_laporan'])
            ->groupBy(fn ($l) => $l->tanggal_laporan->format('Y-m-d'))
            ->filter(fn ($group) => $group->count() > 1)
            ->keys();

        // NB: gunakan closure biasa — `each` berhenti iterasi jika callback mengembalikan false
        $items->each(function ($l) use ($dupeDates) {
            $l->tanggal_duplikat = $dupeDates->contains($l->tanggal_laporan->format('Y-m-d'));
        });
    }

    public function create(array $data): LaporanHarian
    {
        return LaporanHarian::create($data);
    }

    public function createMany(array $items): array
    {
        $created = [];

        DB::transaction(function () use ($items, &$created) {
            foreach ($items as $data) {
                $created[] = LaporanHarian::create($data);
            }
        });

        return $created;
    }

    public function update(LaporanHarian $laporanHarian, array $data): LaporanHarian
    {
        $laporanHarian->update($data);

        return $laporanHarian;
    }

    public function delete(LaporanHarian $laporanHarian): void
    {
        // Delete all lampiran files first
        foreach ($laporanHarian->lampiran as $lampiran) {
            $this->deleteLampiranFile($lampiran);
        }

        // Delete old single file if exists (backward compatibility)
        $this->deleteFile($laporanHarian);

        // Delete generated Word document if exists
        $this->deleteDocFile($laporanHarian);

        $laporanHarian->delete();
    }

    public function removeFile(LaporanHarian $laporanHarian): void
    {
        $this->deleteFile($laporanHarian);
        $laporanHarian->update([
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
        ]);
    }

    /**
     * Opsi kategori uraian aktivitas = Work Group Kurva-S data kapal (urut sort_order),
     * selalu ditutup segmen "Lainnya" (work_group_id null).
     *
     * @return array<int, array{work_group_id: ?int, nama: string}>
     */
    public function kategoriOptions(int $dataKapalId): array
    {
        return KurvaSWorkGroup::where('data_kapal_id', $dataKapalId)
            ->orderBy('sort_order')
            ->get(['id', 'nama'])
            ->map(fn ($wg) => ['work_group_id' => $wg->id, 'nama' => $wg->nama])
            ->push(['work_group_id' => null, 'nama' => self::KATEGORI_LAINNYA])
            ->all();
    }

    /**
     * Kelompokkan baris aktivitas form per kategori. Index asli baris dipertahankan
     * (dipakai sebagai path wire:model); baris dengan work group yang tidak valid
     * untuk kapal ini masuk segmen "Lainnya".
     *
     * @return array<int, array{work_group_id: ?int, nama: string, rows: array<int, array>}>
     */
    public function groupRowsByKategori(array $rows, array $kategoriOptions): array
    {
        $groups = array_map(fn ($opt) => $opt + ['rows' => []], $kategoriOptions);
        $groupIndex = array_flip(array_map(fn ($opt) => (int) $opt['work_group_id'], $kategoriOptions));

        foreach ($rows as $i => $row) {
            $wgId = (int) ($row['work_group_id'] ?? 0);
            $groups[$groupIndex[$wgId] ?? $groupIndex[0]]['rows'][$i] = $row;
        }

        return $groups;
    }

    /**
     * Baris aktivitas kosong untuk form (row_key stabil agar foto & wire:key tidak tertukar).
     */
    public function newAktivitasRow(?int $workGroupId): array
    {
        return [
            'row_key' => 'n'.Str::lower(Str::random(12)),
            'id' => null,
            'work_group_id' => $workGroupId,
            'aktivitas' => '',
            'pic' => '',
        ];
    }

    /**
     * Baris form dari aktivitas tersimpan.
     */
    public function aktivitasRowsFrom(LaporanHarian $laporan): array
    {
        return $laporan->aktivitas->map(fn ($a) => $this->aktivitasRow($a))->all();
    }

    public function aktivitasRow(LaporanAktivitas $aktivitas): array
    {
        return [
            'row_key' => 'a'.$aktivitas->id,
            'id' => $aktivitas->id,
            'work_group_id' => $aktivitas->kurva_s_work_group_id,
            'aktivitas' => $aktivitas->aktivitas ?? '',
            'pic' => $aktivitas->pic ?? '',
        ];
    }

    /**
     * Sinkronkan uraian aktivitas beserta foto lampirannya untuk satu laporan harian.
     * - Baris tanpa uraian, PIC, dan foto baru diabaikan.
     * - Baris tersimpan (id milik laporan ini) di-update; yang tidak dikirim dihapus beserta fotonya.
     * - Foto baru disimpan ke temp lalu diproses async (crop + WebP) oleh ProcessLaporanLampiran.
     *
     * @param  array  $rows  [['row_key', 'id', 'work_group_id', 'aktivitas', 'pic'], ...]
     * @param  array  $fotos  [row_key => ['file' => UploadedFile|null, 'cropData' => ?array]]
     * @return int jumlah foto baru yang diantrikan
     */
    public function syncAktivitas(LaporanHarian $laporan, array $rows, array $fotos = []): int
    {
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $laporan->data_kapal_id)->pluck('nama', 'id');
        $jobs = [];

        DB::transaction(function () use ($laporan, $rows, $fotos, $workGroups, &$jobs) {
            $existing = $laporan->aktivitas()->with('lampiran')->get()->keyBy('id');
            $keepIds = [];

            foreach ($rows as $row) {
                $foto = $fotos[$row['row_key'] ?? ''] ?? [];
                $file = ($foto['file'] ?? null) instanceof UploadedFile ? $foto['file'] : null;
                $uraian = trim((string) ($row['aktivitas'] ?? ''));
                $pic = trim((string) ($row['pic'] ?? ''));

                if ($uraian === '' && $pic === '' && ! $file) {
                    continue;
                }

                $wgId = (int) ($row['work_group_id'] ?? 0);
                $wgId = $workGroups->has($wgId) ? $wgId : null;
                $attributes = [
                    'kurva_s_work_group_id' => $wgId,
                    'kategori' => $wgId ? $workGroups[$wgId] : null,
                    'aktivitas' => $uraian ?: null,
                    'pic' => $pic ?: null,
                ];

                $aktivitas = $existing->get((int) ($row['id'] ?? 0));
                if ($aktivitas) {
                    $aktivitas->update($attributes);
                } else {
                    $aktivitas = $laporan->aktivitas()->create($attributes);
                }
                $keepIds[] = $aktivitas->id;

                if ($file) {
                    if ($aktivitas->lampiran) {
                        $this->deleteLampiran($aktivitas->lampiran);
                    }

                    $lampiran = $aktivitas->lampiran()->create([
                        'laporan_harian_id' => $laporan->id,
                        'file_name' => basename($file->getClientOriginalName()),
                        'file_size' => $file->getSize(),
                        'file_status' => 'pending',
                    ]);

                    $jobs[] = [$lampiran, $file->store('temp/laporan-lampiran', 'local'), $foto['cropData'] ?? null];
                }
            }

            $existing->except($keepIds)->each(function (LaporanAktivitas $aktivitas) {
                if ($aktivitas->lampiran) {
                    $this->deleteLampiran($aktivitas->lampiran);
                }
                $aktivitas->delete();
            });
        });

        foreach ($jobs as [$lampiran, $tempPath, $cropData]) {
            ProcessLaporanLampiran::dispatch($lampiran, $tempPath, $cropData);
        }

        return count($jobs);
    }

    /**
     * Uraian aktivitas tersimpan dikelompokkan per kategori (snapshot nama), hanya
     * kategori yang memiliki aktivitas. Urutan: sort_order work group, kategori
     * yang work group-nya sudah dihapus, lalu "Lainnya". Dipakai Show & Word.
     *
     * @return Collection<int, array{nama: string, aktivitas: Collection<int, LaporanAktivitas>}>
     */
    public function aktivitasPerKategori(LaporanHarian $laporan): Collection
    {
        $laporan->loadMissing(['aktivitas.workGroup', 'aktivitas.lampiran']);

        return $laporan->aktivitas
            ->groupBy(fn (LaporanAktivitas $a) => $a->kategori ?? self::KATEGORI_LAINNYA)
            ->map(function (Collection $items, string $nama) {
                $workGroup = $items->first()->workGroup;
                $urutan = match (true) {
                    $nama === self::KATEGORI_LAINNYA => PHP_INT_MAX,
                    $workGroup !== null => $workGroup->sort_order,
                    default => PHP_INT_MAX - 1,
                };

                return ['nama' => $nama, 'aktivitas' => $items->values(), 'urutan' => $urutan];
            })
            ->sortBy('urutan')
            ->map(fn ($group) => ['nama' => $group['nama'], 'aktivitas' => $group['aktivitas']])
            ->values();
    }

    public function deleteLampiran(LaporanLampiran $lampiran): void
    {
        $this->deleteLampiranFile($lampiran);
        $lampiran->delete();
    }

    public function removeDoc(LaporanHarian $laporanHarian): void
    {
        $this->deleteDocFile($laporanHarian);
        $laporanHarian->update([
            'doc_path' => null,
            'doc_name' => null,
            'doc_status' => null,
            'doc_generated_at' => null,
            'doc_error' => null,
        ]);
    }

    private function deleteFile(LaporanHarian $laporanHarian): void
    {
        if ($laporanHarian->file_path && Storage::disk('local')->exists($laporanHarian->file_path)) {
            Storage::disk('local')->delete($laporanHarian->file_path);
        }
    }

    private function deleteDocFile(LaporanHarian $laporanHarian): void
    {
        if ($laporanHarian->doc_path) {
            $path = storage_path('app/'.$laporanHarian->doc_path);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function deleteLampiranFile(LaporanLampiran $lampiran): void
    {
        if ($lampiran->file_path && Storage::disk('local')->exists($lampiran->file_path)) {
            Storage::disk('local')->delete($lampiran->file_path);
        }
    }
}
