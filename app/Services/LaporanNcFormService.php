<?php

namespace App\Services;

use App\Enums\NcFileKategori;
use App\Enums\NcFormJenis;
use App\Enums\NcStatus;
use App\Jobs\ProcessNcFormFile;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
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
    public function __construct(
        private LaporanNcWordService $wordService,
    ) {}

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
     * @param  string|null  $tanggal  Tanggal form (Y-m-d); default hari ini
     * @return array{0: LaporanNcForm, 1: int} [form, jumlah NC yang dilewati]
     */
    public function createPenutupan(int $dataKapalId, array $ncIds, ?string $tanggal = null): array
    {
        return DB::transaction(function () use ($dataKapalId, $ncIds, $tanggal) {
            $eligible = $this->eligiblePenutupanQuery($dataKapalId)
                ->whereIn('id', $ncIds)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($eligible === []) {
                throw ValidationException::withMessages([
                    'form' => 'Tidak ada NC Open terpilih yang bisa dimasukkan ke Form Penutupan (NC closed, sudah ada di draft form penutupan lain, atau belum punya file Close).',
                ]);
            }

            $tanggalForm = $tanggal ?: now()->toDateString();

            // Tanggal penyelesaian (close) tidak boleh lebih awal dari tanggal
            // temuan/rekomendasi terakhir NC yang dipilih.
            $maxOpen = LaporanHarian::whereIn('id',
                LaporanNc::whereIn('id', $eligible)->select('laporan_harian_id')
            )->max('tanggal_laporan');
            if ($maxOpen && Carbon::parse($tanggalForm)->lt(Carbon::parse($maxOpen))) {
                throw ValidationException::withMessages([
                    'penutupanTanggal' => 'Tanggal penyelesaian tidak boleh lebih awal dari tanggal temuan NC ('.Carbon::parse($maxOpen)->locale('id')->translatedFormat('d F Y').').',
                ]);
            }

            $form = LaporanNcForm::create([
                'data_kapal_id' => $dataKapalId,
                'jenis' => NcFormJenis::Penutupan,
                'nomor' => $this->nextNomor($dataKapalId),
                'tanggal' => $tanggalForm,
            ]);
            $form->ncs()->sync($eligible);

            return [$form, count(array_unique($ncIds)) - count($eligible)];
        });
    }

    /**
     * NC yang boleh masuk Form Penutupan: Open, tidak ada di draft penutupan
     * lain, dan sudah punya file Close selesai diproses (dokumen NCR Close
     * hanya memuat lampiran Close — tanpa Close hasilnya kosong).
     */
    public function eligiblePenutupanQuery(int $dataKapalId): Builder
    {
        return LaporanNc::where('data_kapal_id', $dataKapalId)
            ->where('status', NcStatus::Open)
            ->whereDoesntHave('forms', fn (Builder $q) => $q->where('jenis', NcFormJenis::Penutupan)->whereNull('signed_at'))
            ->whereHas('foto', fn (Builder $q) => $q->where('kategori', NcFileKategori::Close)->where('file_status', 'completed'));
    }

    /**
     * Unggah scan form bertanda tangan. Upload pertama form penutupan menutup
     * semua NC Open di dalamnya. Upload ulang hanya mengganti file.
     */
    public function uploadSigned(LaporanNcForm $form, UploadedFile $file, int $userId): int
    {
        $closed = collect();
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
                        'closed_at' => $form->tanggal?->copy()->endOfDay() ?? now(),
                        'closed_by' => $userId,
                    ]));
            }

            $job = [$form, app(FileStorageService::class)->storeTemp($file, 'laporan-nc-form'), $oldPath];
        });

        ProcessNcFormFile::dispatch(...$job);

        // NC tertutup mengubah rekap NC mingguan → dokumen terkait dibuat ulang saat dipakai.
        $ncService = app(LaporanNcService::class);
        $closed->each(fn (LaporanNc $nc) => $ncService->invalidateDocuments($nc));

        return $closed->count();
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
            if ($form->file_path && Storage::disk()->exists($form->file_path)) {
                Storage::disk()->delete($form->file_path);
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
        $base = str($form->jenis->judulDokumen().' '.$form->nomor.' '.($form->dataKapal?->nama ?? ''))->slug();

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
