<?php

namespace App\Services;

use App\Enums\NcFormJenis;
use App\Enums\NcFormStatus;
use App\Enums\NcStatus;
use App\Jobs\ProcessNcFormFile;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Alur Form NC (Non Conformance Report):
 * 1. Form Temuan  — per laporan harian, berisi NC hari itu yang belum termuat
 *    di form temuan bertanda tangan. Draft disegarkan setiap generate ulang.
 * 2. Form Penutupan — lintas hari, berisi NC Open terpilih yang belum ada di
 *    draft form penutupan lain. Isi draft tetap (sesuai dokumen yang dicetak).
 * 3. Upload scan TTD → form terkunci. Untuk form penutupan, semua NC Open di
 *    dalamnya langsung Closed dalam transaksi yang sama.
 */
class LaporanNcFormService
{
    use HasDynamicLike;
    use SortsQuery;

    public function __construct(
        private LaporanNcWordService $wordService,
    ) {}

    /**
     * Daftar Form NC per kapal (panel Form NC di List NC) — search nomor,
     * filter jenis & status, sorting whitelist, paginator 'formPage'.
     */
    public function getForKapal(
        int $dataKapalId,
        int $perPage = 10,
        string $pageName = 'formPage',
        string $search = '',
        string $jenisFilter = '',
        string $statusFilter = '',
        string $sortField = '',
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $like = $this->getLikeOperator();

        $query = LaporanNcForm::with(['laporanHarian:id,tanggal_laporan', 'createdBy:id,name', 'signedBy:id,name'])
            ->withCount('ncs')
            ->where('data_kapal_id', $dataKapalId)
            ->when($search !== '', fn ($q) => $q->where('nomor', $like, "%{$search}%"))
            ->when($jenisFilter !== '', fn ($q) => $q->where('jenis', $jenisFilter))
            ->when($statusFilter === NcFormStatus::MenungguTtd->value, fn ($q) => $q->whereNull('file_status'))
            ->when($statusFilter === NcFormStatus::Diproses->value, fn ($q) => $q->whereIn('file_status', ['pending', 'processing']))
            ->when($statusFilter === NcFormStatus::Selesai->value, fn ($q) => $q->where('file_status', 'completed'))
            ->when($statusFilter === NcFormStatus::Gagal->value, fn ($q) => $q->where('file_status', 'failed'));

        $this->applySorting($query, [
            'nomor' => 'nomor',
            'jenis' => 'jenis',
            'isi' => '(select count(*) from laporan_nc_form_item where laporan_nc_form_id = laporan_nc_form.id)',
            'status' => "(case when file_status is null then 0 when file_status in ('pending', 'processing') then 1 when file_status = 'completed' then 2 else 3 end)",
            'created' => 'id',
        ], $sortField, $sortDir, 'created', 'desc');

        return $query->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Form temuan per laporan harian (untuk badge di baris grup List NC).
     *
     * @param  array<int>  $laporanIds
     * @return Collection<int, Collection<int, LaporanNcForm>> keyBy laporan_harian_id
     */
    public function temuanByLaporan(array $laporanIds): Collection
    {
        return LaporanNcForm::where('jenis', NcFormJenis::Temuan)
            ->whereIn('laporan_harian_id', $laporanIds ?: [0])
            ->orderByDesc('id')
            ->get()
            ->groupBy('laporan_harian_id');
    }

    /**
     * Generate (atau segarkan) draft Form Temuan untuk satu laporan harian.
     */
    public function createTemuan(LaporanHarian $laporan): LaporanNcForm
    {
        return DB::transaction(function () use ($laporan) {
            $ncIds = $laporan->ncs()
                ->whereDoesntHave('forms', fn (Builder $q) => $q->where('jenis', NcFormJenis::Temuan)->whereNotNull('signed_at'))
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($ncIds === []) {
                throw ValidationException::withMessages([
                    'form' => 'Tidak ada NC pada laporan ini yang belum masuk Form Temuan bertanda tangan.',
                ]);
            }

            $form = LaporanNcForm::where('laporan_harian_id', $laporan->id)
                ->where('jenis', NcFormJenis::Temuan)
                ->whereNull('signed_at')
                ->first()
                ?? LaporanNcForm::create([
                    'data_kapal_id' => $laporan->data_kapal_id,
                    'laporan_harian_id' => $laporan->id,
                    'jenis' => NcFormJenis::Temuan,
                    'nomor' => $this->nextNomor($laporan->data_kapal_id),
                    'tanggal' => $laporan->tanggal_laporan,
                ]);

            $form->ncs()->sync($ncIds);

            return $form;
        });
    }

    /**
     * Buat draft Form Penutupan dari NC Open terpilih (boleh lintas hari).
     *
     * @param  array<int>  $ncIds
     * @return array{0: LaporanNcForm, 1: int} [form, jumlah NC yang dilewati]
     */
    public function createPenutupan(int $dataKapalId, array $ncIds): array
    {
        return DB::transaction(function () use ($dataKapalId, $ncIds) {
            $eligible = $this->eligiblePenutupanQuery($dataKapalId)
                ->whereIn('id', $ncIds)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($eligible === []) {
                throw ValidationException::withMessages([
                    'form' => 'Tidak ada NC Open terpilih yang bisa dimasukkan ke Form Penutupan (NC closed atau sudah ada di draft form penutupan lain).',
                ]);
            }

            $form = LaporanNcForm::create([
                'data_kapal_id' => $dataKapalId,
                'jenis' => NcFormJenis::Penutupan,
                'nomor' => $this->nextNomor($dataKapalId),
                'tanggal' => now()->toDateString(),
            ]);
            $form->ncs()->sync($eligible);

            return [$form, count(array_unique($ncIds)) - count($eligible)];
        });
    }

    /**
     * NC yang boleh masuk Form Penutupan: Open & tidak ada di draft penutupan lain.
     */
    public function eligiblePenutupanQuery(int $dataKapalId): Builder
    {
        return LaporanNc::where('data_kapal_id', $dataKapalId)
            ->where('status', NcStatus::Open)
            ->whereDoesntHave('forms', fn (Builder $q) => $q->where('jenis', NcFormJenis::Penutupan)->whereNull('signed_at'));
    }

    /**
     * Unggah scan form bertanda tangan. Upload pertama form penutupan menutup
     * semua NC Open di dalamnya. Upload ulang hanya mengganti file.
     */
    public function uploadSigned(LaporanNcForm $form, UploadedFile $file, int $userId): int
    {
        $closed = 0;
        $job = null;

        DB::transaction(function () use ($form, $file, $userId, &$closed, &$job) {
            $firstSign = $form->isDraft();
            $oldPath = $form->file_path;

            $form->update([
                'file_name' => basename($file->getClientOriginalName()),
                'file_size' => $file->getSize(),
                'file_status' => 'pending',
                'file_error' => null,
                'signed_at' => $firstSign ? now() : $form->signed_at,
                'signed_by' => $firstSign ? $userId : $form->signed_by,
            ]);

            if ($firstSign && $form->isPenutupan()) {
                $closed = $form->ncs()->where('status', NcStatus::Open)->get()
                    ->each(fn (LaporanNc $nc) => $nc->update([
                        'status' => NcStatus::Closed,
                        'closed_at' => now(),
                        'closed_by' => $userId,
                    ]))
                    ->count();
            }

            $job = [$form, $file->store('temp/laporan-nc-form', 'local'), $oldPath];
        });

        ProcessNcFormFile::dispatch(...$job);

        return $closed;
    }

    /**
     * Hapus draft (belum bertanda tangan) — NC di dalamnya bebas dipilih lagi.
     */
    public function deleteDraft(LaporanNcForm $form): void
    {
        if (! $form->isDraft()) {
            throw ValidationException::withMessages([
                'form' => 'Form yang sudah ditandatangani tidak bisa dihapus.',
            ]);
        }

        $form->delete();
    }

    /**
     * Cleanup form temuan milik laporan harian yang dihapus (file scan ikut dihapus).
     */
    public function deleteForLaporan(LaporanHarian $laporan): void
    {
        LaporanNcForm::where('laporan_harian_id', $laporan->id)->get()->each(function (LaporanNcForm $form) {
            if ($form->file_path && Storage::disk('local')->exists($form->file_path)) {
                Storage::disk('local')->delete($form->file_path);
            }
            $form->delete();
        });
    }

    /**
     * Generate dokumen Word form (draft/cetak ulang) → path file sementara.
     */
    public function generateDocument(LaporanNcForm $form): string
    {
        return $this->wordService->generate($form);
    }

    public function downloadName(LaporanNcForm $form, bool $signed = false): string
    {
        $base = str($form->jenis->label().' '.$form->nomor.' '.($form->dataKapal?->nama ?? ''))->slug();

        return $signed
            ? $base.'-ttd.'.strtolower(pathinfo((string) $form->file_name, PATHINFO_EXTENSION))
            : $base.'.docx';
    }

    private function nextNomor(int $dataKapalId): string
    {
        $kode = DataKapal::whereKey($dataKapalId)->value('kode');
        $last = LaporanNcForm::where('data_kapal_id', $dataKapalId)->orderByDesc('id')->lockForUpdate()->value('nomor');
        $next = $last && preg_match('/(\d+)$/', $last, $m) ? (int) $m[1] + 1 : 1;

        return ($kode ? $kode.'-' : '').'NCR-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
