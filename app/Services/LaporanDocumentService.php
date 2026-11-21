<?php

namespace App\Services;

use App\Jobs\ReleaseLaporanBulananPdfJob;
use App\Jobs\ReleaseLaporanHarianPdfJob;
use App\Jobs\ReleaseLaporanMingguanPdfJob;
use App\Models\LaporanBulanan;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Siklus hidup dokumen Word laporan (doc_path) lintas rantai
 * harian → mingguan → bulanan.
 *
 * Laporan induk MEMAKAI ULANG docx anak yang sudah tergenerate; generate
 * hanya dijalankan bila file belum ada. Karena itu setiap perubahan isi
 * laporan wajib mengosongkan docx-nya sendiri beserta docx induk yang
 * melampirkannya (invalidate*), agar file yang dipakai ulang selalu
 * mencerminkan data terbaru.
 */
class LaporanDocumentService
{
    public function __construct(
        private readonly FilePathService $files,
        private readonly WordAttachmentService $attachment,
        private readonly KontrakPeriodeService $periode,
    ) {}

    /**
     * Hapus file docx beserta cache gambar halamannya.
     */
    public function deleteDocFile(?string $docPath): void
    {
        if (! $docPath) {
            return;
        }

        $this->files->delete($docPath);
        $this->attachment->forgetPageImages($docPath);
    }

    /**
     * Kosongkan dokumen Word + PDF rilis satu laporan (tanpa berantai).
     */
    public function clearDoc(LaporanHarian|LaporanMingguan|LaporanBulanan $laporan): void
    {
        $this->deleteDocFile($laporan->doc_path);

        $laporan->update([
            'doc_path' => null,
            'doc_name' => null,
            'doc_status' => null,
            'doc_generated_at' => null,
            'doc_error' => null,
        ]);

        match (true) {
            $laporan instanceof LaporanHarian => ReleaseLaporanHarianPdfJob::clearPdf($laporan),
            $laporan instanceof LaporanMingguan => ReleaseLaporanMingguanPdfJob::clearPdf($laporan),
            default => ReleaseLaporanBulananPdfJob::clearPdf($laporan),
        };
    }

    /**
     * Isi laporan harian berubah: docx harian + mingguan yang melampirkannya
     * (dan bulanan di atasnya) dikosongkan.
     */
    public function invalidateHarian(LaporanHarian $harian): void
    {
        $this->clearDoc($harian);
        $harian->laporanMingguan()->get()->each(fn (LaporanMingguan $mingguan) => $this->invalidateMingguan($mingguan));
    }

    /**
     * Isi laporan mingguan berubah: docx mingguan + bulanan yang melampirkannya.
     */
    public function invalidateMingguan(LaporanMingguan $mingguan): void
    {
        $this->clearDoc($mingguan);
        $this->bulananOf($mingguan)->each(fn (LaporanBulanan $bulanan) => $this->clearDoc($bulanan));
    }

    /**
     * @param  int[]  $mingguanIds
     */
    public function invalidateMingguanIds(array $mingguanIds): void
    {
        if ($mingguanIds !== []) {
            LaporanMingguan::whereIn('id', $mingguanIds)->get()->each(fn (LaporanMingguan $mingguan) => $this->invalidateMingguan($mingguan));
        }
    }

    /**
     * NC tampil di rekap SEMUA mingguan sejak dibuat (open terbawa terus,
     * closed hilang setelah periode penutupan) — perubahan NC / file NC
     * mengosongkan mingguan data kapal yang periodenya berakhir sejak $since.
     */
    public function invalidateNcScope(int $dataKapalId, CarbonInterface $since): void
    {
        LaporanMingguan::where('data_kapal_id', $dataKapalId)
            ->whereDate('periode_selesai', '>=', $since->toDateString())
            ->get()
            ->each(fn (LaporanMingguan $mingguan) => $this->invalidateMingguan($mingguan));
    }

    /**
     * @return Collection<int, LaporanBulanan>
     */
    private function bulananOf(LaporanMingguan $mingguan): Collection
    {
        $bulan = collect($this->periode->getBulanList($mingguan->dataKapal))
            ->first(fn (array $b) => $mingguan->minggu_ke >= $b['minggu_dari'] && $mingguan->minggu_ke <= $b['minggu_sampai']);

        return $bulan
            ? LaporanBulanan::where('data_kapal_id', $mingguan->data_kapal_id)->where('bulan_ke', $bulan['bulan_ke'])->get()
            : collect();
    }
}
