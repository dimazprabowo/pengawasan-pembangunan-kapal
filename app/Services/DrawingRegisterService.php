<?php

namespace App\Services;

use App\Enums\DrawingCatalog;
use App\Enums\DrawingStatus as DefaultStatus;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use App\Models\DrawingRevision;
use App\Models\DrawingStatus;
use App\Models\DrawingType;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DrawingRegisterService
{
    use HasDynamicLike, SortsQuery;

    public const VALIDATION_MESSAGES = [
        'required' => 'Kolom :attribute wajib diisi.',
        'string' => 'Kolom :attribute harus berupa teks.',
        'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
        'min' => 'Nilai :attribute kurang dari batas yang diizinkan.',
        'max' => 'Nilai :attribute melebihi batas yang diizinkan.',
        'date_format' => 'Kolom :attribute harus berupa tanggal yang valid.',
        'exists' => 'Pilihan :attribute tidak tersedia.',
    ];

    public function query(DataKapal $ship): Builder
    {
        return DrawingRevision::query()->whereHas('drawing', fn ($q) => $q->where('data_kapal_id', $ship->id))->with(['drawing.type', 'status']);
    }

    public function getFiltered(DataKapal $ship, string $search = '', array $filters = [], int $perPage = 10, string $sortField = 'nama', string $sortDir = 'asc'): LengthAwarePaginator
    {
        $cutoff = Validator::make(['tanggal' => $filters['tanggal'] ?? null], ['tanggal' => 'nullable|date_format:Y-m-d'])->valid()['tanggal'] ?? null;
        $query = $this->query($ship)
            ->join('drawing_register', 'drawing_register.id', '=', 'drawing_revisions.drawing_register_id')
            ->join('drawing_types', 'drawing_types.id', '=', 'drawing_register.drawing_type_id')
            ->join('drawing_statuses', 'drawing_statuses.id', '=', 'drawing_revisions.drawing_status_id')
            ->select('drawing_revisions.*')
            ->when($search !== '', fn ($q) => $q->where('drawing_register.nama', $this->getLikeOperator(), '%'.$search.'%'))
            ->when($filters['status'] ?? '', fn ($q, $v) => $q->where('drawing_revisions.drawing_status_id', $v))
            ->when($filters['type'] ?? '', fn ($q, $v) => $q->where('drawing_register.drawing_type_id', $v))
            ->when($cutoff, fn ($q, $v) => $q->whereDate('drawing_revisions.tanggal', '<=', $v));
        $this->applySorting($query, ['nama' => 'drawing_register.nama', 'jenis' => 'drawing_types.nama', 'revisi' => 'drawing_revisions.revisi', 'tanggal' => 'drawing_revisions.tanggal', 'status' => 'drawing_statuses.nama'], $sortField, $sortDir, 'nama', tieBreakColumn: 'drawing_revisions.id', tieBreakDir: 'asc');

        return $query->paginate(in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10, ['*'], 'drawingPage');
    }

    public function findDrawing(DataKapal $ship, int $id): DrawingRegister
    {
        return $ship->drawingRegisters()->findOrFail($id);
    }

    public function findRevision(DataKapal $ship, int $id): DrawingRevision
    {
        return $this->query($ship)->findOrFail($id);
    }

    public function saveRevision(DataKapal $ship, array $data, ?int $drawingId = null, ?int $revisionId = null): DrawingRevision
    {
        $data['nama'] = trim($data['nama'] ?? '');
        $data = Validator::make($data, [
            'nama' => 'required|string|max:255',
            'drawing_type_id' => ['required', 'integer', Rule::exists('drawing_types', 'id')],
            'revisi' => 'required|integer|min:0|max:65535',
            'tanggal' => 'required|date_format:Y-m-d',
            'drawing_status_id' => ['required', 'integer', Rule::exists('drawing_statuses', 'id')],
        ], messages: self::VALIDATION_MESSAGES, attributes: ['nama' => 'nama gambar', 'drawing_type_id' => 'jenis gambar', 'drawing_status_id' => 'status', 'revisi' => 'revisi gambar', 'tanggal' => 'tanggal revisi'])->validate();

        return DB::transaction(function () use ($ship, $data, $drawingId, $revisionId) {
            $drawing = $drawingId ? $ship->drawingRegisters()->lockForUpdate()->findOrFail($drawingId) : new DrawingRegister(['data_kapal_id' => $ship->id]);
            $revision = $revisionId ? $drawing->revisions()->findOrFail($revisionId) : new DrawingRevision;
            if ($drawing->exists && $drawing->revisions()->where('revisi', $data['revisi'])->when($revision->exists, fn ($q) => $q->whereKeyNot($revision->id))->exists()) {
                throw ValidationException::withMessages(['revisi' => 'Nomor revisi ini sudah digunakan pada gambar yang sama.']);
            }
            if ($drawing->exists) {
                $invalidDate = $drawing->revisions()->when($revision->exists, fn ($q) => $q->whereKeyNot($revision->id))
                    ->where(fn ($q) => $q->where(fn ($q) => $q->where('revisi', '<', $data['revisi'])->whereDate('tanggal', '>', $data['tanggal']))
                        ->orWhere(fn ($q) => $q->where('revisi', '>', $data['revisi'])->whereDate('tanggal', '<', $data['tanggal'])))->exists();
                if ($invalidDate) {
                    throw ValidationException::withMessages(['tanggal' => 'Tanggal harus mengikuti urutan revisi gambar.']);
                }
            }
            $drawing->fill(collect($data)->only(['nama', 'drawing_type_id'])->all())->save();
            $revision->fill(collect($data)->only(['revisi', 'tanggal', 'drawing_status_id'])->all());
            $revision->drawing()->associate($drawing);
            $revision->save();

            return $revision->load(['drawing.type', 'status']);
        });
    }

    public function historyUpTo(DataKapal $ship, CarbonInterface $cutoff): Collection
    {
        return $this->query($ship)->whereDate('tanggal', '<=', $cutoff->toDateString())
            ->join('drawing_register', 'drawing_register.id', '=', 'drawing_revisions.drawing_register_id')
            ->select('drawing_revisions.*')->orderBy('drawing_register.nama')->orderBy('tanggal')->orderBy('revisi')->get();
    }

    public function saveContract(DataKapal $ship, ?string $number): void
    {
        $data = Validator::make(['nomor_kontrak_gambar' => filled($number) ? trim($number) : null], ['nomor_kontrak_gambar' => 'nullable|string|max:150'], self::VALIDATION_MESSAGES)->validate();
        $ship->update($data);
    }

    public function nextRevisionNumber(DrawingRegister $drawing): int
    {
        return (int) $drawing->revisions()->max('revisi') + 1;
    }

    public function defaultStatusId(): ?int
    {
        return DrawingStatus::where('nama', DefaultStatus::InProgress->value)->value('id') ?? DrawingStatus::orderBy('id')->value('id');
    }

    public function catalogs(DrawingCatalog $kind): Collection
    {
        return $kind->modelClass()::orderBy('nama')->get();
    }

    public function paginateCatalog(DrawingCatalog $kind): LengthAwarePaginator
    {
        return $kind->modelClass()::orderBy('nama')->paginate(10, ['*'], 'catalogPage');
    }

    public function findCatalog(DrawingCatalog $kind, int $id): DrawingType|DrawingStatus
    {
        return $kind->modelClass()::findOrFail($id);
    }

    public function saveCatalog(DrawingCatalog $kind, string $name, ?int $id = null): DrawingType|DrawingStatus
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $data = Validator::make(['nama' => $name], ['nama' => ['required', 'string', 'max:100']], messages: self::VALIDATION_MESSAGES, attributes: ['nama' => mb_strtolower($kind->label())])->validate();
        $class = $kind->modelClass();
        if ($class::whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['nama' => $kind->label().' tersebut sudah tersedia.']);
        }
        $model = $id ? $this->findCatalog($kind, $id) : new $class;
        $model->fill($data)->save();

        return $model;
    }

    public function deleteCatalog(DrawingCatalog $kind, int $id): void
    {
        DB::transaction(function () use ($kind, $id) {
            $model = $kind->modelClass()::lockForUpdate()->findOrFail($id);
            $used = $kind === DrawingCatalog::Type ? $model->drawings()->withTrashed()->exists() : $model->revisions()->exists();
            if ($used) {
                throw ValidationException::withMessages(['nama' => $kind->label().' sudah digunakan dan tidak dapat dihapus.']);
            }
            $model->delete();
        });
    }

    public function deleteRevision(DrawingRevision $revision): void
    {
        DB::transaction(function () use ($revision) {
            $drawing = $revision->drawing()->lockForUpdate()->firstOrFail();
            $revision->delete();
            if (! $drawing->revisions()->exists()) {
                $drawing->delete();
            }
        });
    }
}
