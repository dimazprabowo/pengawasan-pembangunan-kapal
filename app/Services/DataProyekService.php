<?php

namespace App\Services;

use App\Enums\MaterialCatalog;
use App\Enums\MaterialStatus as DefaultMaterialStatus;
use App\Enums\PengujianCatalog;
use App\Enums\PengujianStatus as DefaultPengujianStatus;
use App\Enums\RapatCatalog;
use App\Enums\RapatJenis as DefaultRapatJenis;
use App\Enums\VendorCatalog;
use App\Jobs\ProcessPengujianFile;
use App\Jobs\ProcessRapatFile;
use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Models\MaterialCategory;
use App\Models\MaterialStatus;
use App\Models\Pengujian;
use App\Models\PengujianFile;
use App\Models\PengujianStatus;
use App\Models\ProjectMaterial;
use App\Models\ProjectVendor;
use App\Models\Rapat;
use App\Models\RapatFile;
use App\Models\RapatJenis;
use App\Models\VendorLingkup;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CRUD entity data umum per data kapal (drawing register, vendor,
 * material, pengujian, rapat) + agregasi untuk laporan bulanan.
 */
class DataProyekService
{
    use HasDynamicLike, SortsQuery;

    public const VALIDATION_MESSAGES = [
        'required' => 'Kolom :attribute wajib diisi.',
        'string' => 'Kolom :attribute harus berupa teks.',
        'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
        'min' => 'Nilai :attribute kurang dari batas yang diizinkan.',
        'max' => 'Nilai :attribute melebihi batas yang diizinkan.',
        'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
        'in' => 'Pilihan :attribute tidak tersedia.',
        'numeric' => 'Kolom :attribute harus berupa angka.',
    ];

    // ─── LIST PER ENTITY (paginated, named paginator per tab) ────────────

    public function getVendors(DataKapal $dataKapal, ?string $search, int $perPage = 10, string $sortField = 'nama', string $sortDir = 'asc'): LengthAwarePaginator
    {
        $query = $this->applySearch(
            $dataKapal->projectVendors()->getQuery()->reorder()
                ->join('vendor_lingkups', 'vendor_lingkups.id', '=', 'project_vendor.lingkup_id')
                ->select('project_vendor.*'),
            $search,
            ['project_vendor.nama', 'vendor_lingkups.nama', 'project_vendor.kontak']
        );
        $this->applySorting(
            $query,
            ['nama' => 'project_vendor.nama', 'lingkup_pekerjaan' => 'vendor_lingkups.nama', 'bulan_ke' => 'project_vendor.bulan_ke', 'kontak' => 'project_vendor.kontak', 'keterangan' => 'project_vendor.keterangan'],
            $sortField,
            $sortDir,
            'nama',
            tieBreakColumn: 'project_vendor.id',
            tieBreakDir: 'asc'
        );

        return $query->paginate(in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10, ['*'], 'vendorPage');
    }

    /**
     * Opsi "Bulan ke-" vendor = bulan yang tergenerate di kurva_s_rencana
     * (hasil generate plan) untuk work group kapal ini. Label diperkaya
     * dengan nama bulan kalender dari KontrakPeriodeService bila tersedia.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getBulanKeOptions(DataKapal $dataKapal): array
    {
        return collect($this->getBulanKeIds($dataKapal))
            ->map(function (int $bulanKe) use ($dataKapal) {
                $periode = app(KontrakPeriodeService::class)->getBulanPeriode($dataKapal, $bulanKe);

                return [
                    'value' => $bulanKe,
                    'label' => 'Bulan ke-'.$bulanKe.($periode ? ' ('.$periode['label'].')' : ''),
                ];
            })
            ->all();
    }

    /**
     * Daftar bulan_ke yang valid untuk vendor — hanya bulan yang ada di
     * rencana Kurva-S tergenerate milik kapal ini.
     *
     * @return array<int, int>
     */
    public function getBulanKeIds(DataKapal $dataKapal): array
    {
        return KurvaSRencana::whereHas('workGroup', fn ($q) => $q->where('data_kapal_id', $dataKapal->id))
            ->distinct()
            ->orderBy('bulan_ke')
            ->pluck('bulan_ke')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Vendor untuk laporan bulanan, dikelompokkan per lingkup pekerjaan
     * (sub-bab 2.3) — kumulatif: vendor dengan bulan_ke <= bulan laporan
     * ikut tampil di bulan tersebut dan seterusnya. bulanKe null (laporan
     * tanpa bulan_ke) menampilkan semua vendor. Key = lingkup_id (urutan katalog).
     *
     * @return Collection<int, Collection<int, ProjectVendor>>
     */
    public function getVendorsUpToBulanGrouped(DataKapal $dataKapal, ?int $bulanKe): Collection
    {
        return $dataKapal->projectVendors()
            ->with('lingkup')
            ->when($bulanKe, fn ($q) => $q->where('bulan_ke', '<=', $bulanKe))
            ->reorder()
            ->orderBy('bulan_ke')
            ->orderBy('nama')
            ->get()
            ->groupBy('lingkup_id')
            ->sortKeys();
    }

    public function getMaterials(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = [], string $sortField = 'nama_item', string $sortDir = 'asc'): LengthAwarePaginator
    {
        $query = $this->applySearch(
            $dataKapal->projectMaterials()->getQuery()->reorder()
                ->join('material_categories', 'material_categories.id', '=', 'project_material.material_category_id')
                ->join('material_statuses', 'material_statuses.id', '=', 'project_material.material_status_id')
                ->select('project_material.*'),
            $search,
            ['project_material.nama_item', 'project_material.spesifikasi', 'project_material.keterangan']
        )
            ->when($filters['kategori'] ?? null, fn ($q, $v) => $q->where('project_material.material_category_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('project_material.material_status_id', $v));
        $this->applySorting(
            $query,
            [
                'nama_item' => 'project_material.nama_item',
                'kategori' => 'material_categories.nama',
                'jumlah' => 'project_material.jumlah',
                'status' => 'material_statuses.nama',
                'tanggal' => 'project_material.tanggal',
            ],
            $sortField,
            $sortDir,
            'nama_item',
            tieBreakColumn: 'project_material.id',
            tieBreakDir: 'asc'
        );

        return $query->paginate(in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10, ['*'], 'materialPage');
    }

    public function getPengujians(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = [], string $sortField = 'tanggal', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $query = $this->applySearch(
            $dataKapal->pengujians()->getQuery()->reorder()
                ->join('pengujian_statuses', 'pengujian_statuses.id', '=', 'pengujian.pengujian_status_id')
                ->select('pengujian.*')
                ->with('files'),
            $search,
            ['pengujian.nama_pengujian', 'pengujian.hasil', 'pengujian.keterangan']
        )
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('pengujian.pengujian_status_id', $v));
        $this->applySorting(
            $query,
            [
                'nama_pengujian' => 'pengujian.nama_pengujian',
                'tanggal' => 'pengujian.tanggal',
                'status' => 'pengujian_statuses.nama',
                'hasil' => 'pengujian.hasil',
            ],
            $sortField,
            $sortDir,
            'tanggal',
            tieBreakColumn: 'pengujian.id',
            tieBreakDir: 'desc'
        );

        return $query->paginate(in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10, ['*'], 'pengujianPage');
    }

    public function getRapats(DataKapal $dataKapal, ?string $search, int $perPage = 10, array $filters = [], string $sortField = 'tanggal', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $query = $this->applySearch(
            $dataKapal->rapats()->getQuery()->reorder()
                ->join('rapat_jenis', 'rapat_jenis.id', '=', 'rapat.rapat_jenis_id')
                ->select('rapat.*')
                ->with(['files', 'jenis']),
            $search,
            ['rapat.lokasi', 'rapat.topik']
        )
            ->when($filters['jenis'] ?? null, fn ($q, $v) => $q->where('rapat.rapat_jenis_id', $v));
        $this->applySorting(
            $query,
            ['tanggal' => 'rapat.tanggal', 'jenis' => 'rapat_jenis.nama', 'lokasi' => 'rapat.lokasi', 'topik' => 'rapat.topik'],
            $sortField,
            $sortDir,
            'tanggal',
            tieBreakColumn: 'rapat.id',
            tieBreakDir: 'desc'
        );

        return $query->paginate(in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10, ['*'], 'rapatPage');
    }

    private function applySearch($query, ?string $search, array $columns)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $col) {
                $q->orWhere($col, $this->getLikeOperator(), "%{$search}%");
            }
        });
    }

    // ─── SAVE / DELETE ───────────────────────────────────────────────────

    public function findVendor(DataKapal $dataKapal, int $id): ProjectVendor
    {
        return $dataKapal->projectVendors()->findOrFail($id);
    }

    /**
     * Lingkup default baris vendor baru — lingkup pertama di katalog.
     */
    public function defaultVendorLingkupId(): ?int
    {
        return VendorLingkup::orderBy('nama')->value('id');
    }

    public function findMaterial(DataKapal $dataKapal, int $id): ProjectMaterial
    {
        return $dataKapal->projectMaterials()->findOrFail($id);
    }

    /**
     * Status default baris material baru — "direncanakan" bila ada di
     * katalog, fallback status pertama yang tersedia.
     */
    public function defaultMaterialStatusId(): ?int
    {
        return MaterialStatus::where('nama', DefaultMaterialStatus::Direncanakan->value)->value('id')
            ?? MaterialStatus::orderBy('id')->value('id');
    }

    public function saveVendor(DataKapal $dataKapal, array $data, ?ProjectVendor $model = null): ProjectVendor
    {
        $data['nama'] = trim($data['nama'] ?? '');
        $data['lingkup_id'] = (int) ($data['lingkup_id'] ?? 0);
        $data['bulan_ke'] = (int) ($data['bulan_ke'] ?? 0);
        foreach (['kontak', 'keterangan'] as $nullable) {
            $data[$nullable] = filled($data[$nullable] ?? null) ? trim($data[$nullable]) : null;
        }

        return tap($model ?? new ProjectVendor, function (ProjectVendor $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function saveMaterial(DataKapal $dataKapal, array $data, ?ProjectMaterial $model = null): ProjectMaterial
    {
        $data['nama_item'] = trim($data['nama_item'] ?? '');
        $data['material_category_id'] = (int) ($data['material_category_id'] ?? 0);
        $data['material_status_id'] = (int) ($data['material_status_id'] ?? 0);
        $data['jumlah'] = filled($data['jumlah'] ?? null) ? $data['jumlah'] : null;
        $data['tanggal'] = filled($data['tanggal'] ?? null) ? $data['tanggal'] : null;
        foreach (['spesifikasi', 'satuan', 'keterangan'] as $nullable) {
            $data[$nullable] = filled($data[$nullable] ?? null) ? trim($data[$nullable]) : null;
        }

        return tap($model ?? new ProjectMaterial, function (ProjectMaterial $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function findPengujian(DataKapal $dataKapal, int $id): Pengujian
    {
        return $dataKapal->pengujians()->findOrFail($id);
    }

    /**
     * Status default baris pengujian baru — "direncanakan" bila ada di
     * katalog, fallback status pertama yang tersedia.
     */
    public function defaultPengujianStatusId(): ?int
    {
        return PengujianStatus::where('nama', DefaultPengujianStatus::Direncanakan->value)->value('id')
            ?? PengujianStatus::orderBy('id')->value('id');
    }

    public function savePengujian(DataKapal $dataKapal, array $data, ?Pengujian $model = null): Pengujian
    {
        $data['nama_pengujian'] = trim($data['nama_pengujian'] ?? '');
        $data['pengujian_status_id'] = (int) ($data['pengujian_status_id'] ?? 0);
        $data['tanggal'] = filled($data['tanggal'] ?? null) ? $data['tanggal'] : null;
        foreach (['hasil', 'keterangan'] as $nullable) {
            $data[$nullable] = filled($data[$nullable] ?? null) ? trim($data[$nullable]) : null;
        }

        return tap($model ?? new Pengujian, function (Pengujian $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function findRapat(DataKapal $dataKapal, int $id): Rapat
    {
        return $dataKapal->rapats()->findOrFail($id);
    }

    public function saveRapat(DataKapal $dataKapal, array $data, ?Rapat $model = null): Rapat
    {
        $data['tanggal'] = filled($data['tanggal'] ?? null) ? $data['tanggal'] : null;
        $data['rapat_jenis_id'] = (int) ($data['rapat_jenis_id'] ?? 0);
        foreach (['lokasi', 'topik'] as $nullable) {
            $data[$nullable] = filled($data[$nullable] ?? null) ? trim($data[$nullable]) : null;
        }

        return tap($model ?? new Rapat, function (Rapat $m) use ($dataKapal, $data) {
            $m->fill($data + ['data_kapal_id' => $dataKapal->id]);
            $m->data_kapal_id ??= $dataKapal->id;
            $m->save();
        });
    }

    public function findRapatFile(DataKapal $dataKapal, int $id): RapatFile
    {
        return RapatFile::whereHas('rapat', fn ($q) => $q->where('data_kapal_id', $dataKapal->id))
            ->with('rapat')
            ->findOrFail($id);
    }

    /**
     * Simpan file lampiran rapat (gambar/PDF) — record pending lalu diproses
     * async oleh ProcessRapatFile (gambar: crop + WebP; PDF: apa adanya).
     */
    public function addRapatFiles(Rapat $rapat, array $entries): void
    {
        $files = app(FileStorageService::class);
        $jobs = DB::transaction(function () use ($rapat, $entries, $files) {
            return collect($entries)->map(function ($entry) use ($rapat, $files) {
                $file = $entry['file'];
                validator(['file' => $file], ['file' => file_upload_validation_rule('rapat_file', true)])->validate();
                $tempPath = $files->storeTemp($file, 'proyek-rapat');
                $record = $rapat->files()->create([
                    'file_name' => basename($file->getClientOriginalName()),
                    'file_size' => $file->getSize(),
                    'file_status' => 'pending',
                    'uploaded_by' => auth()->id(),
                ]);

                return [$record, $tempPath, $entry['cropData'] ?? null];
            })->all();
        });

        foreach ($jobs as [$record, $tempPath, $cropData]) {
            ProcessRapatFile::dispatch($record, $tempPath, $cropData)->afterCommit();
        }
    }

    public function deleteRapatFile(RapatFile $file): void
    {
        if ($file->file_path && Storage::disk()->exists($file->file_path)) {
            Storage::disk()->delete($file->file_path);
        }
        $file->delete();
    }

    /**
     * Hapus rapat beserta seluruh file lampirannya (record + file fisik).
     */
    public function deleteRapat(Rapat $rapat): void
    {
        $rapat->loadMissing('files');
        foreach ($rapat->files as $file) {
            $this->deleteRapatFile($file);
        }
        $rapat->delete();
    }

    public function findPengujianFile(DataKapal $dataKapal, int $id): PengujianFile
    {
        return PengujianFile::whereHas('pengujian', fn ($q) => $q->where('data_kapal_id', $dataKapal->id))
            ->with('pengujian')
            ->findOrFail($id);
    }

    /**
     * Simpan file lampiran pengujian (gambar/PDF) — record pending lalu diproses
     * async oleh ProcessPengujianFile (gambar: crop + WebP; PDF: apa adanya).
     */
    public function addPengujianFiles(Pengujian $pengujian, array $entries): void
    {
        $files = app(FileStorageService::class);
        $jobs = DB::transaction(function () use ($pengujian, $entries, $files) {
            return collect($entries)->map(function ($entry) use ($pengujian, $files) {
                $file = $entry['file'];
                validator(['file' => $file], ['file' => file_upload_validation_rule('pengujian_file', true)])->validate();
                $tempPath = $files->storeTemp($file, 'proyek-pengujian');
                $record = $pengujian->files()->create([
                    'file_name' => basename($file->getClientOriginalName()),
                    'file_size' => $file->getSize(),
                    'file_status' => 'pending',
                    'uploaded_by' => auth()->id(),
                ]);

                return [$record, $tempPath, $entry['cropData'] ?? null];
            })->all();
        });

        foreach ($jobs as [$record, $tempPath, $cropData]) {
            ProcessPengujianFile::dispatch($record, $tempPath, $cropData)->afterCommit();
        }
    }

    public function deletePengujianFile(PengujianFile $file): void
    {
        if ($file->file_path && Storage::disk()->exists($file->file_path)) {
            Storage::disk()->delete($file->file_path);
        }
        $file->delete();
    }

    /**
     * Hapus pengujian beserta seluruh file lampirannya (record + file fisik).
     */
    public function deletePengujian(Pengujian $pengujian): void
    {
        $pengujian->loadMissing('files');
        foreach ($pengujian->files as $file) {
            $this->deletePengujianFile($file);
        }
        $pengujian->delete();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    /**
     * Simpan daftar penandatangan lembar pengesahan laporan bulanan
     * (disimpan sebagai JSON di data_kapal.penandatangan — urutan = urutan TTD).
     */
    public function savePenandatangan(DataKapal $dataKapal, array $rows): void
    {
        $dataKapal->update(['penandatangan' => $this->normalizePenandatangan($rows)]);
    }

    /**
     * Normalisasi input penandatangan: trim, buang baris tanpa nama,
     * hapus key internal (row_key), reindex. Label kosong diisi
     * default urutan (Dipersiapkan, Diketahui, Menyetujui, ...).
     */
    private function normalizePenandatangan(?array $rows): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $defaultLabels = ['Dipersiapkan', 'Diketahui', 'Menyetujui', 'Disahkan'];

        $normalized = collect($rows)
            ->map(fn ($row) => [
                'label' => trim((string) ($row['label'] ?? '')),
                'nama' => trim((string) ($row['nama'] ?? '')),
                'jabatan' => trim((string) ($row['jabatan'] ?? '')),
                'instansi' => trim((string) ($row['instansi'] ?? '')),
            ])
            ->filter(fn ($row) => $row['nama'] !== '')
            ->values()
            ->map(fn ($row, $i) => $row['label'] === ''
                ? $row + ['label' => $defaultLabels[$i] ?? 'Penandatangan '.($i + 1)]
                : $row)
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    // ─── KATALOG MATERIAL (kategori & status bersama lintas kapal) ────────

    /**
     * @return Collection<int, MaterialCategory|MaterialStatus>
     */
    public function materialCatalogs(MaterialCatalog $kind): Collection
    {
        return $kind->modelClass()::orderBy('nama')->get();
    }

    public function paginateMaterialCatalog(MaterialCatalog $kind): LengthAwarePaginator
    {
        return $kind->modelClass()::orderBy('nama')->paginate(10, ['*'], 'catalogPage');
    }

    public function findMaterialCatalog(MaterialCatalog $kind, int $id): MaterialCategory|MaterialStatus
    {
        return $kind->modelClass()::findOrFail($id);
    }

    public function saveMaterialCatalog(MaterialCatalog $kind, string $name, ?int $id = null): MaterialCategory|MaterialStatus
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $data = Validator::make(['nama' => $name], ['nama' => ['required', 'string', 'max:100']], messages: self::VALIDATION_MESSAGES, attributes: ['nama' => mb_strtolower($kind->label())])->validate();
        $class = $kind->modelClass();
        if ($class::whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['nama' => $kind->label().' tersebut sudah tersedia.']);
        }
        $model = $id ? $this->findMaterialCatalog($kind, $id) : new $class;
        $model->fill($data)->save();

        return $model;
    }

    public function deleteMaterialCatalog(MaterialCatalog $kind, int $id): void
    {
        DB::transaction(function () use ($kind, $id) {
            $model = $kind->modelClass()::lockForUpdate()->findOrFail($id);
            if ($model->materials()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['nama' => $kind->label().' sudah digunakan dan tidak dapat dihapus.']);
            }
            $model->delete();
        });
    }

    // ─── KATALOG PENGUJIAN (status bersama lintas kapal) ─────────────────

    /**
     * @return Collection<int, PengujianStatus>
     */
    public function pengujianCatalogs(PengujianCatalog $kind): Collection
    {
        return $kind->modelClass()::orderBy('nama')->get();
    }

    public function paginatePengujianCatalog(PengujianCatalog $kind): LengthAwarePaginator
    {
        return $kind->modelClass()::orderBy('nama')->paginate(10, ['*'], 'catalogPage');
    }

    public function findPengujianCatalog(PengujianCatalog $kind, int $id): PengujianStatus
    {
        return $kind->modelClass()::findOrFail($id);
    }

    public function savePengujianCatalog(PengujianCatalog $kind, string $name, ?int $id = null): PengujianStatus
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $data = Validator::make(['nama' => $name], ['nama' => ['required', 'string', 'max:100']], messages: self::VALIDATION_MESSAGES, attributes: ['nama' => mb_strtolower($kind->label())])->validate();
        $class = $kind->modelClass();
        if ($class::whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['nama' => $kind->label().' tersebut sudah tersedia.']);
        }
        $model = $id ? $this->findPengujianCatalog($kind, $id) : new $class;
        $model->fill($data)->save();

        return $model;
    }

    public function deletePengujianCatalog(PengujianCatalog $kind, int $id): void
    {
        DB::transaction(function () use ($kind, $id) {
            $model = $kind->modelClass()::lockForUpdate()->findOrFail($id);
            if ($model->pengujians()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['nama' => $kind->label().' sudah digunakan dan tidak dapat dihapus.']);
            }
            $model->delete();
        });
    }

    /**
     * Jenis default baris rapat baru — "mingguan" bila ada di katalog,
     * fallback jenis pertama yang tersedia.
     */
    public function defaultRapatJenisId(): ?int
    {
        return RapatJenis::where('nama', DefaultRapatJenis::Mingguan->value)->value('id')
            ?? RapatJenis::orderBy('id')->value('id');
    }

    // ─── KATALOG RAPAT (jenis bersama lintas kapal) ──────────────────────

    /**
     * @return Collection<int, RapatJenis>
     */
    public function rapatCatalogs(RapatCatalog $kind): Collection
    {
        return $kind->modelClass()::orderBy('nama')->get();
    }

    public function paginateRapatCatalog(RapatCatalog $kind): LengthAwarePaginator
    {
        return $kind->modelClass()::orderBy('nama')->paginate(10, ['*'], 'catalogPage');
    }

    public function findRapatCatalog(RapatCatalog $kind, int $id): RapatJenis
    {
        return $kind->modelClass()::findOrFail($id);
    }

    public function saveRapatCatalog(RapatCatalog $kind, string $name, ?int $id = null): RapatJenis
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $data = Validator::make(['nama' => $name], ['nama' => ['required', 'string', 'max:100']], messages: self::VALIDATION_MESSAGES, attributes: ['nama' => mb_strtolower($kind->label())])->validate();
        $class = $kind->modelClass();
        if ($class::whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['nama' => $kind->label().' tersebut sudah tersedia.']);
        }
        $model = $id ? $this->findRapatCatalog($kind, $id) : new $class;
        $model->fill($data)->save();

        return $model;
    }

    public function deleteRapatCatalog(RapatCatalog $kind, int $id): void
    {
        DB::transaction(function () use ($kind, $id) {
            $model = $kind->modelClass()::lockForUpdate()->findOrFail($id);
            if ($model->rapats()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['nama' => $kind->label().' sudah digunakan dan tidak dapat dihapus.']);
            }
            $model->delete();
        });
    }

    // ─── KATALOG VENDOR (lingkup pekerjaan bersama lintas kapal) ─────────

    /**
     * @return Collection<int, VendorLingkup>
     */
    public function vendorLingkups(VendorCatalog $kind): Collection
    {
        return $kind->modelClass()::orderBy('nama')->get();
    }

    public function paginateVendorLingkup(VendorCatalog $kind): LengthAwarePaginator
    {
        return $kind->modelClass()::orderBy('nama')->paginate(10, ['*'], 'catalogPage');
    }

    public function findVendorLingkup(VendorCatalog $kind, int $id): VendorLingkup
    {
        return $kind->modelClass()::findOrFail($id);
    }

    public function saveVendorLingkup(VendorCatalog $kind, string $name, ?int $id = null): VendorLingkup
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $data = Validator::make(['nama' => $name], ['nama' => ['required', 'string', 'max:100']], messages: self::VALIDATION_MESSAGES, attributes: ['nama' => mb_strtolower($kind->label())])->validate();
        $class = $kind->modelClass();
        if ($class::whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['nama' => $kind->label().' tersebut sudah tersedia.']);
        }
        $model = $id ? $this->findVendorLingkup($kind, $id) : new $class;
        $model->fill($data)->save();

        return $model;
    }

    public function deleteVendorLingkup(VendorCatalog $kind, int $id): void
    {
        DB::transaction(function () use ($kind, $id) {
            $model = $kind->modelClass()::lockForUpdate()->findOrFail($id);
            if ($model->vendors()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['nama' => $kind->label().' sudah digunakan dan tidak dapat dihapus.']);
            }
            $model->delete();
        });
    }

    // ─── DATA UNTUK LAPORAN BULANAN ──────────────────────────────────────

    /**
     * Material dikelompokkan per kategori (untuk tabel sub-bab 2.5) — kumulatif:
     * material dengan tanggal <= cutoff laporan ikut; tanggal null (belum
     * dijadwalkan) selalu ikut. Key = material_category_id (urutan katalog).
     *
     * @return Collection<int, Collection<int, ProjectMaterial>>
     */
    public function getMaterialsGrouped(DataKapal $dataKapal, ?CarbonInterface $selesai = null): Collection
    {
        return $dataKapal->projectMaterials()
            ->with(['category', 'status'])
            ->when($selesai, fn ($q) => $q->where(fn ($q) => $q->whereNull('tanggal')->orWhere('tanggal', '<=', $selesai->toDateString())))
            ->reorder()
            ->orderBy('nama_item')
            ->get()
            ->groupBy('material_category_id')
            ->sortKeys();
    }

    /**
     * Rapat dalam rentang periode (sub-bab 2.8).
     */
    public function getRapatsInPeriode(DataKapal $dataKapal, CarbonInterface $mulai, CarbonInterface $selesai): Collection
    {
        return $dataKapal->rapats()
            ->with('jenis')
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get();
    }

    /**
     * Pengujian sampai tanggal cut-off (sub-bab 2.7).
     */
    public function getPengujiansUpTo(DataKapal $dataKapal, ?CarbonInterface $selesai = null): Collection
    {
        return $dataKapal->pengujians()
            ->with('status')
            ->when($selesai, fn ($q) => $q->where(function ($q) use ($selesai) {
                $q->whereNull('tanggal')->orWhere('tanggal', '<=', $selesai->toDateString());
            }))
            ->get();
    }
}
