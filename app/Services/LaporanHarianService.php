<?php

namespace App\Services;

use App\Jobs\ProcessLaporanLampiran;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanAktivitas;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Traits\SortsQuery;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LaporanHarianService
{
    use SortsQuery;

    public const KATEGORI_LAINNYA = 'Lainnya';

    public const CUACA_FIELDS = ['suhu', 'cuaca_pagi_id', 'kelembaban_pagi_id', 'cuaca_siang_id', 'kelembaban_siang_id', 'cuaca_sore_id', 'kelembaban_sore_id'];

    public function previousDayDefaults(DataKapal $proyek, string $tanggal, array $drafts = []): array
    {
        abort_unless(auth()->user()?->canAccessDataKapal($proyek), 403);
        $date = Carbon::parse($tanggal)->locale('id')->startOfDay();
        $history = $proyek->laporanHarian()->whereDate('tanggal_laporan', '<', $date->toDateString())
            ->orderByDesc('tanggal_laporan')->orderByDesc('id')->get(['id', 'tanggal_laporan', 'is_libur']);
        $records = $history->unique(fn ($row) => $row->tanggal_laporan->toDateString())
            ->keyBy(fn ($row) => $row->tanggal_laporan->toDateString());
        $drafts = collect($drafts)->keyBy('tanggal_laporan');
        $firstDate = collect([$history->last()?->tanggal_laporan?->toDateString(), $drafts->keys()->min()])->filter()->min();
        $start = $proyek->tanggal_mulai_pelaksanaan?->copy()->startOfDay()
            ?? ($firstDate ? Carbon::parse($firstDate)->startOfDay() : $date->copy());
        $previous = $date->copy()->subDay();

        while ($previous->gte($start)) {
            $key = $previous->toDateString();
            $draft = $drafts->get($key);
            $record = $records->get($key);
            if (! $draft && ! $record) {
                $title = LaporanHarian::generateJudul($previous, $proyek);

                return ['values' => [], 'info' => "{$title} ({$previous->translatedFormat('d F Y')}) belum diisi. Silakan lengkapi juga laporan tersebut."];
            }
            if ((bool) ($draft['is_libur'] ?? $record?->is_libur)) {
                $previous->subDay();

                continue;
            }
            if ($draft) {
                $values = array_intersect_key($draft, array_flip(self::CUACA_FIELDS));
                foreach (['personel', 'peralatan', 'consumable', 'aktivitas'] as $relation) {
                    $values[$relation] = $draft[$relation] ?? [];
                }
            } else {
                $source = $proyek->laporanHarian()->with(['personel', 'peralatan', 'consumable', 'aktivitas'])->findOrFail($record->id);
                $values = $source->only(self::CUACA_FIELDS);
                $values['personel'] = $source->personel->map(fn ($row) => $row->only(['jabatan', 'jumlah', 'keterangan']))->all();
                foreach (['peralatan', 'consumable'] as $relation) {
                    $values[$relation] = $source->{$relation}->map(fn ($row) => $row->only(['jenis', 'jumlah', 'keterangan']))->all();
                }
                $values['aktivitas'] = $this->aktivitasRowsFrom($source);
            }
            foreach ($values['aktivitas'] as &$row) {
                $row = array_replace($this->newAktivitasRow($row['work_group_id'] ?? null), array_intersect_key($row, array_flip(['aktivitas', 'keterangan'])));
            }
            unset($row);
            foreach (['personel', 'peralatan', 'consumable'] as $relation) {
                $values[$relation] = array_map(fn ($row) => array_diff_key($row, array_flip(['id', 'row_key', 'laporan_harian_id'])), $values[$relation]);
            }

            return ['values' => $values, 'info' => 'Default dari laporan '.$previous->translatedFormat('d F Y').($draft ? ' (tab belum disimpan)' : '').'. Foto/dokumen diunggah sendiri.'];
        }

        return ['values' => [], 'info' => 'Belum ada laporan hari kerja sebelumnya. Isi data secara manual.'];
    }

    public function normalizeHari(array $data): array
    {
        $data['is_libur'] = (bool) ($data['is_libur'] ?? false);
        $data['alasan_libur'] = $data['is_libur'] ? trim($data['alasan_libur'] ?? '') : null;
        if ($data['is_libur'] && $data['alasan_libur'] === '') {
            throw ValidationException::withMessages(['alasan_libur' => 'Alasan libur wajib diisi.']);
        }

        return $data;
    }

    public function getFiltered(
        ?string $search = null,
        ?int $dataKapalId = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc',
        array $mingguFilter = [],
        array $bulanFilter = []
    ): LengthAwarePaginator {
        $query = LaporanHarian::with(['user', 'updatedBy:id,name', 'dataKapal.owner', 'dataKapal.galangan', 'lampiran'])
            ->byDataKapal($dataKapalId);

        if ($mingguFilter || $bulanFilter) {
            app(KontrakPeriodeService::class)->applyPeriodeFilter($query, $dataKapalId ? DataKapal::find($dataKapalId) : null, $mingguFilter, $bulanFilter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tanggal_laporan', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $this->applySorting($query, [
            'tanggal_laporan' => 'laporan_harian.tanggal_laporan',
            'judul' => 'laporan_harian.tanggal_laporan',
            'updated_at' => 'laporan_harian.updated_at',
        ], $sortField, $sortDir, 'tanggal_laporan', 'desc', 'laporan_harian.created_at', 'desc');

        $paginator = $query->paginate($perPage);

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
        return LaporanHarian::create($this->normalizeHari($data));
    }

    public function createMany(array $items): array
    {
        $created = [];

        DB::transaction(function () use ($items, &$created) {
            foreach ($items as $data) {
                $created[] = $this->create($data);
            }
        });

        return $created;
    }

    public function update(LaporanHarian $laporanHarian, array $data): LaporanHarian
    {
        $laporanHarian->update($this->normalizeHari(array_replace($laporanHarian->only(['is_libur', 'alasan_libur']), $data)));
        app(LaporanDocumentService::class)->invalidateHarian($laporanHarian);

        return $laporanHarian;
    }

    public function saveFormBatch(array $dataItems, array $forms, array $fotos, array $ncFotos): array
    {
        return DB::transaction(function () use ($dataItems, $forms, $fotos, $ncFotos) {
            $reports = [];
            $fotoCount = 0;
            foreach ($dataItems as $index => $data) {
                $report = $this->create($data);
                $reports[] = $report;
                $fotoCount += $this->syncFormDetails($report, $forms[$index], $fotos[$index] ?? [], $ncFotos[$index] ?? []);
            }

            return [$reports, $fotoCount];
        });
    }

    public function saveForm(LaporanHarian $report, array $data, array $form, array $fotos, array $ncFotos): int
    {
        return DB::transaction(function () use ($report, $data, $form, $fotos, $ncFotos) {
            $this->update($report, $data);

            return $this->syncFormDetails($report, $form, $fotos, $ncFotos);
        });
    }

    private function syncFormDetails(LaporanHarian $report, array $form, array $fotos, array $ncFotos): int
    {
        foreach (['personel' => 'jabatan', 'peralatan' => 'jenis', 'consumable' => 'jenis'] as $relation => $field) {
            $report->{$relation}()->delete();
            foreach ($form[$relation] ?? [] as $row) {
                $data = array_intersect_key($row, array_flip([$field, 'jumlah', 'keterangan']));
                $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
                if (array_filter($data, fn ($value) => $value !== null)) {
                    $report->{$relation}()->create($data);
                }
            }
        }
        $count = $this->syncAktivitas($report, $form['aktivitas'] ?? [], $fotos);
        $count += app(LaporanNcService::class)->syncNc($report, $form['nc'] ?? [], $ncFotos);

        return $count;
    }

    public function delete(LaporanHarian $laporanHarian): void
    {
        // Delete all lampiran files first
        foreach ($laporanHarian->lampiran as $lampiran) {
            $this->deleteLampiranFile($lampiran);
        }

        // Delete old single file if exists (backward compatibility)
        $this->deleteFile($laporanHarian);

        // Dokumen Word/PDF harian + mingguan/bulanan yang melampirkannya
        app(LaporanDocumentService::class)->invalidateHarian($laporanHarian);

        // NC milik laporan ini + file orphan-nya (file bisa dipakai NC lain)
        app(LaporanNcService::class)->deleteForLaporan($laporanHarian);

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
            'keterangan' => '',
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
            'keterangan' => $aktivitas->keterangan ?? '',
        ];
    }

    /**
     * Sinkronkan uraian aktivitas beserta foto lampirannya untuk satu laporan harian.
     * - Baris tanpa uraian, keterangan, dan foto baru diabaikan.
     * - Baris tersimpan (id milik laporan ini) di-update; yang tidak dikirim dihapus beserta fotonya.
     * - Foto baru disimpan ke temp lalu diproses async (crop + WebP) oleh ProcessLaporanLampiran.
     *
     * @param  array  $rows  [['row_key', 'id', 'work_group_id', 'aktivitas', 'keterangan'], ...]
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
                $keterangan = trim((string) ($row['keterangan'] ?? ''));

                if ($uraian === '' && $keterangan === '' && ! $file) {
                    continue;
                }

                $wgId = (int) ($row['work_group_id'] ?? 0);
                $wgId = $workGroups->has($wgId) ? $wgId : null;
                $attributes = [
                    'kurva_s_work_group_id' => $wgId,
                    'kategori' => $wgId ? $workGroups[$wgId] : null,
                    'aktivitas' => $uraian ?: null,
                    'keterangan' => $keterangan ?: null,
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

                    $jobs[] = [$lampiran, app(FileStorageService::class)->storeTemp($file, 'laporan-lampiran'), $foto['cropData'] ?? null];
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
            ProcessLaporanLampiran::dispatch($lampiran, $tempPath, $cropData)->afterCommit();
        }

        app(LaporanDocumentService::class)->invalidateHarian($laporan);

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

        return $this->groupAktivitasPerKategori($laporan->aktivitas);
    }

    /**
     * Kelompokkan kumpulan aktivitas (boleh lintas laporan harian, mis. Laporan
     * Mingguan) per kategori dengan urutan yang sama seperti aktivitasPerKategori().
     * Relasi workGroup sebaiknya sudah di-eager load.
     *
     * @param  Collection<int, LaporanAktivitas>  $aktivitas
     * @return Collection<int, array{nama: string, aktivitas: Collection<int, LaporanAktivitas>}>
     */
    public function groupAktivitasPerKategori(Collection $aktivitas): Collection
    {
        return $aktivitas
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

        if ($lampiran->laporanHarian) {
            app(LaporanDocumentService::class)->invalidateHarian($lampiran->laporanHarian);
        }
    }

    private function deleteFile(LaporanHarian $laporanHarian): void
    {
        if ($laporanHarian->file_path && Storage::disk()->exists($laporanHarian->file_path)) {
            Storage::disk()->delete($laporanHarian->file_path);
        }
    }

    private function deleteLampiranFile(LaporanLampiran $lampiran): void
    {
        if ($lampiran->file_path && Storage::disk()->exists($lampiran->file_path)) {
            Storage::disk()->delete($lampiran->file_path);
        }
    }
}
